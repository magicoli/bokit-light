<?php

namespace Modules\Hbook\Commands;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\WpConnector\Services\WpConnectorService;

/**
 * Import / sync hbook bookings from WordPress into Bokit.
 *
 * Only imports "website origin" bookings (direct reservations with real prices).
 * OTA bookings (Beds24/Lodgify-synced) are excluded by the WP endpoint.
 *
 * Deduplication: uid = "hbook-{hbook_id}" only — no date fallback.
 * Dates can change on an existing hbook booking; the uid is the stable identifier.
 *
 * Dates are pre-converted to the unit's local timezone via shiftAndFormat()
 * before insert/update, bypassing the Eloquent mutators (DB::table directly).
 * This ensures correct midnight-local storage regardless of server timezone.
 *
 * Safe to run repeatedly as a recurring sync.
 */
class HbookImportCommand extends Command
{
    protected $signature = 'hbook:import
                            {--property= : Property slug or ID (all configured properties if omitted)}
                            {--from=     : Import bookings from date (YYYY-MM-DD)}
                            {--to=       : Import bookings up to date (YYYY-MM-DD)}
                            {--dry-run   : Preview without saving}';

    protected $description = 'Import / sync hbook (direct website) bookings from WordPress';

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

            // Build map: hbook_unit_id (e.g. "3539_1") → Unit
            // Reads from options.sources where type=hbook and enabled=true.
            /** @var array<string, Unit> $unitMap  hbook_unit_id → Unit */
            $unitMap = $this->buildUnitMap($property);

            if (empty($unitMap)) {
                $this->warn('  Skipping — no units with hbook source configured.');

                continue;
            }

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
        $now = now();

        foreach ($bookings as $row) {
            $unitId = $row['unit_id'] ?? null;
            $unit = $unitId ? ($unitMap[$unitId] ?? null) : null;

            if (! $unit) {
                $label = $row['unit'] ?? $unitId ?? '?';
                $this->warn("  Skip: no unit mapped to hbook_unit_id='{$unitId}' [{$label}] (hbook id={$row['id']})");
                $skipped++;

                continue;
            }

            $uid = 'hbook-'.$row['id'];
            // Pre-convert to unit local timezone: WP sends plain Y-m-d (local date).
            // shiftAndFormat('2025-01-15') → '2025-01-15T00:00:00-04:00' for Martinique.
            // We insert directly via DB::table to bypass the Eloquent mutator.
            $checkIn = $unit->shiftAndFormat($row['check_in']);
            $checkOut = $unit->shiftAndFormat($row['check_out']);
            $price = (float) ($row['price'] ?? 0);
            $guestName = trim($row['guest_name'] ?? '') ?: 'Guest';
            $status = $this->mapStatus($row['status'] ?? '');

            $existing = Booking::where('uid', $uid)->first();

            // Fallback: if no uid match, look for a booking on the same date/unit
            // that was imported without a hbook uid (e.g. from iCal or Beds24).
            // We assign the uid so future syncs find it reliably even if dates change.
            if (! $existing) {
                $existing = Booking::where('unit_id', $unit->id)
                    ->where('check_in', 'LIKE', $row['check_in'].'%')
                    ->whereNull('uid')
                    ->orWhere(fn ($q) => $q
                        ->where('unit_id', $unit->id)
                        ->where('check_in', 'LIKE', $row['check_in'].'%')
                        ->where('uid', 'NOT LIKE', 'hbook-%')
                    )
                    ->first();

                if ($existing && ! $dryRun) {
                    DB::table('bookings')->where('id', $existing->id)->update(['uid' => $uid]);
                    $existing->uid = $uid;
                }
            }

            if ($existing) {
                // hbook is the source of truth: sync dates, name, status, price.
                $changes = [];

                if ($existing->check_in->toDateString() !== $row['check_in']) {
                    $changes['check_in'] = $checkIn;
                }

                if ($existing->check_out->toDateString() !== $row['check_out']) {
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
                    $changes['metadata'] = json_encode(array_merge($currentMeta, $newMeta));
                }

                if (empty($changes)) {
                    $skipped++;

                    continue;
                }

                $this->line("  Update uid={$uid} [{$unit->name}]: ".implode(', ', array_keys($changes)));

                if (! $dryRun) {
                    $changes['updated_at'] = $now;
                    DB::table('bookings')->where('id', $existing->id)->update($changes);
                }

                $updated++;

                continue;
            }

            $this->line("  Create: [{$unit->name}] {$row['check_in']}→{$row['check_out']} — {$guestName} ({$price}€)");

            if (! $dryRun) {
                DB::table('bookings')->insert([
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
                    'metadata' => json_encode($this->buildMeta($row)),
                    'created_at' => $now,
                    'updated_at' => $now,
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

    /**
     * Build a map of hbook_unit_id → Unit from options.sources for all units of a property.
     *
     * @return array<string, Unit>
     */
    private function buildUnitMap(Property $property): array
    {
        $map = [];

        foreach ($property->units as $unit) {
            foreach ($unit->options['sources'] ?? [] as $source) {
                if (($source['type'] ?? '') === 'hbook'
                    && ($source['enabled'] ?? true)
                    && ! empty($source['hbook_unit_id'])
                ) {
                    $map[$source['hbook_unit_id']] = $unit;
                }
            }
        }

        return $map;
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
