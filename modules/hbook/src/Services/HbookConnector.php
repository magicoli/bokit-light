<?php

namespace Modules\Hbook\Services;

use App\Contracts\SourceConnector;
use App\Models\BookingSource;
use App\Models\Property;
use App\Models\Unit;
use App\Support\NormalizedBooking;
use Modules\WpConnector\Services\WpConnectorService;

/**
 * Fetches HBook (direct website) bookings through the bokit-connector
 * WordPress plugin and normalizes them for SyncEngine.
 *
 * Pure connector: no database access. The property-level API response is
 * cached within a single sync run so all units share one HTTP call.
 *
 * ## HBook group model
 *
 * The WP endpoint returns two kinds of rows sharing one hbook_uid:
 * - the direct hb_resa row (is_blocked=false) — carries guest, price,
 *   deposit, paid and occupancy;
 * - one row per automatically blocked unit (is_blocked=true). HBook
 *   self-blocks every booking's own accommodation, and package
 *   accommodations ("Site entier") additionally block the real units they
 *   cover.
 *
 * A booking whose direct row sits on this unit's accommodation is a solo
 * booking (its blocked rows are self-blocks, ignored). When the direct row
 * sits on an untracked package accommodation and a blocked row points to
 * this unit, the unit is a member of a group reservation: the member on
 * the lowest accommodation id carries the group's price and occupancy
 * (mirroring the Beds24 master convention, so the sum of unit prices
 * equals the group total), the other members carry none.
 *
 * HBook is the origin of website bookings, so the connector claims origin.
 * hb_resa.uid doubles as the iCal UID of the site's exported feeds, which
 * lets the engine connect placeholder origins created from Beds24's
 * "iCal Import" referers.
 */
class HbookConnector implements SourceConnector
{
    /** @var array<int, array<int, array<string,mixed>>> property_id → raw rows */
    private array $apiCache = [];

    public function sourceType(): string
    {
        return 'hbook';
    }

    public function label(): string
    {
        return 'HBook';
    }

    public function displayLabel(array $sourceConfig): string
    {
        return 'HBook';
    }

    public function sourceKey(Unit $unit, array $sourceConfig): string
    {
        $host = parse_url($unit->property->options['wp_url'] ?? '', PHP_URL_HOST);

        return 'hbook'.($host ? ":{$host}" : '');
    }

    /**
     * HBook's WP admin has no per-booking deep link; the closest native
     * view is the reservations page filtered on the customer.
     */
    public function externalBookingUrl(BookingSource $source): ?string
    {
        $booking = $source->booking;
        $customerId = $booking?->getMetadata('hbook_customer_id');
        $wpUrl = rtrim($booking?->property?->options['wp_url'] ?? '', '/');

        if (! $customerId || $wpUrl === '') {
            return null;
        }

        return "{$wpUrl}/wp-admin/admin.php?page=hb_reservations&customer_id={$customerId}";
    }

    public function fetchBookings(Unit $unit, array $sourceConfig): array
    {
        $hbookUnitId = (string) ($sourceConfig['hbook_unit_id'] ?? '');

        if ($hbookUnitId === '') {
            throw new \RuntimeException('No hbook_unit_id configured');
        }

        $byUid = [];

        foreach ($this->fetchRows($unit->property) as $row) {
            if (! empty($row['hbook_uid'])) {
                $byUid[(string) $row['hbook_uid']][] = $row;
            }
        }

        $bookings = [];

        foreach ($byUid as $hbookUid => $group) {
            $parent = collect($group)->first(fn (array $r): bool => ! ($r['is_blocked'] ?? false));

            if (! $parent) {
                // Orphaned blocked rows without their hb_resa — nothing usable.
                continue;
            }

            if ((string) ($parent['unit_id'] ?? '') === $hbookUnitId) {
                // Solo booking on this unit; its blocked rows are self-blocks.
                $bookings[] = $this->normalize(
                    parent: $parent,
                    datesRow: $parent,
                    externalId: $hbookUid,
                    carriesAmounts: true,
                    groupId: null,
                );

                continue;
            }

            $blocked = array_values(array_filter($group, fn (array $r): bool => (bool) ($r['is_blocked'] ?? false)));
            $mine = collect($blocked)->first(fn (array $r): bool => (string) ($r['unit_id'] ?? '') === $hbookUnitId);

            if (! $mine) {
                continue;
            }

            // Group member: deterministic carrier = lowest accommodation id.
            $carrierUnitId = collect($blocked)->pluck('unit_id')->map(fn ($v): string => (string) $v)->sort()->first();

            $bookings[] = $this->normalize(
                parent: $parent,
                datesRow: $mine,
                externalId: "{$hbookUid}#{$hbookUnitId}",
                carriesAmounts: $hbookUnitId === $carrierUnitId,
                groupId: 'hbook-'.($parent['id'] ?? $hbookUid),
            );
        }

        return $bookings;
    }

