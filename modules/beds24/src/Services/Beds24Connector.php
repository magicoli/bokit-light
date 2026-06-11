<?php

namespace Modules\Beds24\Services;

use App\Contracts\SourceConnector;
use App\Models\Property;
use App\Models\Unit;
use App\Support\NormalizedBooking;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Fetches Beds24 bookings via the JSON API and normalizes them for SyncEngine.
 *
 * Pure connector: no database access. Property-level API responses are cached
 * within a single sync run so that multiple units of the same property share
 * one API call.
 *
 * Origin detection: rows whose 'referer' starts with 'iCal Import' were
 * imported into Beds24 from an external calendar; the original UID is in
 * 'apiReference'. These are reported with an originHint so the engine never
 * lets Beds24 claim ownership of them.
 */
class Beds24Connector implements SourceConnector
{
    /** @var array<int, array<int, array<string,mixed>>>  property_id → raw Beds24 rows */
    private array $apiCache = [];

    public function sourceType(): string
    {
        return 'beds24';
    }

    public function label(): string
    {
        return 'Beds24 API';
    }

    public function displayLabel(array $sourceConfig): string
    {
        return 'Beds24 API';
    }

    public function sourceKey(Unit $unit, array $sourceConfig): string
    {
        return 'beds24';
    }

    /**
     * Direct link to the booking edit page. Note: the obvious control3.php
     * URL from the UI does not deep-link; control2.php?ajax=bookedit does
     * (same trick as taxesejour-bridge).
     */
    public function externalBookingUrl(string $externalId): ?string
    {
        return "https://beds24.com/control2.php?ajax=bookedit&id={$externalId}";
    }

    public function fetchBookings(Unit $unit, array $sourceConfig): array
    {
        $roomId = (int) ($sourceConfig['room_id'] ?? 0);

        if (! $roomId) {
            throw new \RuntimeException('No room_id configured');
        }

        $property = $unit->property;

        $allRows = $this->fetchRows($property);
        $masters = $this->indexGroupMasters($allRows);

        $rows = array_values(
            array_filter($allRows, fn ($row) => (int) ($row['roomId'] ?? 0) === $roomId)
        );

        $bookings = [];

        foreach ($rows as $row) {
            $normalized = $this->normalize($row, $unit, $masters);

            if ($normalized) {
                $bookings[] = $normalized;
            }
        }

        return $bookings;
    }

    /**
     * Index group master bookings of the property: a master carries a
     * non-empty 'group' field and a self-referential masterId. Group
     * sub-bookings reference it through their own masterId.
     *
     * @param  array<int, array<string,mixed>>  $rows
     * @return array<string, array<string,mixed>> bookId → master row
     */
    private function indexGroupMasters(array $rows): array
    {
        $masters = [];

        foreach ($rows as $row) {
            $bookId = (string) ($row['bookId'] ?? '');
            $masterId = trim((string) ($row['masterId'] ?? ''));

            if ($bookId !== '' && $masterId === $bookId && ! empty($row['group'])) {
                $masters[$bookId] = $row;
            }
        }

        return $masters;
    }

    /**
     * @return array<int, array<string,mixed>>
     *
     * @throws \RuntimeException
     */
    protected function fetchRows(Property $property): array
    {
        if (isset($this->apiCache[$property->id])) {
            return $this->apiCache[$property->id];
        }

        $service = new Beds24ApiService($property);

        if (! $service->isConfigured()) {
            throw new \RuntimeException('Beds24 not configured for this property');
        }

        return $this->apiCache[$property->id] = $service->getBookings([
            'arrivalFrom' => '2020-01-01',
            'arrivalTo' => now()->addYears(5)->format('Y-m-d'),
        ]) ?? [];
    }

