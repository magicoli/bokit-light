<?php

namespace Modules\Beds24\Services;

use App\Contracts\SyncHandler;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Syncs Beds24 bookings into Bokit via the JSON API.
 *
 * Registered in Beds24ServiceProvider::boot() so bokit:sync includes it
 * automatically. Beds24SyncCommand also uses this class directly to get
 * targeted per-property / date-range runs without duplicating logic.
 *
 * Deduplication priority:
 *   1. uid = "beds24-{bookId}"
 *   2. Legacy iCal booking with matching Beds24 bookId stored as group_id
 *   3. email + exact dates + unit
 *   4. guest name + exact dates + unit
 *
 * Timezone: Beds24 dates are Y-m-d local strings. check_in = firstNight,
 * check_out = lastNight + 1 day. The Booking model mutators apply the unit
 * timezone, so we pass raw strings and let the model handle the shift.
 *
 * Guest email: Beds24 JSON API v1 may use "guestEmail" or "email";
 * we try both and fall back gracefully.
 *
 * Amount: acc_ttc from invoice lines is preferred. When acc_ttc ≤ 0 but
 * real payments exist (e.g. negative accommodation line with paid taxes),
 * payment_total is used as fallback for standalone bookings.
 */
class Beds24SyncHandler implements SyncHandler
{
    public function __construct(
        private readonly ?string $propertyFilter = null,
        private readonly string $from = '2020-01-01',
        private readonly ?string $to = null,
        private readonly ?string $modifiedSince = null,
    ) {}

    public function label(): string
    {
        return 'Beds24 API';
    }

    public function handle(OutputInterface $output, bool $dryRun = false): void
    {
        $properties = $this->resolveProperties();

        if ($properties->isEmpty()) {
            $output->writeln('<comment>No properties found with Beds24 configured.</comment>');

            return;
        }

        $totalCreated = 0;
        $totalUpdated = 0;
        $totalSkipped = 0;

        foreach ($properties as $property) {
            $output->writeln('');
            $output->writeln("<info>Property:</info> {$property->name}");

            $service = new Beds24ApiService($property);

            if (! $service->isConfigured()) {
                $output->writeln('  <comment>Skipping — Beds24 not configured.</comment>');

                continue;
            }

            $params = $this->buildApiParams();
            $rows = $service->getBookings($params);

            if (empty($rows)) {
                $output->writeln('  No bookings returned.');

                continue;
            }

            $output->writeln('  Fetched '.count($rows).' bookings from Beds24.', OutputInterface::VERBOSITY_VERBOSE);

            // Build roomId → Unit map.
            // Cannot use flatMap/mapWithKeys: array_merge() reindexes integer keys.
            /** @var array<int, Unit> $unitMap  beds24_room_id (int) → Unit */
            $unitMap = [];
            foreach ($property->units as $u) {
                foreach ($this->resolveBedsRoomIds($u) as $roomId) {
                    $unitMap[$roomId] = $u;
                }
            }

            if (empty($unitMap)) {
                $output->writeln('  <comment>Skipping — no units have a beds24 source configured.</comment>');

                continue;
            }

            [$created, $updated, $skipped] = $this->syncBookings($output, $property, $unitMap, $rows, $dryRun);

            $parts = [
                ($created > 0 ? '<fg=green>' : '')."New: {$created}".($created > 0 ? '</>' : ''),
                ($updated > 0 ? '<fg=yellow>' : '')."Updated: {$updated}".($updated > 0 ? '</>' : ''),
                "Skipped: {$skipped}",
            ];
            $output->writeln('  ✓ '.implode(', ', $parts));

            $totalCreated += $created;
            $totalUpdated += $updated;
            $totalSkipped += $skipped;
        }

        $suffix = $dryRun ? ' <fg=yellow>[DRY RUN]</>' : '';
        $output->writeln('');
        $output->writeln("Beds24 total:  New: {$totalCreated}, Updated: {$totalUpdated}, Skipped: {$totalSkipped}{$suffix}");
    }

