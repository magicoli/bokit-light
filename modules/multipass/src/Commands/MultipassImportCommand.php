<?php

namespace Modules\Multipass\Commands;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\WpConnector\Services\WpConnectorService;

/**
 * One-shot import of multipass (historical direct bookings) from WordPress.
 *
 * Multipass is the predecessor to Bokit (project abandoned). Each prestation
 * may cover multiple gîtes; we create one Bokit booking per unit detail.
 *
 * Deduplication:
 *   - Primary key: uid = "multipass-{detail_id}"
 *   - Fallback: unit_id + check_in date (LIKE match)
 */
class MultipassImportCommand extends Command
{
    protected $signature = 'multipass:import
                            {--property= : Property slug or ID (all configured properties if omitted)}
                            {--from=     : Import prestations from date (YYYY-MM-DD)}
                            {--to=       : Import prestations up to date (YYYY-MM-DD)}
                            {--dry-run   : Preview without saving}';

    protected $description = 'One-shot import of historical multipass bookings from WordPress';

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

            $response = $service->get('/wp-json/bokit/v1/bookings/multipass', $params);

            if (! $response->successful()) {
                $this->error("  HTTP {$response->status()}: {$response->body()}");

                continue;
            }

            $prestations = $response->json();
            $this->line('  Fetched '.count($prestations).' prestations from multipass.');

            /** @var array<int, Unit> $unitMap  multipass_unit_id → Unit */
            $unitMap = $this->buildUnitMap($property);

            if (empty($unitMap)) {
                $this->warn('  Skipping — no units with multipass source configured.');
                continue;
            }

            [$created, $updated, $skipped] = $this->importPrestations($property, $unitMap, $prestations);

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
     * @param  array<int, Unit>  $unitMap  multipass_unit_id → Unit
     * @param  array<int, array<string, mixed>>  $prestations
     * @return array{int, int, int} [created, updated, skipped]
     */
    private function importPrestations(Property $property, array $unitMap, array $prestations): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $dryRun = $this->option('dry-run');
        $now = now();