    /**
     * @param  array<string,mixed>  $row
     * @param  array<string, array<string,mixed>>  $masters  bookId → group master row
     */
    private function normalize(array $row, Unit $unit, array $masters = []): ?NormalizedBooking
    {
        $checkIn = $row['firstNight'] ?? null;
        $checkOut = isset($row['lastNight'])
            ? Carbon::parse($row['lastNight'])->addDay()->format('Y-m-d')
            : null;

        if (! $checkIn || ! $checkOut) {
            Log::debug("[Beds24] Skip: missing dates for bookId={$row['bookId']}");

            return null;
        }

        $rawStatus = (string) ($row['status'] ?? '2');
        $isBlock = $rawStatus === '4';

        // Past or ongoing blocks (Beds24 'Black') are availability artifacts;
        // only future ones are meaningful.
        if ($isBlock && Carbon::parse($checkIn)->lte(now()->startOfDay())) {
            Log::debug("[Beds24] Skip: past block [{$unit->name}] {$checkIn} (bookId={$row['bookId']})");

            return null;
        }

        $masterId = trim((string) ($row['masterId'] ?? ''));
        $master = $masterId !== '' ? ($masters[$masterId] ?? null) : null;
        $isGroupMaster = $master !== null && $masterId === (string) ($row['bookId'] ?? '');
        $isGroupSub = $master !== null && ! $isGroupMaster;
        $masterConfirmed = $master !== null && in_array((string) ($master['status'] ?? ''), ['1', '2'], true);

        $guestName = trim(
            ($row['guestFirstName'] ?? $row['firstName'] ?? '').' '.($row['guestName'] ?? $row['lastName'] ?? '')
        ) ?: 'Guest';

        // Group sub-bookings often carry no guest of their own — use the master's.
        if ($guestName === 'Guest' && $isGroupSub) {
            $guestName = trim(
                ($master['guestFirstName'] ?? '').' '.($master['guestName'] ?? '')
            ) ?: 'Guest';
        }

        $commission = (float) ($row['commission'] ?? 0);

        $invoice = ! empty($row['invoice']) && is_array($row['invoice'])
            ? $this->parseInvoice($row['invoice'])
            : [];
        $price = $this->resolvePrice($row, $invoice, $isGroupMaster, $isGroupSub);

        // Empty placeholder rows (no guest, no money) are channel artifacts —
        // unless they belong to a group (group rows are real occupancies even
        // when the amounts and guest live on another booking of the group) or
        // are blocks, which carry no guest or price by nature.
        if ($guestName === 'Guest' && $price === 0.0 && $commission === 0.0 && $masterId === '' && ! $isBlock) {
            Log::debug("[Beds24] Skip: empty block [{$unit->name}] {$checkIn} (bookId={$row['bookId']})");

            return null;
        }

        // Beds24 assigns the placeholder status 3 (Request) to sub-bookings of
        // group reservations; they are real bookings when the master is confirmed.
        $status = $this->mapStatus($rawStatus);
        if ($rawStatus === '3' && $isGroupSub && $masterConfirmed) {
            $status = 'confirmed';
        }

        // 'New' is not a status of its own: the booking is confirmed, the
        // tag just flags it until it is acknowledged in Beds24.
        $isNew = $rawStatus === '2';

        $originHint = null;
        $referer = (string) ($row['referer'] ?? '');

        if (str_starts_with($referer, 'iCal Import')) {
            $icalUid = trim((string) ($row['apiReference'] ?? ''));

            if ($icalUid !== '') {
                $originHint = ['type' => 'ical', 'external_id' => $icalUid];
            }
        }

        $email = trim($row['guestEmail'] ?? $row['email'] ?? '') ?: null;

        $metadata = $this->buildMeta($row, $invoice);
        $metadata['is_new'] = $isNew;

        if ($master !== null) {
            $groupTotal = $this->resolveGroupTotal($master);

            if ($groupTotal > 0) {
                $metadata['group_total'] = $groupTotal;
            }
        }

        return new NormalizedBooking(
            externalId: (string) $row['bookId'],
            checkIn: $checkIn,
            checkOut: $checkOut,
            guestName: $guestName,
            status: $status,
            email: $email,
            price: $price ?: null,
            commission: $commission ?: null,
            adults: isset($row['numAdult']) ? (int) $row['numAdult'] : null,
            children: isset($row['numChild']) ? (int) $row['numChild'] : null,
            channel: $this->mapSourceName((string) ($row['apiSource'] ?? '')),
            metadata: $metadata,
            originHint: $originHint,
            legacyUid: "beds24-{$row['bookId']}",
            claimsOrigin: $originHint === null,
            groupId: $masterId ?: null,
        );
    }

