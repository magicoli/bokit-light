<?php

namespace Modules\Beds24\Services;

use App\Contracts\SyncHandler;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Syncs Beds24 bookings into Bokit via the JSON API.
 *
 * Registered in Beds24ServiceProvider::boot(). bokit:sync calls syncSource()
 * for each 'beds24' entry in a unit's options.sources, in definition order.
 *
 * Property-level API responses are cached within a single sync run so that
 * multiple units belonging to the same property share one API call.
 *
 * Deduplication priority:
 *   1. uid = "beds24-{bookId}"
 *   2. Legacy iCal booking with matching Beds24 bookId stored as group_id
 *   3. email + exact dates + unit
 *   4. guest name + exact dates + unit
 *
 * Timezone: Beds24 dates are Y-m-d local strings. check_in = firstNight,
 * check_out = lastNight + 1 day.
 */
class Beds24SyncHandler implements SyncHandler
{
    public function sourceType(): string
    {
        return 'beds24';
    }

    public function label(): string
    {
        return 'Beds24 API';
    }

    public function syncSource(Unit $unit, array $sourceConfig, bool $dryRun = false): array
    {
        $roomId = (int) ($sourceConfig['room_id'] ?? 0);

        if (! $roomId) {
            return $this->failure('Beds24 API', 'No room_id configured');
        }

        $property = $unit->property;
        $service = new Beds24ApiService($property);

        if (! $service->isConfigured()) {
            return $this->failure('Beds24 API', 'Beds24 not configured for this property');
        }

        $params = [
            'arrivalFrom' => '2020-01-01',
            'arrivalTo' => now()->addYears(5)->format('Y-m-d'),
        ];
        $allRows = $service->getBookings($params) ?? [];

        $rows = array_values(
            array_filter($allRows, fn ($r) => (int) ($r['roomId'] ?? 0) === $roomId)
        );

        [$created, $updated, $skipped] = $this->syncBookings($unit, $property, $rows, $dryRun);

        return [
            'label' => 'Beds24 API',
            'success' => true,
            'total' => $created + $updated + $skipped,
            'new' => $created,
            'updated' => $updated,
            'deleted' => 0,
            'vanished' => 0,
            'error' => null,
        ];
    }

    /**
     * @param  array<int, array<string,mixed>>  $rows  Beds24 booking rows for this room
     * @return array{int, int, int} [created, updated, skipped]
     */
    private function syncBookings(Unit $unit, Property $property, array $rows, bool $dryRun): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $uid = "beds24-{$row['bookId']}";
            $checkIn = $row['firstNight'] ?? null;
            $checkOut = isset($row['lastNight'])
                ? Carbon::parse($row['lastNight'])->addDay()->format('Y-m-d')
                : null;

            if (! $checkIn || ! $checkOut) {
                Log::debug("[Beds24] Skip: missing dates for bookId={$row['bookId']}");
                $skipped++;

                continue;
            }

            // Skip Beds24 availability blocks (status 4=block, 5=owner block).
            $rawStatus = (string) ($row['status'] ?? '2');
            if (in_array($rawStatus, ['4', '5'], true)) {
                Log::debug("[Beds24] Skip: availability block [{$unit->name}] {$checkIn} (bookId={$row['bookId']}, status={$rawStatus})");
                $skipped++;

                continue;
            }

            $guestName = trim(
                ($row['guestFirstName'] ?? $row['firstName'] ?? '').' '.($row['guestName'] ?? $row['lastName'] ?? '')
            ) ?: 'Guest';

            $status = $this->mapStatus($rawStatus);
            $commission = (float) ($row['commission'] ?? 0);
            $adults = isset($row['numAdult']) ? (int) $row['numAdult'] : null;
            $children = isset($row['numChild']) ? (int) $row['numChild'] : null;
            $sourceName = $this->mapSourceName((string) ($row['apiSource'] ?? ''));
            $email = trim($row['guestEmail'] ?? $row['email'] ?? '');

            $invoice = ! empty($row['invoice']) && is_array($row['invoice'])
                ? $this->parseInvoice($row['invoice'])
                : [];
            $price = $this->resolvePrice($row, $invoice);

