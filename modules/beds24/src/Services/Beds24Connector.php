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

    public function fetchBookings(Unit $unit, array $sourceConfig): array
    {
        $roomId = (int) ($sourceConfig['room_id'] ?? 0);

        if (! $roomId) {
            throw new \RuntimeException('No room_id configured');
        }

        $property = $unit->property;

        $rows = array_values(
            array_filter($this->fetchRows($property), fn ($row) => (int) ($row['roomId'] ?? 0) === $roomId)
        );

        $bookings = [];

        foreach ($rows as $row) {
            $normalized = $this->normalize($row, $unit);

            if ($normalized) {
                $bookings[] = $normalized;
            }
        }

        return $bookings;
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
     */
    private function normalize(array $row, Unit $unit): ?NormalizedBooking
    {
        $checkIn = $row['firstNight'] ?? null;
        $checkOut = isset($row['lastNight'])
            ? Carbon::parse($row['lastNight'])->addDay()->format('Y-m-d')
            : null;

        if (! $checkIn || ! $checkOut) {
            Log::debug("[Beds24] Skip: missing dates for bookId={$row['bookId']}");

            return null;
        }

        // Beds24 availability blocks (status 4=block, 5=owner block) are not bookings.
        $rawStatus = (string) ($row['status'] ?? '2');
        if (in_array($rawStatus, ['4', '5'], true)) {
            Log::debug("[Beds24] Skip: availability block [{$unit->name}] {$checkIn} (bookId={$row['bookId']}, status={$rawStatus})");

            return null;
        }

        $guestName = trim(
            ($row['guestFirstName'] ?? $row['firstName'] ?? '').' '.($row['guestName'] ?? $row['lastName'] ?? '')
        ) ?: 'Guest';

        $commission = (float) ($row['commission'] ?? 0);

        $invoice = ! empty($row['invoice']) && is_array($row['invoice'])
            ? $this->parseInvoice($row['invoice'])
            : [];
        $price = $this->resolvePrice($row, $invoice);

        // Empty placeholder rows (no guest, no money) are channel artifacts.
        if ($guestName === 'Guest' && $price === 0.0 && $commission === 0.0) {
            Log::debug("[Beds24] Skip: empty block [{$unit->name}] {$checkIn} (bookId={$row['bookId']})");

            return null;
        }

        $originHint = null;
        $referer = (string) ($row['referer'] ?? '');

        if (str_starts_with($referer, 'iCal Import')) {
            $icalUid = trim((string) ($row['apiReference'] ?? ''));

            if ($icalUid !== '') {
                $originHint = ['type' => 'ical', 'external_id' => $icalUid];
            }
        }

        $email = trim($row['guestEmail'] ?? $row['email'] ?? '') ?: null;

        return new NormalizedBooking(
            externalId: (string) $row['bookId'],
            checkIn: $checkIn,
            checkOut: $checkOut,
            guestName: $guestName,
            status: $this->mapStatus($rawStatus),
            email: $email,
            price: $price ?: null,
            commission: $commission ?: null,
            adults: isset($row['numAdult']) ? (int) $row['numAdult'] : null,
            children: isset($row['numChild']) ? (int) $row['numChild'] : null,
            channel: $this->mapSourceName((string) ($row['apiSource'] ?? '')),
            metadata: $this->buildMeta($row, $invoice),
            originHint: $originHint,
            legacyUid: "beds24-{$row['bookId']}",
            claimsOrigin: $originHint === null,
        );
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

    /**
     * Beds24 v1 status codes: 0=Cancelled, 1=Confirmed, 2=New, 3=Request,
     * 4=Black (block), 5=Inquiry. 4 and 5 are filtered out in normalize().
     */
    private function mapStatus(string $status): string
    {
        return match ($status) {
            '0' => 'cancelled',
            '3' => 'pending',
            default => 'confirmed',
        };
    }
}
