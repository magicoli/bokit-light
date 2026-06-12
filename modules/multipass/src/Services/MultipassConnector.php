<?php

namespace Modules\Multipass\Services;

use App\Contracts\SourceConnector;
use App\Models\BookingSource;
use App\Models\Property;
use App\Models\Unit;
use App\Support\NormalizedBooking;
use Modules\WpConnector\Services\WpConnectorService;

/**
 * Fetches Multipass prestations through the bokit-connector WordPress
 * plugin and normalizes them for SyncEngine.
 *
 * Pure connector: no database access. The property-level API response is
 * cached within a single sync run so all units share one HTTP call.
 *
 * ## Multipass model
 *
 * A prestation is one client booking that may span several resources:
 * lodging units, but also services (car rental, boat, table d'hôtes…),
 * each as a detail line with its own subtotal. Only details on resources
 * configured as bokit units count as occupancies; service lines only
 * contribute to the total.
 *
 * - Solo prestation (one configured unit): the booking carries the full
 *   prestation total — everything the client owes, services included.
 * - Multi-unit prestation: a group reservation. Each member carries its
 *   own detail subtotal; the member on the lowest resource id absorbs the
 *   remainder (services, rounding) so the sum of members equals the total.
 *
 * Multipass aggregated bookings from several channels (its origin meta
 * keeps the canal), so the connector never claims origin: where Beds24 or
 * HBook already own a booking, Multipass attaches as information; bookings
 * it alone knows (historical data) are synced as their first reference.
 */
class MultipassConnector implements SourceConnector
{
    /** @var array<int, array<int, array<string,mixed>>> property_id → raw prestations */
    private array $apiCache = [];

    public function sourceType(): string
    {
        return 'multipass';
    }

    public function label(): string
    {
        return 'Multipass';
    }

    public function displayLabel(array $sourceConfig): string
    {
        return 'Multipass';
    }

    public function sourceKey(Unit $unit, array $sourceConfig): string
    {
        $host = parse_url($unit->property->options['wp_url'] ?? '', PHP_URL_HOST);

        return 'multipass'.($host ? ":{$host}" : '');
    }

    /**
     * Prestations are WordPress posts: the native admin edit page is the
     * booking's page.
     */
    public function externalBookingUrl(BookingSource $source): ?string
    {
        $wpUrl = rtrim($source->booking?->property?->options['wp_url'] ?? '', '/');

        if ($wpUrl === '') {
            return null;
        }

        $prestationId = (int) explode('#', $source->external_id)[0];

        return "{$wpUrl}/wp-admin/post.php?post={$prestationId}&action=edit";
    }

    public function fetchBookings(Unit $unit, array $sourceConfig): array
    {
        $resourceId = (string) ($sourceConfig['multipass_unit_id'] ?? '');

        if ($resourceId === '') {
            throw new \RuntimeException('No multipass_unit_id configured');
        }

        // Resource ids configured as bokit units on this property: details
        // on these resources are occupancies, anything else is a service.
        $unitResourceIds = $this->configuredResourceIds($unit->property);

        $bookings = [];

        foreach ($this->fetchRows($unit->property) as $prestation) {
            $unitDetails = collect($prestation['units'] ?? [])
                ->filter(fn (array $d): bool => in_array((string) ($d['resource_id'] ?? ''), $unitResourceIds, true))
                ->values();

            $mine = $unitDetails->first(fn (array $d): bool => (string) $d['resource_id'] === $resourceId);

            if (! $mine) {
                continue;
            }

            // Dateless prestations (unfinished drafts) are unusable — an
            // empty date would be parsed as "now" by the model mutators.
            if (empty($mine['check_in'] ?? $prestation['check_in'])
                || empty($mine['check_out'] ?? $prestation['check_out'])) {
                continue;
            }

            $isGroup = $unitDetails->pluck('resource_id')->unique()->count() > 1;

            if (! $isGroup) {
                $bookings[] = $this->normalize(
                    prestation: $prestation,
                    detail: $mine,
                    externalId: (string) $prestation['id'],
                    price: (float) ($prestation['total'] ?? 0) ?: null,
                    carriesAmounts: true,
                    groupId: null,
                );

                continue;
            }

            // Group: each member carries its detail subtotal; the carrier
            // (lowest resource id) absorbs the remainder so members sum to
            // the prestation total.
            $carrierResourceId = (string) $unitDetails->pluck('resource_id')
                ->map(fn ($v): string => (string) $v)
                ->sort()
                ->first();

            if ($resourceId === $carrierResourceId) {
                $othersSubtotal = $unitDetails
                    ->filter(fn (array $d): bool => (string) $d['resource_id'] !== $carrierResourceId)
                    ->sum(fn (array $d): float => (float) ($d['subtotal'] ?? 0));
                $price = round((float) ($prestation['total'] ?? 0) - $othersSubtotal, 2);
            } else {
                $price = (float) ($mine['subtotal'] ?? 0);
            }

            $bookings[] = $this->normalize(
                prestation: $prestation,
                detail: $mine,
                externalId: $prestation['id'].'#'.$resourceId,
                price: $price ?: null,
                carriesAmounts: $resourceId === $carrierResourceId,
                groupId: 'multipass-'.$prestation['id'],
            );
        }

        return $bookings;
    }