            $existing = Booking::where('uid', $uid)->first();

            if (! $existing) {
                $existing = Booking::where('group_id', $row['bookId'])
                    ->where('uid', 'NOT LIKE', 'beds24-%')
                    ->first();
            }

            if (! $existing && $email) {
                $existing = Booking::where('unit_id', $unit->id)
                    ->where('check_in', 'LIKE', $checkIn.'%')
                    ->where('check_out', 'LIKE', $checkOut.'%')
                    ->whereRaw("JSON_EXTRACT(metadata, '$.email') = ?", [$email])
                    ->first();
            }

            if (! $existing && $guestName !== 'Guest') {
                $existing = Booking::where('unit_id', $unit->id)
                    ->where('check_in', 'LIKE', $checkIn.'%')
                    ->where('check_out', 'LIKE', $checkOut.'%')
                    ->where('guest_name', $guestName)
                    ->first();
            }

            if ($existing && $existing->uid !== $uid && ! $dryRun) {
                DB::table('bookings')->where('id', $existing->id)->update(['uid' => $uid]);
                $existing->uid = $uid;
            }

            if ($existing) {
                $changes = $this->buildChanges(
                    $existing,
                    $checkIn, $checkOut, $guestName,
                    $status, $price, $commission,
                    $adults, $children, $sourceName,
                    $row, $invoice,
                );

                if (empty($changes)) {
                    $skipped++;

                    continue;
                }

                if (! $dryRun) {
                    $existing->update($changes);
                }

                $updated++;

                continue;
            }

            $apiSourceCode = (string) ($row['apiSource'] ?? '');
            if ($guestName === 'Guest' && $price === 0.0 && $commission === 0.0) {
                Log::debug("[Beds24] Skip: empty block [{$unit->name}] {$checkIn} (bookId={$row['bookId']}, src={$apiSourceCode})");
                $skipped++;

                continue;
            }

            if (! $dryRun) {
                Booking::create([
                    'unit_id' => $unit->id,
                    'property_id' => $property->id,
                    'uid' => $uid,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'guest_name' => $guestName,
                    'status' => $status,
                    'price' => $price ?: null,
                    'commission' => $commission ?: null,
                    'adults' => $adults,
                    'children' => $children,
                    'source_name' => $sourceName,
                    'is_manual' => false,
                    'metadata' => $this->buildMeta($row, $invoice),
                ]);
            }