    /**
     * @param  array<int, Unit>  $unitMap  beds24_room_id → Unit
     * @param  array<int, array<string,mixed>>  $rows
     * @return array{int, int, int} [created, updated, skipped]
     */
    private function syncBookings(
        OutputInterface $output,
        Property $property,
        array $unitMap,
        array $rows,
        bool $dryRun,
    ): array {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $roomId = (int) ($row['roomId'] ?? 0);
            $unit = $unitMap[$roomId] ?? null;

            if (! $unit) {
                $output->writeln("  <comment>Skip: unmapped roomId={$roomId} (bookId={$row['bookId']})</comment>", OutputInterface::VERBOSITY_VERBOSE);
                $skipped++;

                continue;
            }

            $uid = "beds24-{$row['bookId']}";
            $checkIn = $row['firstNight'] ?? null;
            $checkOut = isset($row['lastNight'])
                ? Carbon::parse($row['lastNight'])->addDay()->format('Y-m-d')
                : null;

            if (! $checkIn || ! $checkOut) {
                $output->writeln("  <comment>Skip: missing dates for bookId={$row['bookId']}</comment>", OutputInterface::VERBOSITY_VERBOSE);
                $skipped++;

                continue;
            }

            // Skip Beds24 availability blocks (status 4=block, 5=owner block).
            $rawStatus = (string) ($row['status'] ?? '2');
            if (in_array($rawStatus, ['4', '5'], true)) {
                $output->writeln("  Skip: availability block [{$unit->name}] {$checkIn} (bookId={$row['bookId']}, status={$rawStatus})", OutputInterface::VERBOSITY_VERBOSE);
                $skipped++;

                continue;
            }

            // Guest name: try guestFirstName/guestName (JSON API v1) then firstName/lastName (legacy).
            $guestName = trim(
                ($row['guestFirstName'] ?? $row['firstName'] ?? '').' '.($row['guestName'] ?? $row['lastName'] ?? '')
            ) ?: 'Guest';

            $status = $this->mapStatus($rawStatus);
            $commission = (float) ($row['commission'] ?? 0);
            $adults = isset($row['numAdult']) ? (int) $row['numAdult'] : null;
            $children = isset($row['numChild']) ? (int) $row['numChild'] : null;
            $sourceName = $this->mapSourceName((string) ($row['apiSource'] ?? ''));

            // Email: try guestEmail (JSON API v1) then email (legacy).
            $email = trim($row['guestEmail'] ?? $row['email'] ?? '');

            // Invoice: parse lines when available (more accurate than raw price field).
            $invoice = ! empty($row['invoice']) && is_array($row['invoice'])
                ? $this->parseInvoice($row['invoice'])
                : [];

            // Amount priority:
            //   1. acc_ttc from invoice lines (accommodation lines, most accurate)
            //   2. payment_total when acc_ttc ≤ 0 but real payments exist
            //   3. raw price field as last resort
            $price = $this->resolvePrice($row, $invoice);

            $existing = Booking::where('uid', $uid)->first();

            // Fallback 1: legacy iCal booking with same Beds24 bookId as group_id.
            if (! $existing) {
                $existing = Booking::where('group_id', $row['bookId'])
                    ->where('uid', 'NOT LIKE', 'beds24-%')
                    ->first();
            }

            // Fallback 2: email + exact dates + unit.
            if (! $existing && $email) {
                $existing = Booking::where('unit_id', $unit->id)
                    ->where('check_in', 'LIKE', $checkIn.'%')
                    ->where('check_out', 'LIKE', $checkOut.'%')
                    ->whereRaw("JSON_EXTRACT(metadata, '$.email') = ?", [$email])
                    ->first();
            }

            // Fallback 3: guest name + exact dates + unit.
            if (! $existing && $guestName !== 'Guest') {
                $existing = Booking::where('unit_id', $unit->id)
                    ->where('check_in', 'LIKE', $checkIn.'%')
                    ->where('check_out', 'LIKE', $checkOut.'%')
                    ->where('guest_name', $guestName)
                    ->first();
            }

            // Assign canonical uid so future syncs find this booking reliably.
            if ($existing && $existing->uid !== $uid && ! $dryRun) {
                DB::table('bookings')
                    ->where('id', $existing->id)
                    ->update(['uid' => $uid]);
                $existing->uid = $uid;
            }

            if ($existing) {
                $changes = $this->buildChanges(
                    $existing,
                    $checkIn, $checkOut, $guestName,
                    $status, $price, $commission,
                    $adults, $children, $sourceName,
                    $row, $invoice,
                );

                if (empty($changes)) {
                    $skipped++;

                    continue;
                }

                $output->writeln("  Update: [{$unit->name}] {$checkIn} uid={$uid} — ".implode(', ', array_keys($changes)), OutputInterface::VERBOSITY_VERBOSE);

                if (! $dryRun) {
                    $existing->update($changes);
                }

                $updated++;

                continue;
            }

            // Skip empty blocks (iCal-sourced or owner entries without guest data).
            $apiSourceCode = (string) ($row['apiSource'] ?? '');
            if ($guestName === 'Guest' && $price === 0.0 && $commission === 0.0) {
                $output->writeln("  Skip: empty block [{$unit->name}] {$checkIn} (bookId={$row['bookId']}, src={$apiSourceCode})", OutputInterface::VERBOSITY_VERBOSE);
                $skipped++;

                continue;
            }

            $output->writeln("  Create: [{$unit->name}] {$checkIn}→{$checkOut} — {$guestName} ({$price}€)", OutputInterface::VERBOSITY_VERBOSE);

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
                    'metadata' => $this->buildMeta($row, $invoice),
                ]);
            }

            $created++;
        }

        return [$created, $updated, $skipped];
    }

    /**
     * Resolve the booking price from invoice lines and raw row data.
     *
     * Priority:
     *   1. acc_ttc > 0  → use it (accommodation lines, most accurate)
     *   2. acc_ttc ≤ 0 but payment_total > 0 and invoice present
     *        → use payment_total (negative acc + real payments = taxe-only scenario)
     *   3. fall back to raw price field
     *
     * @param  array<string,mixed>  $row
     * @param  array{acc_ttc: float, taxe_invoiced: float, payment_total: float}|array{}  $invoice
     */
    private function resolvePrice(array $row, array $invoice): float
    {
        if (! empty($invoice)) {
            if (($invoice['acc_ttc'] ?? 0) > 0) {
                return $invoice['acc_ttc'];
            }

            if (($invoice['payment_total'] ?? 0) > 0) {
                return $invoice['payment_total'];
            }
        }

        return (float) ($row['price'] ?? 0);
    }

    /**
     * Compute fields that need updating for an existing booking.
     *
     * Beds24 is the source of truth: sync all mutable fields (dates can change
     * on modification, status changes on cancellation, etc.).
     *
     * @param  array<string,mixed>  $row
     * @param  array<string,float>  $invoice
     * @return array<string,mixed>
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
        array $invoice = [],
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

        // Never downgrade confirmed → pending: Beds24 often stores manual/synced
        // entries as status=0 (new) when they are already confirmed in our DB.
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

        $newMeta = $this->buildMeta($row, $invoice);
        $currentMeta = $existing->metadata ?? [];

        if (array_diff_assoc($newMeta, array_intersect_key($currentMeta, $newMeta))) {
            $changes['metadata'] = array_merge($currentMeta, $newMeta);
        }

        return $changes;
    }

    /**
     * Build metadata array from a Beds24 booking row.
     *
     * Key fields:
     *   email       — guest email (guestEmail or email field)
     *   api_source  — Beds24 channel code (0=Direct, 19=Booking.com, 28=iCal, 29=Airbnb iCal, 46=Airbnb API)
     *   referrer    — channel name set by the OTA/CM; sole reliable canal for iCal-sourced bookings
     *   api_ref     — OTA booking reference (e.g. Airbnb confirmation code)
     *   invoice_*   — parsed from Beds24 invoice lines
     *
     * @param  array<string,mixed>  $row
     * @param  array<string,float>  $invoice  parsed invoice breakdown (may be empty)
     * @return array<string,mixed>
     */
    private function buildMeta(array $row, array $invoice = []): array
    {
        $meta = [
            'beds24_book_id' => $row['bookId'] ?? null,
            'beds24_room_id' => $row['roomId'] ?? null,
            'email' => $row['guestEmail'] ?? $row['email'] ?? null,
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
        ];

        if (! empty($invoice)) {
            $meta['invoice_acc_ttc'] = $invoice['acc_ttc'];
            $meta['invoice_taxe_invoiced'] = $invoice['taxe_invoiced'];
            $meta['invoice_payment_total'] = $invoice['payment_total'];
        }

        return array_filter($meta, fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Parse Beds24 invoice lines to extract a financial breakdown.
     * Translated from taxesejour-bridge/beds24.py::_parse_invoice().
     *
     * Line types: 0/1/8 = accommodation; 200 = payment; others = extras.
     * Lines whose description contains "taxe de séjour" are tracked separately.
     *
     * @param  array<int, array<string,mixed>>  $lines
     * @return array{acc_ttc: float, taxe_invoiced: float, payment_total: float}
     */
    private function parseInvoice(array $lines): array
    {
        $accTtc = 0.0;
        $taxeInvoiced = 0.0;
        $paymentTotal = 0.0;

        foreach ($lines as $line) {
            $type = (string) ($line['type'] ?? '');
            $desc = mb_strtolower((string) ($line['description'] ?? ''));
            $price = (float) ($line['price'] ?? 0);

            if ($type === '200') {
                $paymentTotal += $price;

                continue;
            }

            if (str_contains($desc, 'taxe de séjour')) {
                $taxeInvoiced += $price;
            } elseif (in_array($type, ['0', '1', '8'], true)) {
                $accTtc += $price;
            }
        }

        return [
            'acc_ttc' => round($accTtc, 2),
            'taxe_invoiced' => round($taxeInvoiced, 2),
            'payment_total' => round($paymentTotal, 2),
        ];
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
     * @return array<int, int> Beds24 room IDs (integers)
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

    /** @return array<string,mixed> */
    private function buildApiParams(): array
    {
        $params = [
            'arrivalFrom' => $this->from,
            'arrivalTo' => $this->to ?? now()->addYears(5)->format('Y-m-d'),
        ];

        if ($this->modifiedSince) {
            $params['modifiedSince'] = $this->modifiedSince;
        }

        return $params;
    }

    private function resolveProperties()
    {
        $query = Property::with('units');

        if ($this->propertyFilter) {
            $query->where('slug', $this->propertyFilter)
                ->orWhere('id', (int) $this->propertyFilter);
        }

        return $query->get()->filter(
            fn (Property $p) => ! empty($p->options['beds24_api_key'])
                || ! empty($p->options['beds24_prop_key']),
        );
    }
}
