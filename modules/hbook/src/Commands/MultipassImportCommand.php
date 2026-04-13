<?php

namespace Modules\Hbook\Commands;

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

            /** @var array<string, Unit> $unitMap  name (lowercase) → Unit */
            $unitMap = $property->units->keyBy(fn (Unit $u) => strtolower($u->name))->all();

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
     * @param  array<string, Unit>  $unitMap
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
            if ($prestation['status'] === 'canceled') {
                $skipped++;

                continue;
            }

            $units = $prestation['units'] ?? [];

            if (empty($units)) {
                $this->warn("  Skip: prestation {$prestation['id']} has no unit details.");
                $skipped++;

                continue;
            }

            foreach ($units as $detail) {
                $unitName = strtolower($detail['unit'] ?? '');
                $unit = $unitMap[$unitName] ?? null;

                if (! $unit) {
                    $this->warn("  Skip: unknown unit '{$detail['unit']}' (prestation={$prestation['id']})");
                    $skipped++;

                    continue;
                }

                $uid = 'multipass-'.$detail['detail_id'];
                $rawIn = $detail['check_in'];
                $rawOut = $detail['check_out'];
                $price = (float) ($detail['subtotal'] ?? 0);

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
                    if (! $existing->price && $price > 0) {
                        $this->line("  Update: [{$unit->name}] {$rawIn} — add price {$price}€");

                        if (! $dryRun) {
                            DB::table('bookings')->where('id', $existing->id)->update([
                                'uid' => $existing->uid ?? $uid,
                                'price' => $price,
                                'metadata' => json_encode(array_merge(
                                    $existing->metadata ?? [],
                                    $this->buildMeta($prestation, $detail),
                                )),
                                'updated_at' => $now,
                            ]);
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
                        'status' => $this->mapStatus($prestation['status'] ?? ''),
                        'price' => $price ?: null,
                        'source_name' => 'multipass',
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
            'total' => $prestation['total'] ?? null,
            'deposit' => $prestation['deposit'] ?? null,
            'paid' => $prestation['paid'] ?? null,
        ]);
    }

    private function mapStatus(string $status): string
    {
        return match ($status) {
            'publish', 'private' => 'confirmed',
            'open', 'draft' => 'pending',
            default => 'pending',
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