    /**
     * @param  array<string,mixed>  $prestation
     * @param  array<string,mixed>  $detail  this unit's detail line
     */
    private function normalize(
        array $prestation,
        array $detail,
        string $externalId,
        ?float $price,
        bool $carriesAmounts,
        ?string $groupId,
    ): NormalizedBooking {
        $metadata = array_filter([
            'multipass_id' => $prestation['id'] ?? null,
            'email' => $prestation['contact_email'] ?? null,
            'phone' => $prestation['contact_phone'] ?? null,
            'babies' => $prestation['babies'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        if ($carriesAmounts) {
            $metadata['deposit'] = (float) ($prestation['deposit'] ?? 0) ?: null;
            $metadata['paid'] = (float) ($prestation['paid'] ?? 0) ?: null;
        }

        if ($groupId !== null && (float) ($prestation['total'] ?? 0) > 0) {
            $metadata['group_total'] = (float) $prestation['total'];
        }

        return new NormalizedBooking(
            externalId: $externalId,
            checkIn: (string) ($detail['check_in'] ?? $prestation['check_in']),
            checkOut: (string) ($detail['check_out'] ?? $prestation['check_out']),
            guestName: trim((string) ($prestation['contact_name'] ?? '')) ?: 'Guest',
            status: $this->mapStatus((string) ($prestation['status'] ?? '')),
            email: trim((string) ($prestation['contact_email'] ?? '')) ?: null,
            price: $price,
            adults: $carriesAmounts && isset($prestation['adults']) ? (int) $prestation['adults'] : null,
            children: $carriesAmounts && isset($prestation['children']) ? (int) $prestation['children'] : null,
            channel: trim((string) ($prestation['origin'] ?? '')) ?: 'multipass',
            metadata: array_filter($metadata, fn ($v) => $v !== null),
            claimsOrigin: false,
            groupId: $groupId,
            sourceCreatedAt: trim((string) ($prestation['created_at'] ?? '')) ?: null,
            sourceUpdatedAt: trim((string) ($prestation['updated_at'] ?? '')) ?: null,
        );
    }

    /**
     * Map Multipass prestation statuses (WordPress post statuses) to
     * Bokit's canonical statuses (see Booking::STATUSES).
     */
    private function mapStatus(string $status): string
    {
        return match ($status) {
            'publish' => 'confirmed',
            'canceled', 'cancelled' => 'cancelled',
            'open' => 'option',
            'draft' => 'quote',
            default => 'undefined',
        };
    }

    /**
     * Multipass resource ids configured on the property's units.
     *
     * @return list<string>
     */
    private function configuredResourceIds(Property $property): array
    {
        $ids = [];

        foreach ($property->units as $configuredUnit) {
            foreach ($configuredUnit->options['sources'] ?? [] as $source) {
                if (($source['type'] ?? '') === 'multipass'
                    && ($source['enabled'] ?? true)
                    && ! empty($source['multipass_unit_id'])) {
                    $ids[] = (string) $source['multipass_unit_id'];
                }
            }
        }

        return array_values(array_unique($ids));
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

        $response = $service->get('/wp-json/bokit/v1/bookings/multipass');

        if (! $response->successful()) {
            throw new \RuntimeException("WP API returned HTTP {$response->status()}");
        }

        return $this->apiCache[$property->id] = $response->json() ?? [];
    }
}
