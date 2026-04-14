<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Export confirmed bookings to a semicolon-separated CSV file (French format).
 *
 * Canal resolution (source_name + metadata):
 *   airbnb / www.airbnb.fr   → Airbnb (iCal placeholder); skipped if guest="Reserved*" (no data)
 *   beds24 / api.beds24.com  → api_source '46' → Airbnb (Beds24 Airbnb API, authoritative)
 *                             → api_source '19' → Booking.com
 *                             → referrer 'Airbnb*' → Airbnb (iCal, api_source 28/29)
 *                             → referrer 'Booking*' → Booking.com (iCal)
 *                             → else            → Direct
 *   booking.com              → Booking.com (set by beds24:sync mapSourceName)
 *   hbook                    → Direct (always — OTAs excluded by WP endpoint)
 *   multipass                → metadata.origin: airbnb→Airbnb, booking→Booking.com, else→Direct
 */
class BookingsExportCsvCommand extends Command
{
    protected $signature = 'bookings:export-csv
                            {--year=     : Export bookings with check-in in this year (YYYY)}
                            {--from=     : Check-in from date (YYYY-MM-DD)}
                            {--to=       : Check-in to date (YYYY-MM-DD)}
                            {--output=   : Output file path (default: tmp/bookings-{year}.csv)}
                            {--property= : Property slug or ID (all properties if omitted)}';

    protected $description = 'Export confirmed bookings to CSV with canal, price, commission, adults, children';

    public function handle(): int
    {
        [$from, $to, $label] = $this->resolveDateRange();

        if (! $from) {
            $this->error('Provide --year=YYYY or --from=YYYY-MM-DD / --to=YYYY-MM-DD');

            return self::FAILURE;
        }

        $outputPath = $this->option('output') ?? "tmp/bookings-{$label}.csv";

        $bookings = $this->loadBookings($from, $to);
        $this->info("Found {$bookings->count()} confirmed bookings from {$from} to {$to}.");

        $this->writeCsv($bookings, $outputPath);
        $this->info("Exported to {$outputPath}");

        return self::SUCCESS;
    }

    /** @return array{string|null, string|null, string} [from, to, label] */
    private function resolveDateRange(): array
    {
        if ($year = $this->option('year')) {
            return ["{$year}-01-01", "{$year}-12-31", $year];
        }

        $from = $this->option('from');
        $to = $this->option('to');
        $label = ($from ?? 'all').($to ? "_to_{$to}" : '');

        return [$from, $to, $label];
    }

    private function loadBookings(?string $from, ?string $to): Collection
    {
        $query = Booking::with(['unit', 'property'])
            ->where('status', 'confirmed')
            ->orderBy('check_in');

        if ($from) {
            $query->where('check_in', '>=', $from);
        }
        if ($to) {
            $query->where('check_in', '<=', $to);
        }

        if ($slug = $this->option('property')) {
            $query->whereHas('property', fn ($q) => $q
                ->where('slug', $slug)
                ->orWhere('id', (int) $slug)
            );
        }

        return $query->get();
    }

    private function writeCsv(Collection $bookings, string $path): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $handle = fopen($path, 'w');
        // BOM for Excel UTF-8 compatibility
        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [
            'Arrivée', 'Départ', 'Gîte', 'Nuits', 'Locataire', 'Email',
            'Canal', 'Prix (€)', 'Commission (€)', 'Adultes', 'Enfants',
        ], ';');

        foreach ($bookings as $booking) {
            $canal = $this->resolveCanal($booking);

            if ($canal === null) {
                continue; // Skip obsolete iCal-only sources (www.airbnb.fr, etc.)
            }

            $email = $booking->metadata['email'] ?? '';

            fputcsv($handle, [
                $booking->check_in->format('Y-m-d'),
                $booking->check_out->format('Y-m-d'),
                $booking->unit?->name ?? '',
                $booking->nights(),
                $booking->guest_name,
                $email,
                $canal,
                $this->formatAmount($booking->getRawOriginal('price')),
                $this->formatAmount($booking->getRawOriginal('commission')),
                $booking->getRawOriginal('adults') ?? '',
                $booking->getRawOriginal('children') ?? '',
            ], ';');
        }

        fclose($handle);
    }

    /**
     * Resolve the display canal (Airbnb / Booking.com / Direct) from source_name + metadata.
     *
     * Returns null to skip rows that are pure calendar placeholders (no guest/financial data).
     */
    private function resolveCanal(Booking $booking): ?string
    {
        $rawSource = $booking->getRawOriginal('source_name') ?? '';
        $slug = \App\Models\Booking::sourceSlug($rawSource);
        $apiSource = (string) ($booking->metadata['api_source'] ?? '');
        $referrer = strtolower($booking->metadata['referrer'] ?? '');
        $origin = strtolower($booking->metadata['origin'] ?? '');

        return match (true) {
            // Airbnb iCal placeholder: "Reserved (Airbnb)" — no guest or financial data
            $slug === 'airbnb' && str_starts_with($booking->guest_name, 'Reserved') => null,
            $slug === 'airbnb' => 'Airbnb',
            // Beds24: authoritative via apiSource, fallback to referrer for iCal (apiSource 28/29)
            $slug === 'beds24' => match (true) {
                $apiSource === '46' => 'Airbnb',
                $apiSource === '19' => 'Booking.com',
                str_contains($referrer, 'airbnb') => 'Airbnb',
                str_contains($referrer, 'booking') => 'Booking.com',
                default => 'Direct',
            },
            // Set directly by beds24:sync mapSourceName after enrichment
            str_contains($rawSource, 'booking') => 'Booking.com',
            $slug === 'hbook' => 'Direct',
            $slug === 'multipass' => str_contains($origin, 'airbnb')
                ? 'Airbnb'
                : (str_contains($origin, 'booking') ? 'Booking.com' : 'Direct'),
            default => 'Direct',
        };
    }

    /**
     * Format a decimal amount using French locale convention (comma decimal, space thousands).
     */
    private function formatAmount(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $float = (float) $value;
        if ($float === 0.0) {
            return '';
        }

        return number_format($float, 2, ',', "\u{202F}");
    }
}
