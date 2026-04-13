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
 *   - Primary key: uid = "hbook-{hbook_id}"
 *   - Fallback: unit_id + check_in date (LIKE match to handle ISO timezone suffix)
 *
 * Run on dev and live server: reads wp_url/wp_user/wp_app_password from property options.
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

            // Primary dedup: uid
            $existing = Booking::where('uid', $uid)->first();

            // Fallback dedup: unit + check_in (LIKE handles ISO timezone suffix in stored value)
            if (! $existing) {
                $existing = Booking::where('unit_id', $unit->id)
                    ->where('check_in', 'LIKE', $checkIn.'%')
                    ->first();
            }

            if ($existing) {
                // Only update if the booking has no price and hbook has one
                if (! $existing->price && $price > 0) {
                    $this->line("  Update: [{$unit->name}] {$checkIn} — add price {$price}€");

                    if (! $dryRun) {
                        $existing->update([
                            'uid' => $existing->uid ?? $uid,
                            'price' => $price,
                            'metadata' => array_merge($existing->metadata ?? [], $this->buildMeta($row)),
                        ]);
                    }

                    $updated++;
                } else {
                    $skipped++;
                }

                continue;
            }

            $guestName = trim($row['guest_name'] ?? '') ?: 'Guest';
            $this->line("  Create: [{$unit->name}] {$checkIn}→{$checkOut} — {$guestName} ({$price}€)");

            if (! $dryRun) {
                Booking::create([
                    'unit_id' => $unit->id,
                    'property_id' => $property->id,
                    'uid' => $uid,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'guest_name' => $guestName,
                    'status' => $this->mapStatus($row['status'] ?? ''),
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