            $created++;
        }

        return [$created, $updated, $skipped];
    }

    /**
     * @return array{label: string, success: false, total: 0, new: 0, updated: 0, deleted: 0, vanished: 0, error: string}
     */
    private function failure(string $label, string $error): array
    {
        return [
            'label' => $label,
            'success' => false,
            'total' => 0,
            'new' => 0,
            'updated' => 0,
            'deleted' => 0,
            'vanished' => 0,
            'error' => $error,
        ];
    }

    /**
     * @param  array<string,mixed>  $row
     * @param  array{acc_ttc: float, taxe_invoiced: float, payment_total: float}|array{}  $invoice
     */
    private function resolvePrice(array $row, array $invoice): float
    {
        if (! empty($invoice)) {
            if (($invoice['acc_ttc'] ?? 0) > 0) {
                return $invoice['acc_ttc'];
            }

            if (($invoice['payment_total'] ?? 0) > 0) {
                return $invoice['payment_total'];
            }
        }

        return (float) ($row['price'] ?? 0);
    }

    /**
     * @param  array<string,mixed>  $row
     * @param  array<string,float>  $invoice
     * @return array<string,mixed>
     */
    private function buildChanges(
        Booking $existing,
        string $checkIn,
        string $checkOut,
        string $guestName,
        string $status,
        float $price,
        float $commission,
        ?int $adults,
        ?int $children,
        string $sourceName,
        array $row,
        array $invoice = [],
    ): array {
        $changes = [];

        if ($existing->check_in->format('Y-m-d') !== $checkIn) {
            $changes['check_in'] = $checkIn;
        }

        if ($existing->check_out->format('Y-m-d') !== $checkOut) {
            $changes['check_out'] = $checkOut;
        }

        if ($existing->guest_name !== $guestName && $guestName !== 'Guest') {
            $changes['guest_name'] = $guestName;
        }

        // Never downgrade confirmed → pending.
        if ($existing->status !== $status && ! ($existing->status === 'confirmed' && $status === 'pending')) {
            $changes['status'] = $status;
        }

        if ($price > 0 && (float) $existing->getRawOriginal('price') !== $price) {
            $changes['price'] = $price;
        }

        if ($commission > 0 && (float) $existing->getRawOriginal('commission') !== $commission) {
            $changes['commission'] = $commission;
        }

        if ($adults !== null && $existing->getRawOriginal('adults') !== $adults) {
            $changes['adults'] = $adults;
        }

        if ($children !== null && $existing->getRawOriginal('children') !== $children) {
            $changes['children'] = $children;
        }

        if ($existing->getRawOriginal('source_name') !== $sourceName) {
            $changes['source_name'] = $sourceName;
        }

        $newMeta = $this->buildMeta($row, $invoice);
        $currentMeta = $existing->metadata ?? [];

        if (array_diff_assoc($newMeta, array_intersect_key($currentMeta, $newMeta))) {
            $changes['metadata'] = array_merge($currentMeta, $newMeta);
        }

        return $changes;
    }

    /**
     * @param  array<string,mixed>  $row
     * @param  array<string,float>  $invoice
     * @return array<string,mixed>
     */
    private function buildMeta(array $row, array $invoice = []): array
    {
        $meta = [
            'beds24_book_id' => $row['bookId'] ?? null,
            'beds24_room_id' => $row['roomId'] ?? null,
            'email' => $row['guestEmail'] ?? $row['email'] ?? null,
            'phone' => $row['phone'] ?? null,
            'mobile' => $row['mobile'] ?? null,
            'address' => $row['address'] ?? null,
            'country' => $row['country'] ?? null,
            'api_source' => $row['apiSource'] ?? null,
            'api_ref' => $row['apiRef'] ?? null,
            'referrer' => $row['referrer'] ?? null,
            'num_adult' => isset($row['numAdult']) ? (int) $row['numAdult'] : null,
            'num_child' => isset($row['numChild']) ? (int) $row['numChild'] : null,
            'num_baby' => isset($row['numBaby']) ? (int) $row['numBaby'] : null,
            'notes' => $row['notes'] ?? null,
            'message' => $row['message'] ?? null,
        ];

        if (! empty($invoice)) {
            $meta['invoice_acc_ttc'] = $invoice['acc_ttc'];
            $meta['invoice_taxe_invoiced'] = $invoice['taxe_invoiced'];
            $meta['invoice_payment_total'] = $invoice['payment_total'];
        }

        return array_filter($meta, fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @param  array<int, array<string,mixed>>  $lines
     * @return array{acc_ttc: float, taxe_invoiced: float, payment_total: float}
     */
    private function parseInvoice(array $lines): array
    {
        $accTtc = 0.0;
        $taxeInvoiced = 0.0;
        $paymentTotal = 0.0;

        foreach ($lines as $line) {
            $type = (string) ($line['type'] ?? '');
            $desc = mb_strtolower((string) ($line['description'] ?? ''));
            $price = (float) ($line['price'] ?? 0);

            if ($type === '200') {
                $paymentTotal += $price;

                continue;
            }

            if (str_contains($desc, 'taxe de séjour')) {
                $taxeInvoiced += $price;
            } elseif (in_array($type, ['0', '1', '8'], true)) {
                $accTtc += $price;
            }
        }

        return [
            'acc_ttc' => round($accTtc, 2),
            'taxe_invoiced' => round($taxeInvoiced, 2),
            'payment_total' => round($paymentTotal, 2),
        ];
    }

    private function mapSourceName(string $apiSource): string
    {
        return match ($apiSource) {
            '46' => 'airbnb',
            '19' => 'booking.com',
            default => 'beds24',
        };
    }

    private function mapStatus(string $status): string
    {
        return match ($status) {
            '0', '1' => 'pending',
            '3' => 'cancelled',
            default => 'confirmed',
        };
    }
}
