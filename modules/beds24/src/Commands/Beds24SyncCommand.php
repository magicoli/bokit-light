<?php

namespace Modules\Beds24\Commands;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Beds24\Services\Beds24ApiService;

/**
 * Sync Beds24 bookings into Bokit.
 *
 * Designed to run repeatedly (cron or on-demand), not just once.
 *
 * Deduplication:
 *   - Sole key: uid = "beds24-{bookId}"
 *   - No date fallback: booking dates can change on modification.
 *
 * Room mapping:
 *   - Each Unit stores sources in options.sources (ordered array, new format).
 *     Legacy fallback: options.beds24_room_id (single value, old format).
 *   - Bookings with an unmapped roomId are skipped with a warning.
 *
 * Timezone:
 *   - Beds24 returns dates as local Y-m-d strings (firstNight / lastNight).
 *   - check_in  = firstNight
 *   - check_out = lastNight + 1 day  (lastNight is the last night of the stay)
 *   - The Booking model mutators apply the unit timezone via shiftAndFormat(),
 *     so we pass raw Y-m-d strings and let the model handle the shift.
 *   - unit_id is always set before check_in/check_out in create() so the
 *     mutator can resolve the unit's timezone.
 */
class Beds24SyncCommand extends Command
{
    protected $signature = 'beds24:sync
                            {--property=      : Property slug or ID (all configured properties if omitted)}
                            {--from=          : Arrival from date (YYYY-MM-DD). REQUIRED for historical data — Beds24 API only returns future bookings by default.}
                            {--to=            : Arrival to date (YYYY-MM-DD)}
                            {--modified-since= : Only fetch bookings modified since this date (YYYY-MM-DD)}
                            {--dry-run        : Preview without saving}';

    protected $description = 'Sync Beds24 bookings into Bokit (recurring)';

