<?php

namespace Modules\Beds24\Services;

use App\Contracts\PushableConnector;
use App\Contracts\SourceConnector;
use App\Models\Booking;
use App\Models\BookingSource;
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
class Beds24Connector implements PushableConnector, SourceConnector
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
    public function externalBookingUrl(BookingSource $source): ?string
    {
        return "https://beds24.com/control2.php?ajax=bookedit&id={$source->external_id}";
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
        if ($guestName === 'Guest' && ($price ?? 0.0) === 0.0 && $commission === 0.0 && $masterId === '' && ! $isBlock) {
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
            price: $price,
            commission: $commission ?: null,
            adults: isset($row['numAdult']) ? (int) $row['numAdult'] : null,
            children: isset($row['numChild']) ? (int) $row['numChild'] : null,
            channel: $this->mapSourceName((string) ($row['apiSource'] ?? '')),
            metadata: $metadata,
            originHint: $originHint,
            legacyUid: "beds24-{$row['bookId']}",
            claimsOrigin: $originHint === null,
            sourceCreatedAt: trim((string) ($row['bookingTime'] ?? '')) ?: null,
            sourceUpdatedAt: trim((string) ($row['modified'] ?? '')) ?: null,
            groupId: $masterId ?: null,
        );
    }

    /**
     * Resolve the booking price — the full invoice total, every charge line
     * included (taxe de séjour, fees, discounts), since all of it is owed:
     * - invoice with positive total → use it;
     * - no invoice: group masters report 0 (amounts live on sub-bookings,
     *   using the price field would double-count), group subs report 0 too
     *   (Beds24 replicates the group total in every sub's price field —
     *   summing them would multiply the group price by the number of units),
     *   standalone bookings use the price field;
     * - invoice present but total ≤ 0 (large discount, manual entry):
     *   standalone bookings use the payment lines (what was actually
     *   received), with the price field as last resort.
     *
     * @param  array<string,mixed>  $row
     * @param  array{total: float, acc_ttc: float, taxe_invoiced: float, payment_total: float}|array{}  $invoice
     */
    /**
     * Resolve the booking price, distinguishing a DEFINITIVE value (a real
     * float, including 0 — e.g. an invoice the user zeroed) from UNKNOWN
     * (null — no pricing info, the engine then leaves the stored price
     * untouched).
     */
    private function resolvePrice(array $row, array $invoice, bool $isGroupMaster, bool $isGroupSub = false): ?float
    {
        // An invoice with a non-zero total is the definitive price.
        if (($invoice['total'] ?? 0) != 0.0) {
            return $invoice['total'];
        }

        // Group members carry only their own invoice; otherwise priceless —
        // the group total lives on the carrier member.
        if ($isGroupSub || $isGroupMaster) {
            return null;
        }

        // Solo booking with an invoice that nets to zero: use what was
        // actually paid, else a definitive 0 (the user emptied the invoice).
        if (! empty($invoice)) {
            return ($invoice['payment_total'] ?? 0) > 0 ? $invoice['payment_total'] : 0.0;
        }

        // Solo booking with no invoice: trust only a positive price field;
        // a 0 there is ambiguous (CESL with no invoice yet) → unknown.
        $priceField = (float) ($row['price'] ?? 0);

        return $priceField > 0 ? $priceField : null;
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
            $meta['invoice_total'] = $invoice['total'];
            $meta['invoice_acc_ttc'] = $invoice['acc_ttc'];
            $meta['invoice_taxe_invoiced'] = $invoice['taxe_invoiced'];
            $meta['invoice_payment_total'] = $invoice['payment_total'];
        }

        if (! empty($row['invoice']) && is_array($row['invoice'])) {
            $meta['invoice_lines'] = array_map(function (array $line): array {
                $qty = (float) ($line['qty'] ?? 1) ?: 1.0;
                $price = (float) ($line['price'] ?? 0);

                return [
                    'type' => (string) ($line['type'] ?? ''),
                    'description' => (string) ($line['description'] ?? ''),
                    'qty' => $qty,
                    'price' => $price,
                    'amount' => round($qty * $price, 2),
                ];
            }, array_values($row['invoice']));
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

        if (($invoice['total'] ?? 0) > 0) {
            return $invoice['total'];
        }

        if (($invoice['payment_total'] ?? 0) > 0) {
            return $invoice['payment_total'];
        }

        return (float) ($master['price'] ?? 0);
    }

    /**
     * Parse the Beds24 invoice. Line amounts are qty × price (Beds24 returns
     * unit prices: e.g. 7 nights × 100 € come as qty=7, price=100; payment
     * lines come as qty=-1 so they reduce the invoice balance).
     *
     * - total: every charge line (accommodation, fees, taxe de séjour,
     *   discounts) — this is what the client owes;
     * - acc_ttc / taxe_invoiced: accommodation vs taxe breakdown;
     * - payment_total: what the client actually paid (type-200 lines).
     *
     * @param  array<int, array<string,mixed>>  $lines
     * @return array{total: float, acc_ttc: float, taxe_invoiced: float, payment_total: float}
     */
    private function parseInvoice(array $lines): array
    {
        $total = 0.0;
        $accTtc = 0.0;
        $taxeInvoiced = 0.0;
        $paymentTotal = 0.0;

        foreach ($lines as $line) {
            $type = (string) ($line['type'] ?? '');
            $desc = mb_strtolower((string) ($line['description'] ?? ''));
            $qty = (float) ($line['qty'] ?? 1) ?: 1.0;
            $amount = $qty * (float) ($line['price'] ?? 0);

            if ($type === '200') {
                $paymentTotal += abs($amount);

                continue;
            }

            $total += $amount;

            if (str_contains($desc, 'taxe de séjour')) {
                $taxeInvoiced += $amount;
            } elseif (in_array($type, ['0', '1', '8'], true)) {
                $accTtc += $amount;
            }
        }

        return [
            'total' => round($total, 2),
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
     * Push a bokit-origin booking to Beds24 through the V2 API. Without an
     * external id the booking is created; with one it is updated in place
     * (cancellation = canonical status mapped to 'cancelled').
     */
    public function pushBooking(Unit $unit, array $sourceConfig, Booking $booking, ?string $externalId): string
    {
        $roomId = (int) ($sourceConfig['room_id'] ?? 0);

        if (! $roomId) {
            throw new \RuntimeException('No room_id configured');
        }

        [$firstName, $lastName] = self::splitGuestName($booking->guest_name);

        $payload = array_filter([
            'id' => $externalId !== null ? (int) $externalId : null,
            'roomId' => $roomId,
            'status' => self::pushStatus($booking),
            'arrival' => $booking->check_in->format('Y-m-d'),
            'departure' => $booking->check_out->format('Y-m-d'),
            'firstName' => $firstName,
            'lastName' => $lastName,
            'numAdult' => $booking->adults,
            'numChild' => $booking->children,
            'email' => $booking->getMetadata('email'),
            'mobile' => $booking->getMetadata('phone') ?? $booking->getMetadata('mobile'),
            'price' => $booking->getRawOriginal('price') !== null ? (float) $booking->getRawOriginal('price') : null,
            'comments' => $booking->notes,
            'refererEditable' => 'bokit',
        ], fn ($v) => $v !== null && $v !== '');

        $results = $this->v2Service($unit->property)->postBookings([$payload]);
        $result = $results[0] ?? [];

        if (! ($result['success'] ?? false)) {
            throw new \RuntimeException('Beds24 push rejected: '.json_encode($result['errors'] ?? $result));
        }

        $newId = $result['new']['id'] ?? $result['modified']['id'] ?? $externalId;

        if ($newId === null) {
            throw new \RuntimeException('Beds24 push returned no booking id: '.json_encode($result));
        }

        return (string) $newId;
    }

    /**
     * Canonical status → Beds24 V2 status. Trashed bokit bookings push as
     * cancellations so the dates free up everywhere.
     */
    private static function pushStatus(Booking $booking): string
    {
        if ($booking->trashed()) {
            return 'cancelled';
        }

        return match ($booking->status) {
            'option' => 'request',
            'quote' => 'inquiry',
            'blocked' => 'black',
            'cancelled', 'deleted', 'vanished' => 'cancelled',
            default => 'confirmed',
        };
    }

    /**
     * Bokit stores one guest name; Beds24 wants first/last.
     *
     * @return array{string, string}
     */
    private static function splitGuestName(string $guestName): array
    {
        $parts = explode(' ', trim($guestName), 2);

        return count($parts) === 2 ? [$parts[0], $parts[1]] : ['', $parts[0] ?? ''];
    }

    protected function v2Service(Property $property): Beds24V2ApiService
    {
        return new Beds24V2ApiService($property);
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