    /**
     * Resolve the accommodation amount, mirroring taxesejour-bridge:
     * - invoice with positive accommodation total → use it;
     * - no invoice: group masters report 0 (amounts live on sub-bookings,
     *   using the price field would double-count), group subs report 0 too
     *   (Beds24 replicates the group total in every sub's price field —
     *   summing them would multiply the group price by the number of units),
     *   standalone bookings use the price field;
     * - invoice present but acc ≤ 0 (large discount, manual entry): standalone
     *   bookings use the payment lines (what was actually received), with the
     *   price field as last resort.
     *
     * @param  array<string,mixed>  $row
     * @param  array{acc_ttc: float, taxe_invoiced: float, payment_total: float}|array{}  $invoice
     */
    private function resolvePrice(array $row, array $invoice, bool $isGroupMaster, bool $isGroupSub = false): float
    {
        if (($invoice['acc_ttc'] ?? 0) > 0) {
            return $invoice['acc_ttc'];
        }

        if ($isGroupSub) {
            return ($invoice['payment_total'] ?? 0) > 0 ? $invoice['payment_total'] : 0.0;
        }

        $priceField = (float) ($row['price'] ?? 0);

        if (empty($invoice)) {
            return $isGroupMaster ? 0.0 : $priceField;
        }

        if (! $isGroupMaster && ($invoice['payment_total'] ?? 0) > 0) {
            return $invoice['payment_total'];
        }

        return $priceField;
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
            'api_ref' => $row['apiReference'] ?? null,
            'referrer' => $row['referer'] ?? null,
            'master_id' => trim((string) ($row['masterId'] ?? '')) ?: null,
            'num_adult' => isset($row['numAdult']) ? (int) $row['numAdult'] : null,
            'num_child' => isset($row['numChild']) ? (int) $row['numChild'] : null,
            'num_baby' => isset($row['numBaby']) ? (int) $row['numBaby'] : null,
            'notes' => $row['notes'] ?? null,
            'message' => $row['message'] ?? null,
        ];

        $deposit = (float) ($row['deposit'] ?? 0);
        $tax = (float) ($row['tax'] ?? 0);
        $meta['deposit'] = $deposit ?: null;
        $meta['tax'] = $tax ?: null;

        if (! empty($invoice)) {
            $meta['invoice_acc_ttc'] = $invoice['acc_ttc'];
            $meta['invoice_taxe_invoiced'] = $invoice['taxe_invoiced'];
            $meta['invoice_payment_total'] = $invoice['payment_total'];
        }

        if (! empty($row['invoice']) && is_array($row['invoice'])) {
            $meta['invoice_lines'] = array_map(fn (array $line): array => [
                'type' => (string) ($line['type'] ?? ''),
                'description' => (string) ($line['description'] ?? ''),
                'qty' => (float) ($line['qty'] ?? 1),
                'price' => (float) ($line['price'] ?? 0),
            ], array_values($row['invoice']));
        }

        return array_filter($meta, fn ($v) => $v !== null && $v !== '');
    }

    /**
     * The group's total price as Beds24 reports it on the master booking.
     * Distinct from per-unit prices: it must never be summed with them.
     *
     * @param  array<string,mixed>  $master
     */
    private function resolveGroupTotal(array $master): float
    {
        $invoice = ! empty($master['invoice']) && is_array($master['invoice'])
            ? $this->parseInvoice($master['invoice'])
            : [];

        if (($invoice['acc_ttc'] ?? 0) > 0) {
            return $invoice['acc_ttc'];
        }

        if (($invoice['payment_total'] ?? 0) > 0) {
            return $invoice['payment_total'];
        }

        return (float) ($master['price'] ?? 0);
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

    /**
     * Map Beds24 v1 status codes to Bokit's canonical statuses
     * (see Booking::STATUSES): 0=Cancelled → cancelled, 1=Confirmed and
     * 2=New → confirmed (New only adds the is_new metadata tag),
     * 3=Request → option (dates blocked), 4=Black → blocked,
     * 5=Inquiry → quote (priced but not blocking).
     */
    private function mapStatus(string $status): string
    {
        return match ($status) {
            '0' => 'cancelled',
            '3' => 'option',
            '4' => 'blocked',
            '5' => 'quote',
            default => 'confirmed',
        };
    }
}