    public function handle(): int
    {
        if (! $this->option('from') && ! $this->option('modified-since')) {
            $this->warn('No --from or --modified-since provided. Beds24 API will only return future bookings.');
            $this->warn('Use --from=YYYY-MM-DD to include historical bookings (e.g. --from=2025-01-01).');
        }

        $properties = $this->resolveProperties();

        if ($properties->isEmpty()) {
            $this->error('No properties found with Beds24 configured.');

            return self::FAILURE;
        }

        $totalCreated = 0;
        $totalUpdated = 0;
        $totalSkipped = 0;

        foreach ($properties as $property) {
            $this->line("\n<info>Property:</info> {$property->name}");

            $service = new Beds24ApiService($property);

            if (! $service->isConfigured()) {
                $this->warn('  Skipping — Beds24 not configured.');

                continue;
            }

            $params = $this->buildApiParams();
            $rows = $service->getBookings($params);

            if (empty($rows)) {
                $this->line('  No bookings returned.');

                continue;
            }

            $this->line('  Fetched '.count($rows).' bookings from Beds24.');

            /** @var array<int, Unit> $unitMap  beds24_room_id (int) → Unit */
            $unitMap = $property->units
                ->flatMap(fn (Unit $u) => collect($this->resolveBedsRoomIds($u))
                    ->mapWithKeys(fn (int $roomId) => [$roomId => $u])
                )
                ->all();

            if (empty($unitMap)) {
                $this->warn('  Skipping — no units have a beds24 source configured (options.sources or options.beds24_room_id).');

                continue;
            }

            [$created, $updated, $skipped] = $this->syncBookings($property, $unitMap, $rows);

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
     * @param  array<int, Unit>  $unitMap  beds24_room_id → Unit
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{int, int, int} [created, updated, skipped]
     */
    private function syncBookings(Property $property, array $unitMap, array $rows): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $dryRun = $this->option('dry-run');

        foreach ($rows as $row) {
            $roomId = (int) ($row['roomId'] ?? 0);
            $unit = $unitMap[$roomId] ?? null;

            if (! $unit) {
                $this->warn("  Skip: unmapped roomId={$roomId} (bookId={$row['bookId']})");
                $skipped++;

                continue;
            }

            $uid = "beds24-{$row['bookId']}";
            $checkIn = $row['firstNight'] ?? null;
            $checkOut = isset($row['lastNight'])
                ? Carbon::parse($row['lastNight'])->addDay()->format('Y-m-d')
                : null;

            if (! $checkIn || ! $checkOut) {
                $this->warn("  Skip: missing dates for bookId={$row['bookId']}");
                $skipped++;

                continue;
            }

            // Skip Beds24 availability blocks (status 4=block, 5=owner block).
            // These are not real guest bookings — they block the calendar for other reasons.
            $rawStatus = (string) ($row['status'] ?? '2');
            if (in_array($rawStatus, ['4', '5'], true)) {
                $this->line("  Skip: availability block [{$unit->name}] {$checkIn} (bookId={$row['bookId']}, status={$rawStatus})");
                $skipped++;

                continue;
            }

            $guestName = trim(($row['firstName'] ?? '').' '.($row['lastName'] ?? '')) ?: 'Guest';
            $status = $this->mapStatus($rawStatus);
            $price = (float) ($row['price'] ?? 0);
            $commission = (float) ($row['commission'] ?? 0);
            $adults = isset($row['numAdult']) ? (int) $row['numAdult'] : null;
            $children = isset($row['numChild']) ? (int) $row['numChild'] : null;
            $sourceName = $this->mapSourceName((string) ($row['apiSource'] ?? ''));

            $existing = Booking::where('uid', $uid)->first();

            // Fallback 1: iCal-imported booking for the same Beds24 bookId.
            // iCal bookings have group_id = bookId but a different uid format.
            if (! $existing) {
                $existing = Booking::where('group_id', $row['bookId'])
                    ->where('uid', 'NOT LIKE', 'beds24-%')
                    ->first();
            }

            // Fallback 2: same unit + exact dates.
            // Catches multipass/hbook entries AND other beds24 bookIds for the same physical
            // booking (Beds24 sometimes has two entries: one direct, one iCal, same dates).
            if (! $existing) {
                $existing = Booking::where('unit_id', $unit->id)
                    ->where('check_in', 'LIKE', $checkIn.'%')
                    ->where('check_out', 'LIKE', $checkOut.'%')
                    ->first();
            }

            // Assign canonical uid so future syncs find this booking reliably.
            if ($existing && $existing->uid !== $uid && ! $dryRun) {
                \Illuminate\Support\Facades\DB::table('bookings')
                    ->where('id', $existing->id)
                    ->update(['uid' => $uid]);
                $existing->uid = $uid;
            }

            if ($existing) {
                $changes = $this->buildChanges($existing, $checkIn, $checkOut, $guestName, $status, $price, $commission, $adults, $children, $sourceName, $row);

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

            // Skip creating entries with no useful guest/price data.
            // These are calendar blocks (iCal sync, owner blocks entered without a guest name).
            // If a real booking exists at these dates, the date fallback above already matched it.
            $apiSourceCode = (string) ($row['apiSource'] ?? '');
            if ($guestName === 'Guest' && $price === 0.0 && $commission === 0.0) {
                $this->line("  Skip: empty block [{$unit->name}] {$checkIn} (bookId={$row['bookId']}, src={$apiSourceCode})");
                $skipped++;

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
                    'commission' => $commission ?: null,
                    'adults' => $adults,
                    'children' => $children,
                    'source_name' => $sourceName,
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
     * Beds24 is the source of truth: sync all mutable fields (dates can change
     * on modification, status changes on cancellation, etc.).
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
        float $commission,
        ?int $adults,
        ?int $children,
        string $sourceName,
        array $row,
    ): array {
        $changes = [];

        if ($existing->check_in->format('Y-m-d') !== $checkIn) {
            $changes['check_in'] = $checkIn;
        }

        if ($existing->check_out->format('Y-m-d') !== $checkOut) {
            $changes['check_out'] = $checkOut;
        }

        // Don't overwrite a real guest name with the generic "Guest" placeholder.
        if ($existing->guest_name !== $guestName && $guestName !== 'Guest') {
            $changes['guest_name'] = $guestName;
        }

        // Never downgrade confirmed → pending: Beds24 often stores manual/synced entries
        // as status=0 (new), but they may already be confirmed in our DB from multipass/hbook.
        // Only allow: pending→confirmed, anything→cancelled.
        if ($existing->status !== $status && ! ($existing->status === 'confirmed' && $status === 'pending')) {
            $changes['status'] = $status;
        }

        if ($price > 0 && (float) $existing->getRawOriginal('price') !== $price) {
            $changes['price'] = $price;
        }

        if ($commission > 0 && (float) $existing->getRawOriginal('commission') !== $commission) {
            $changes['commission'] = $commission;
        }

        if ($adults !== null && $existing->getRawOriginal('adults') !== $adults) {
            $changes['adults'] = $adults;
        }

        if ($children !== null && $existing->getRawOriginal('children') !== $children) {
            $changes['children'] = $children;
        }

        if ($existing->getRawOriginal('source_name') !== $sourceName) {
            $changes['source_name'] = $sourceName;
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
     *
     * Important fields:
     *   referrer    — channel name set by the OTA/CM ("Airbnb", "Booking.com", …).
     *                 This is the only reliable canal indicator for iCal-sourced bookings
     *                 (apiSource 28/29) where apiSource alone is insufficient.
     *   api_source  — Beds24 channel code: 0=Direct, 19=Booking.com, 28=iCal, 29=Airbnb iCal, 46=Airbnb API
     *   api_ref     — OTA booking reference (e.g. Airbnb confirmation code HMMC43XXFH)
     */
    private function buildMeta(array $row): array
    {
        return array_filter([
            'beds24_book_id' => $row['bookId'] ?? null,
            'beds24_room_id' => $row['roomId'] ?? null,
            'email' => $row['email'] ?? null,
            'phone' => $row['phone'] ?? null,
            'mobile' => $row['mobile'] ?? null,
            'address' => $row['address'] ?? null,
            'country' => $row['country'] ?? null,
            'api_source' => $row['apiSource'] ?? null,
            'api_ref' => $row['apiRef'] ?? null,
            'referrer' => $row['referrer'] ?? null,
            'num_adult' => isset($row['numAdult']) ? (int) $row['numAdult'] : null,
            'num_child' => isset($row['numChild']) ? (int) $row['numChild'] : null,
            'num_baby' => isset($row['numBaby']) ? (int) $row['numBaby'] : null,
            'notes' => $row['notes'] ?? null,
            'message' => $row['message'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Map Beds24 apiSource code to Bokit source_name.
     *
     * Beds24 apiSource: '0'=Direct, '19'=Booking.com, '28'=iCal, '29'=Airbnb iCal, '46'=Airbnb API
     */
    private function mapSourceName(string $apiSource): string
    {
        return match ($apiSource) {
            '46' => 'airbnb',
            '19' => 'booking.com',
            default => 'beds24',
        };
    }

    /**
     * Map Beds24 status codes to Bokit statuses.
     *
     * Beds24: 0=new, 1=request, 2=confirmed, 3=cancelled, 4=block, 5=owner
     */
    private function mapStatus(string $status): string
    {
        return match ($status) {
            '0', '1' => 'pending',
            '3' => 'cancelled',
            default => 'confirmed',
        };
    }

    /**
     * Resolve all Beds24 room IDs for a unit.
     *
     * New format: unit.options.sources = [{type: 'beds24', room_id: '12345', enabled: true}, ...]
     * Legacy fallback: unit.options.beds24_room_id = '12345'
     *
     * @return array<int, int>  Beds24 room IDs (integers)
     */
    private function resolveBedsRoomIds(Unit $unit): array
    {
        $sources = $unit->options['sources'] ?? [];

        if (! empty($sources)) {
            return collect($sources)
                ->filter(fn ($s) => ($s['type'] ?? '') === 'beds24'
                    && ($s['enabled'] ?? true)
                    && ! empty($s['room_id'])
                )
                ->map(fn ($s) => (int) $s['room_id'])
                ->filter()
                ->values()
                ->all();
        }

        // Legacy: single beds24_room_id
        $roomId = $unit->options['beds24_room_id'] ?? null;

        return $roomId ? [(int) $roomId] : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildApiParams(): array
    {
        $params = [];

        if ($from = $this->option('from')) {
            $params['arrivalFrom'] = $from;
        }

        if ($to = $this->option('to')) {
            $params['arrivalTo'] = $to;
        }

        if ($modifiedSince = $this->option('modified-since')) {
            $params['modifiedSince'] = $modifiedSince;
        }

        return $params;
    }

    private function resolveProperties()
    {
        $query = Property::with('units');

        if ($slug = $this->option('property')) {
            $query->where('slug', $slug)->orWhere('id', (int) $slug);
        }

        return $query->get()->filter(
            fn (Property $p) => ! empty($p->options['beds24_api_key'])
                || ! empty($p->options['beds24_prop_key']),
        );
    }
}
