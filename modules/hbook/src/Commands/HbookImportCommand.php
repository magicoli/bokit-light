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
 * ## Group booking model
 *
 * HBook "package" bookings (e.g. "Site entier") block multiple individual units
 * automatically via hb_accom_blocked. The WP endpoint returns:
 *   - Part 1: the direct hb_resa row (is_blocked=false) — carries price/adults/children
 *   - Part 2: one row per blocked unit (is_blocked=true) — same hbook_uid as Part 1
 *
 * We detect groups by finding hbook_uids that have at least one is_blocked row.
 * For each group we create:
 *   - One summary row: uid="hbook:{hbook_uid}", unit_id=NULL, price=total, adults=total
 *   - N member rows: uid="hbook:{hbook_uid}", unit_id=unit.id, price=NULL, adults=NULL
 *
 * Solo bookings (no blocked rows) create a single row with uid and unit_id set.
 *
 * ## Deduplication
 *
 * Solo:   WHERE uid=? AND unit_id=?
 * Group summary:  WHERE uid=? AND unit_id IS NULL
 * Group member:   WHERE uid=? AND unit_id=?
 *
 * Dates can change on existing bookings; the uid is the stable identifier.
 * Dates are pre-converted to the unit's local timezone via shiftAndFormat()
 * before insert/update, bypassing Eloquent mutators (DB::table directly).
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
            $this->line('  Fetched '.count($bookings).' rows from hbook.');

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
     * @param  array<int, array<string, mixed>>  $rows  Raw rows from WP endpoint
     * @return array{int, int, int} [created, updated, skipped]
     */
    private function importBookings(Property $property, array $unitMap, array $rows): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $dryRun = $this->option('dry-run');
        $now = now();

        // Group all rows by hbook_uid.
        $byUid = [];
        foreach ($rows as $row) {
            $uid = $row['hbook_uid'] ?? null;
            if ($uid === null) {
                $this->warn('  Skip: missing hbook_uid in row (hbook id='.($row['id'] ?? '?').')');
                $skipped++;
                continue;
            }
            $byUid[$uid][] = $row;
        }

        foreach ($byUid as $hbookUid => $group) {
            // A group booking has at least one is_blocked=true row.
            $isGroup = collect($group)->contains('is_blocked', true);

            if ($isGroup) {
                [$c, $u, $s] = $this->importGroupBooking($property, $unitMap, $hbookUid, $group, $now, $dryRun);
            } else {
                // Solo booking: single row, guaranteed is_blocked=false.
                $row = $group[0];
                [$c, $u, $s] = $this->importSoloBooking($property, $unitMap, $hbookUid, $row, $now, $dryRun);
            }

            $created += $c;
            $updated += $u;
            $skipped += $s;
        }

        return [$created, $updated, $skipped];
    }

    /**
     * Import a solo booking (one unit, one hbook row, no blocked rows).
     *
     * @param  array<string, mixed>  $row
     * @return array{int, int, int} [created, updated, skipped]
     */
    private function importSoloBooking(
        Property $property,
        array $unitMap,
        string $hbookUid,
        array $row,
        \Illuminate\Support\Carbon $now,
        bool $dryRun,
    ): array {
        $unitId = $row['unit_id'] ?? null;
        $unit = $unitId ? ($unitMap[$unitId] ?? null) : null;

        if (! $unit) {
            $label = $row['unit'] ?? $unitId ?? '?';
            $this->warn("  Skip: no unit mapped to hbook_unit_id='{$unitId}' [{$label}] (hbook uid={$hbookUid})");

            return [0, 0, 1];
        }

        $uid = 'hbook:'.$hbookUid;
        $checkIn = $unit->shiftAndFormat($row['check_in']);
        $checkOut = $unit->shiftAndFormat($row['check_out']);
        $price = (float) ($row['price'] ?? 0);
        $guestName = trim($row['guest_name'] ?? '') ?: 'Guest';
        $status = $this->mapStatus($row['status'] ?? '');
        $adults = isset($row['adults']) ? (int) $row['adults'] : null;
        $children = isset($row['children']) ? (int) $row['children'] : null;

        $existing = Booking::where('uid', $uid)
            ->where('unit_id', $unit->id)
            ->first();

        if ($existing) {
            $changes = $this->buildChanges($existing, $row, $checkIn, $checkOut, $guestName, $status, $price, $adults, $children);

            if (empty($changes)) {
                return [0, 0, 1];
            }

            $this->line("  Update uid={$uid} [{$unit->name}]: ".implode(', ', array_keys($changes)));

            if (! $dryRun) {
                $changes['updated_at'] = $now;
                DB::table('bookings')->where('id', $existing->id)->update($changes);
            }

            return [0, 1, 0];
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
                'adults' => $adults,
                'children' => $children,
                'source_name' => 'hbook',
                'is_manual' => false,
                'metadata' => json_encode($this->buildMeta($row)),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return [1, 0, 0];
    }

    /**
     * Import a group booking: one summary row (unit_id=NULL) + N member rows.
     *
     * The WP endpoint returns the direct hb_resa row (is_blocked=false) and one
     * row per blocked unit (is_blocked=true). Price/adults/children are on the
     * parent row (Part 1) and should not be double-counted.
     *
     * Summary row:  uid="hbook:{hbook_uid}", unit_id=NULL, price/adults/children from parent
     * Member rows:  uid="hbook:{hbook_uid}", unit_id=unit.id, price=NULL, adults=NULL
     *
     * @param  array<int, array<string, mixed>>  $group  All rows sharing this hbook_uid
     * @return array{int, int, int} [created, updated, skipped]
     */
    private function importGroupBooking(
        Property $property,
        array $unitMap,
        string $hbookUid,
        array $group,
        \Illuminate\Support\Carbon $now,
        bool $dryRun,
    ): array {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        $uid = 'hbook:'.$hbookUid;

        // Separate parent row (is_blocked=false) from member rows (is_blocked=true).
        $parentRows = array_values(array_filter($group, fn ($r) => ! ($r['is_blocked'] ?? false)));
        $memberRows = array_values(array_filter($group, fn ($r) => (bool) ($r['is_blocked'] ?? false)));

        $parent = $parentRows[0] ?? $group[0]; // fallback to first row if all are blocked

        $guestName = trim($parent['guest_name'] ?? '') ?: 'Guest';
        $status = $this->mapStatus($parent['status'] ?? '');
        $price = (float) ($parent['price'] ?? 0);
        $adults = isset($parent['adults']) ? (int) $parent['adults'] : null;
        $children = isset($parent['children']) ? (int) $parent['children'] : null;
        $meta = $this->buildMeta($parent);

        // Determine check-in/check-out for the summary row from the parent booking dates.
        // We need a timezone reference — use the first mapped unit, or fall back to UTC.
        $referenceUnit = $this->findAnyMappedUnit($memberRows, $unitMap)
            ?? $this->findAnyMappedUnit($parentRows, $unitMap);

        if ($referenceUnit) {
            $summaryCheckIn = $referenceUnit->shiftAndFormat($parent['check_in']);
            $summaryCheckOut = $referenceUnit->shiftAndFormat($parent['check_out']);
        } else {
            $summaryCheckIn = $parent['check_in'].'T00:00:00+00:00';
            $summaryCheckOut = $parent['check_out'].'T00:00:00+00:00';
        }

        // 1. Summary row (unit_id = NULL)
        $summary = Booking::where('uid', $uid)->whereNull('unit_id')->first();

        if ($summary) {
            $changes = [];

            if ($summary->check_in->toDateString() !== $parent['check_in']) {
                $changes['check_in'] = $summaryCheckIn;
            }
            if ($summary->check_out->toDateString() !== $parent['check_out']) {
                $changes['check_out'] = $summaryCheckOut;
            }
            if ($summary->guest_name !== $guestName) {
                $changes['guest_name'] = $guestName;
            }
            if ($summary->status !== $status) {
                $changes['status'] = $status;
            }
            if ($price > 0 && (float) $summary->getRawOriginal('price') !== $price) {
                $changes['price'] = $price;
            }
            if ($adults !== null && $summary->adults !== $adults) {
                $changes['adults'] = $adults;
            }
            if ($children !== null && $summary->children !== $children) {
                $changes['children'] = $children;
            }
            $currentMeta = $summary->metadata ?? [];
            if (array_diff_assoc($meta, array_intersect_key($currentMeta, $meta))) {
                $changes['metadata'] = json_encode(array_merge($currentMeta, $meta));
            }

            if (empty($changes)) {
                $skipped++;
            } else {
                $this->line("  Update group summary uid={$uid}: ".implode(', ', array_keys($changes)));
                if (! $dryRun) {
                    $changes['updated_at'] = $now;
                    DB::table('bookings')->where('id', $summary->id)->update($changes);
                }
                $updated++;
            }
        } else {
            $this->line("  Create group summary uid={$uid}: {$parent['check_in']}→{$parent['check_out']} — {$guestName} ({$price}€, {$adults}p)");
            if (! $dryRun) {
                DB::table('bookings')->insert([
                    'unit_id' => null,
                    'property_id' => $property->id,
                    'uid' => $uid,
                    'check_in' => $summaryCheckIn,
                    'check_out' => $summaryCheckOut,
                    'guest_name' => $guestName,
                    'status' => $status,
                    'price' => $price ?: null,
                    'adults' => $adults,
                    'children' => $children,
                    'source_name' => 'hbook',
                    'is_manual' => false,
                    'metadata' => json_encode($meta),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $created++;
        }

        // 2. Member rows (one per blocked unit)
        foreach ($memberRows as $row) {
            $unitId = $row['unit_id'] ?? null;
            $unit = $unitId ? ($unitMap[$unitId] ?? null) : null;

            if (! $unit) {
                $label = $row['unit'] ?? $unitId ?? '?';
                $this->warn("  Skip group member: no unit mapped to '{$unitId}' [{$label}] (uid={$uid})");
                $skipped++;
                continue;
            }

            $checkIn = $unit->shiftAndFormat($row['check_in']);
            $checkOut = $unit->shiftAndFormat($row['check_out']);

            $member = Booking::where('uid', $uid)->where('unit_id', $unit->id)->first();

            if ($member) {
                $changes = [];

                if ($member->check_in->toDateString() !== $row['check_in']) {
                    $changes['check_in'] = $checkIn;
                }
                if ($member->check_out->toDateString() !== $row['check_out']) {
                    $changes['check_out'] = $checkOut;
                }
                if ($member->guest_name !== $guestName) {
                    $changes['guest_name'] = $guestName;
                }
                if ($member->status !== $status) {
                    $changes['status'] = $status;
                }

                if (empty($changes)) {
                    $skipped++;
                } else {
                    $this->line("  Update group member uid={$uid} [{$unit->name}]: ".implode(', ', array_keys($changes)));
                    if (! $dryRun) {
                        $changes['updated_at'] = $now;
                        DB::table('bookings')->where('id', $member->id)->update($changes);
                    }
                    $updated++;
                }
            } else {
                $this->line("  Create group member uid={$uid} [{$unit->name}] {$row['check_in']}→{$row['check_out']}");
                if (! $dryRun) {
                    DB::table('bookings')->insert([
                        'unit_id' => $unit->id,
                        'property_id' => $property->id,
                        'uid' => $uid,
                        'check_in' => $checkIn,
                        'check_out' => $checkOut,
                        'guest_name' => $guestName,
                        'status' => $status,
                        'price' => null,   // price/adults/children on summary row only
                        'adults' => null,
                        'children' => null,
                        'source_name' => 'hbook',
                        'is_manual' => false,
                        'metadata' => json_encode(['hbook_id' => $row['id'], 'is_group_member' => true]),
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
     * Build a map of changes for an existing booking.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function buildChanges(
        Booking $existing,
        array $row,
        string $checkIn,
        string $checkOut,
        string $guestName,
        string $status,
        float $price,
        ?int $adults,
        ?int $children,
    ): array {
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
        if ($adults !== null && $existing->adults !== $adults) {
            $changes['adults'] = $adults;
        }
        if ($children !== null && $existing->children !== $children) {
            $changes['children'] = $children;
        }

        $newMeta = $this->buildMeta($row);
        $currentMeta = $existing->metadata ?? [];

        if (array_diff_assoc($newMeta, array_intersect_key($currentMeta, $newMeta))) {
            $changes['metadata'] = json_encode(array_merge($currentMeta, $newMeta));
        }

        return $changes;
    }

    /**
     * Find the first Unit that is mapped in the given rows, if any.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, Unit>  $unitMap
     */
    private function findAnyMappedUnit(array $rows, array $unitMap): ?Unit
    {
        foreach ($rows as $row) {
            $unitId = $row['unit_id'] ?? null;
            if ($unitId && isset($unitMap[$unitId])) {
                return $unitMap[$unitId];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function buildMeta(array $row): array
    {
        return array_filter([
            'hbook_id'    => $row['id'] ?? null,
            'hbook_uid'   => $row['hbook_uid'] ?? null,
            'email'       => $row['guest_email'] ?? null,
            'phone'       => $row['guest_phone'] ?? null,
            'deposit'     => $row['deposit'] ?? null,
            'paid'        => $row['paid'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
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