        foreach ($prestations as $prestation) {
            // Only import confirmed prestations — skip everything else.
            // Multipass statuses: publish/private = confirmed, everything else = not ready.
            if (! in_array($prestation['status'], ['publish', 'private'], true)) {
                $skipped++;

                continue;
            }

            $units = $prestation['units'] ?? [];

            if (empty($units)) {
                $this->warn("  Skip: prestation {$prestation['id']} has no unit details.");
                $skipped++;

                continue;
            }

            // Count confirmed units to pro-rate prestation.total when no subtotals.
            $confirmedUnits = array_filter($units, fn ($d) => in_array(
                $d['status'] ?? 'publish', ['publish', 'private'], true
            ));
            $nbConfirmed = max(1, count($confirmedUnits));
            $prestationTotal = (float) ($prestation['total'] ?? 0);

            foreach ($units as $detail) {
                // 'status' added in plugin v0.3.0 — absent means old endpoint, treat as confirmed.
                $detailStatus = $detail['status'] ?? 'publish';
                $detailConfirmed = in_array($detailStatus, ['publish', 'private'], true);
                $uid = 'multipass-'.$detail['detail_id'];

                // If not confirmed, soft-delete if already in DB, then skip.
                if (! $detailConfirmed) {
                    $toDelete = Booking::where('uid', $uid)->first();
                    if ($toDelete) {
                        $this->line("  Delete: uid={$uid} (detail status={$detailStatus})");
                        if (! $dryRun) {
                            $toDelete->delete();
                        }
                        $updated++;
                    } else {
                        $skipped++;
                    }

                    continue;
                }

                // Match unit by resource_id (multipass_unit_id in Bokit config)
                $resourceId = (int) ($detail['resource_id'] ?? 0);
                
                // Skip if this detail is not a unit reservation (no resource_id or not configured)
                if ($resourceId === 0 || !isset($unitMap[$resourceId])) {
                    $this->line("  Skip detail: not a unit reservation (resource_id={$resourceId})");
                    $skipped++;
                    continue;
                }
                
                $unit = $unitMap[$resourceId];

                $rawIn = $detail['check_in'];
                $rawOut = $detail['check_out'];

                // Price priority:
                // 1. detail.subtotal (per-unit price, set when units have different rates)
                // 2. prestation.total / nb_confirmed_units (equal split of contract total)
                $subtotal = (float) ($detail['subtotal'] ?? 0);
                $price = $subtotal > 0 ? $subtotal : ($prestationTotal > 0 ? round($prestationTotal / $nbConfirmed, 2) : 0);

                if (! $rawIn || ! $rawOut) {
                    $this->warn("  Skip: missing dates for detail {$detail['detail_id']}");
                    $skipped++;

                    continue;
                }

                // Pre-convert to unit local timezone (DB::table bypasses mutators).
                $checkIn = $unit->shiftAndFormat($rawIn);
                $checkOut = $unit->shiftAndFormat($rawOut);

                // Primary dedup: uid
                $existing = Booking::where('uid', $uid)->first();

                // Fallback dedup for one-shot import: unit + check_in date
                // (multipass has no stable ID that we could have stored previously)
                if (! $existing) {
                    $existing = Booking::where('unit_id', $unit->id)
                        ->where('check_in', 'LIKE', $rawIn.'%')
                        ->first();
                }

                if ($existing) {
                    $changes = [];

                    // Never overwrite a beds24-* uid — beds24:sync is authoritative
                    // and must win the final uid assignment.
                    if ($existing->uid !== $uid && ! str_starts_with($existing->uid, 'beds24-')) {
                        $changes['uid'] = $uid;
                    }

                    if ($existing->status !== 'confirmed') {
                        $changes['status'] = 'confirmed';
                    }

                    if (! $existing->price && $price > 0) {
                        $changes['price'] = $price;
                    }

                    $adults = $prestation['adults'] ?? null;
                    $children = isset($prestation['children']) || isset($prestation['babies'])
                        ? ($prestation['children'] ?? 0) + ($prestation['babies'] ?? 0)
                        : null;

                    if ($adults !== null && ! $existing->getRawOriginal('adults')) {
                        $changes['adults'] = $adults;
                    }

                    if ($children !== null && ! $existing->getRawOriginal('children')) {
                        $changes['children'] = $children;
                    }

                    $newMeta = $this->buildMeta($prestation, $detail);
                    $currentMeta = $existing->metadata ?? [];

                    if (array_diff_assoc($newMeta, array_intersect_key($currentMeta, $newMeta))) {
                        $changes['metadata'] = json_encode(array_merge($currentMeta, $newMeta));
                    }

                    if (! empty($changes)) {
                        $changeKeys = implode(', ', array_keys($changes));
                        $this->line("  Update: [{$unit->name}] {$rawIn} — {$changeKeys}");

                        if (! $dryRun) {
                            $changes['updated_at'] = $now;
                            DB::table('bookings')->where('id', $existing->id)->update($changes);
                        }

                        $updated++;
                    } else {
                        $skipped++;
                    }

                    continue;
                }

                $guestName = trim($prestation['contact_name'] ?? '') ?: 'Guest';
                $this->line("  Create: [{$unit->name}] {$rawIn}→{$rawOut} — {$guestName} ({$price}€)");

                if (! $dryRun) {
                    DB::table('bookings')->insert([
                        'unit_id' => $unit->id,
                        'property_id' => $property->id,
                        'uid' => $uid,
                        'check_in' => $checkIn,
                        'check_out' => $checkOut,
                        'guest_name' => $guestName,
                        'status' => 'confirmed',
                        'price' => $price ?: null,
                        'source_name' => 'multipass',
                        'adults' => $prestation['adults'] ?? null,
                        'children' => ($prestation['children'] ?? 0) + ($prestation['babies'] ?? 0),
                        'is_manual' => false,
                        'metadata' => json_encode($this->buildMeta($prestation, $detail)),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $created++;
            }
        }

        return [$created, $updated, $skipped];
    }

    /**
     * @param  array<string, mixed>  $prestation
     * @param  array<string, mixed>  $detail
     * @return array<string, mixed>
     */
    private function buildMeta(array $prestation, array $detail): array
    {
        return array_filter([
            'multipass_prestation_id' => $prestation['id'],
            'multipass_detail_id' => $detail['detail_id'],
            'email' => $prestation['contact_email'] ?? null,
            'phone' => $prestation['contact_phone'] ?? null,
            'origin' => $prestation['origin'] ?? null,
            'adults' => $prestation['adults'] ?? null,
            'children' => $prestation['children'] ?? null,
            'babies' => $prestation['babies'] ?? null,
            'total' => $prestation['total'] ?? null,
            'deposit' => $prestation['deposit'] ?? null,
            'paid' => $prestation['paid'] ?? null,
        ], fn ($v) => $v !== null);
    }

    /**
     * Build a map of multipass_unit_id → Unit from options.sources for all units of a property.
     *
     * @return array<int, Unit>
     */
    private function buildUnitMap(Property $property): array
    {
        $map = [];

        foreach ($property->units as $unit) {
            foreach ($unit->options['sources'] ?? [] as $source) {
                if (($source['type'] ?? '') === 'multipass'
                    && ($source['enabled'] ?? true)
                    && ! empty($source['multipass_unit_id'])
                ) {
                    $map[(int) $source['multipass_unit_id']] = $unit;
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