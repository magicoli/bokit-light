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
 *   - Each Unit stores options.beds24_room_id (the Beds24 roomId integer).
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
                            {--from=          : Filter by arrival from date (YYYY-MM-DD)}
                            {--to=            : Filter by arrival to date (YYYY-MM-DD)}
                            {--modified-since= : Only fetch bookings modified since this date (YYYY-MM-DD)}
                            {--dry-run        : Preview without saving}';

    protected $description = 'Sync Beds24 bookings into Bokit (recurring)';

    public function handle(): int
    {
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
                ->filter(fn (Unit $u) => ! empty($u->options['beds24_room_id']))
                ->keyBy(fn (Unit $u) => (int) $u->options['beds24_room_id'])
                ->all();

            if (empty($unitMap)) {
                $this->warn('  Skipping — no units have a beds24_room_id configured.');

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

            $guestName = trim(($row['firstName'] ?? '').' '.($row['lastName'] ?? '')) ?: 'Guest';
            $status = $this->mapStatus((string) ($row['status'] ?? '2'));
            $price = (float) ($row['price'] ?? 0);

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
                    'source_name' => 'beds24',
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
            'beds24_book_id' => $row['bookId'] ?? null,
            'beds24_room_id' => $row['roomId'] ?? null,
            'email' => $row['email'] ?? null,
            'phone' => $row['phone'] ?? null,
            'api_source' => $row['apiSource'] ?? null,
            'api_ref' => $row['apiRef'] ?? null,
            'num_adult' => isset($row['numAdult']) ? (int) $row['numAdult'] : null,
            'num_child' => isset($row['numChild']) ? (int) $row['numChild'] : null,
            'notes' => $row['notes'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
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