    /**
     * @param  array<string,mixed>  $parent  hb_resa row — guest, money, occupancy
     * @param  array<string,mixed>  $datesRow  row carrying this unit's dates
     */
    private function normalize(
        array $parent,
        array $datesRow,
        string $externalId,
        bool $carriesAmounts,
        ?string $groupId,
    ): NormalizedBooking {
        $hbookUid = (string) ($parent['hbook_uid'] ?? '');
        $price = (float) ($parent['price'] ?? 0);

        $metadata = array_filter([
            'hbook_id' => $parent['id'] ?? null,
            'hbook_uid' => $hbookUid,
            'hbook_customer_id' => $parent['customer_id'] ?? null,
            'email' => $parent['guest_email'] ?? null,
            'phone' => $parent['guest_phone'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        if ($carriesAmounts) {
            $metadata['deposit'] = (float) ($parent['deposit'] ?? 0) ?: null;
            $metadata['paid'] = (float) ($parent['paid'] ?? 0) ?: null;
        }

        if ($groupId !== null && $price > 0) {
            $metadata['group_total'] = $price;
        }

        return new NormalizedBooking(
            externalId: $externalId,
            checkIn: (string) $datesRow['check_in'],
            checkOut: (string) $datesRow['check_out'],
            guestName: trim((string) ($parent['guest_name'] ?? '')) ?: 'Guest',
            status: $this->mapStatus((string) ($parent['status'] ?? '')),
            email: trim((string) ($parent['guest_email'] ?? '')) ?: null,
            price: $carriesAmounts ? ($price ?: null) : null,
            adults: $carriesAmounts && isset($parent['adults']) ? (int) $parent['adults'] : null,
            children: $carriesAmounts && isset($parent['children']) ? (int) $parent['children'] : null,
            channel: 'hbook',
            metadata: array_filter($metadata, fn ($v) => $v !== null),
            legacyUid: 'hbook:'.$hbookUid,
            claimsOrigin: true,
            groupId: $groupId,
            sourceCreatedAt: self::dateOrNull($parent['created_at'] ?? null),
            sourceUpdatedAt: self::dateOrNull($parent['updated_at'] ?? null),
        );
    }

    /**
     * HBook DATETIME columns are NOT NULL: old rows may carry MySQL
     * zero-dates instead of real values.
     */
    private static function dateOrNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return ($value === '' || str_starts_with($value, '0000')) ? null : $value;
    }

    /**
     * Map HBook statuses to Bokit's canonical statuses (see
     * Booking::STATUSES). The endpoint already excludes cancelled/deleted.
     */
    private function mapStatus(string $status): string
    {
        return match ($status) {
            'pending' => 'option',
            'cancelled' => 'cancelled',
            default => 'confirmed',
        };
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

        $service = new WpConnectorService($property);

        if (! $service->isConfigured()) {
            throw new \RuntimeException('WordPress connection not configured for this property');
        }

        $response = $service->get('/wp-json/bokit/v1/bookings/hbook');

        if (! $response->successful()) {
            throw new \RuntimeException("WP API returned HTTP {$response->status()}");
        }

        return $this->apiCache[$property->id] = $response->json() ?? [];
    }
}
