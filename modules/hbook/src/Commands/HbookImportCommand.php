<?php

namespace Modules\Hbook\Commands;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Console\Command;
use Modules\WpConnector\Services\WpConnectorService;

/**
 * Import hbook bookings from WordPress into Bokit.
 *
 * Only imports "website origin" bookings (direct reservations with real prices).
 * OTA bookings (Beds24/Lodgify-synced) are excluded by the WP endpoint.
 *
 * Deduplication:
 *   - Sole key: uid = "hbook-{hbook_id}"
 *   - No date fallback: hbook booking dates can change on modification.
 *
 * Timezone:
 *   - Dates from WP are plain Y-m-d strings (local date, no tz info).
 *   - The Booking model mutators (checkIn/checkOut) apply the unit timezone
 *     via shiftAndFormat(), so we pass raw Y-m-d and let the model handle it.
 *   - unit_id must be set before check_in/check_out in create() for the
 *     mutator to resolve the unit's timezone.
 */
class HbookImportCommand extends Command
{
    protected $signature = 'hbook:import
                            {--property= : Property slug or ID (all configured properties if omitted)}
                            {--from=     : Import bookings from date (YYYY-MM-DD)}
                            {--to=       : Import bookings up to date (YYYY-MM-DD)}
                            {--dry-run   : Preview without saving}';

    protected $description = 'Import hbook (direct website) bookings from WordPress';

    public function handle(): int
    {
        $properties = $this->resolveProperties();

        if ($properties->isEmpty()) {
            $this->error('No properties found with WordPress connection configured.');

            return self::FAILURE;
        }

        $totalCreated = 0;
        $totalUpdated = 0;
        $totalSkipped = 0;

        foreach ($properties as $property) {
            $this->line("\n<info>Property:</info> {$property->name}");

            $service = new WpConnectorService($property);

            if (! $service->isConfigured()) {
                $this->warn('  Skipping — WordPress connection not configured.');

                continue;
            }

            $params = array_filter([
                'from' => $this->option('from'),
                'to' => $this->option('to'),
            ]);

            $response = $service->get('/wp-json/bokit/v1/bookings/hbook', $params);

            if (! $response->successful()) {
                $this->error("  HTTP {$response->status()}: {$response->body()}");

                continue;
            }

            $bookings = $response->json();
            $this->line('  Fetched '.count($bookings).' bookings from hbook.');

            /** @var array<string, Unit> $unitMap  name (lowercase) → Unit */
            $unitMap = $property->units->keyBy(fn (Unit $u) => strtolower($u->name))->all();

            [$created, $updated, $skipped] = $this->importBookings($property, $unitMap, $bookings);

            $this->line("  Result: created={$created}, updated={$updated}, skipped={$skipped}");

            $totalCreated += $created;
            $totalUpdated += $updated;
            $totalSkipped += $skipped;
        }

        $suffix = $this->option('dry-run') ? ' [DRY RUN — nothing was saved]' : '';
        $this->newLine();
        $this->info("Total: created={$totalCreated}, updated={$totalUpdated}, skipped={$totalSkipped}{$suffix}");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, Unit>  $unitMap
     * @param  array<int, array<string, mixed>>  $bookings
     * @return array{int, int, int} [created, updated, skipped]
     */
    private function importBookings(Property $property, array $unitMap, array $bookings): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $dryRun = $this->option('dry-run');

        foreach ($bookings as $row) {
            $unitName = strtolower($row['unit'] ?? '');
            $unit = $unitMap[$unitName] ?? null;

            if (! $unit) {
                $this->warn("  Skip: unknown unit '{$row['unit']}' (hbook id={$row['id']})");
                $skipped++;

                continue;
            }

            $uid = "hbook-{$row['id']}";
            $checkIn = $row['check_in'];
            $checkOut = $row['check_out'];
            $price = (float) ($row['price'] ?? 0);
            $guestName = trim($row['guest_name'] ?? '') ?: 'Guest';
            $status = $this->mapStatus($row['status'] ?? '');

            $existing = Booking::where('uid', $uid)->first();

            if ($existing) {
                $changes = $this->buildChanges($existing, $checkIn, $checkOut, $guestName, $status, $price, $row);

                if (empty($changes)) {
                    $skipped++;

                    continue;
                }

                $this->line("  Update: [{$unit->name}] {$checkIn} uid={$uid} — ".implode(', ', array_keys($changes)));

                if (! $dryRun) {
                    $existing->update($changes);
                }

                $updated++;

                continue;
            }

            $this->line("  Create: [{$unit->name}] {$checkIn}→{$checkOut} — {$guestName} ({$price}€)");

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
                    'source_name' => 'hbook',
                    'is_manual' => false,
                    'metadata' => $this->buildMeta($row),
                ]);
            }

            $created++;
        }

        return [$created, $updated, $skipped];
    }

    /**
     * Compute fields that need updating for an existing booking.
     *
     * hbook is the source of truth: sync dates (can change on modification),
     * guest name, status, price, and metadata.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function buildChanges(
        Booking $existing,
        string $checkIn,
        string $checkOut,
        string $guestName,
        string $status,
        float $price,
        array $row,
    ): array {
        $changes = [];

        if ($existing->check_in->format('Y-m-d') !== $checkIn) {
            $changes['check_in'] = $checkIn;
        }

        if ($existing->check_out->format('Y-m-d') !== $checkOut) {
            $changes['check_out'] = $checkOut;
        }

        if ($existing->guest_name !== $guestName) {
            $changes['guest_name'] = $guestName;
        }

        if ($existing->status !== $status) {
            $changes['status'] = $status;
        }

        if ($price > 0 && (float) $existing->getRawOriginal('price') !== $price) {
            $changes['price'] = $price;
        }

        $newMeta = $this->buildMeta($row);
        $currentMeta = $existing->metadata ?? [];

        if (array_diff_assoc($newMeta, array_intersect_key($currentMeta, $newMeta))) {
            $changes['metadata'] = array_merge($currentMeta, $newMeta);
        }

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function buildMeta(array $row): array
    {
        return array_filter([
            'hbook_id' => $row['id'],
            'email' => $row['guest_email'] ?? null,
            'phone' => $row['guest_phone'] ?? null,
            'origin' => $row['origin'] ?? null,
            'deposit' => $row['deposit'] ?? null,
            'paid' => $row['paid'] ?? null,
        ]);
    }

    private function mapStatus(string $status): string
    {
        return match ($status) {
            'confirmed' => 'confirmed',
            'pending' => 'pending',
            'cancelled' => 'cancelled',
            default => 'confirmed',
        };
    }

    private function resolveProperties()
    {
        $query = Property::with('units');

        if ($slug = $this->option('property')) {
            $query->where('slug', $slug)->orWhere('id', (int) $slug);
        }

        return $query->get()->filter(
            fn (Property $p) => ! empty($p->options['wp_url']),
        );
    }
}
