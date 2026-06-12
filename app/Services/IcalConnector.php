<?php

namespace App\Services;

use App\Contracts\SourceConnector;
use App\Models\BookingSource;
use App\Models\IcalSource;
use App\Models\Unit;
use App\Support\NormalizedBooking;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Fetches iCal feeds (unit.options.sources type = 'ical') and normalizes
 * events for SyncEngine.
 *
 * Pure connector: no database access. Parsing is delegated to
 * BookingSyncIcal::parseIcal() / parse(), which remain the single source of
 * truth for iCal format handling.
 */
class IcalConnector implements SourceConnector
{
    public function __construct(private readonly BookingSyncIcal $parser) {}

    public function sourceType(): string
    {
        return 'ical';
    }

    public function label(): string
    {
        return 'iCal';
    }

    public function displayLabel(array $sourceConfig): string
    {
        return 'iCal '.$this->configLabel($sourceConfig);
    }

    public function sourceKey(Unit $unit, array $sourceConfig): string
    {
        return 'ical:'.$this->configLabel($sourceConfig);
    }

    public function externalBookingUrl(BookingSource $source): ?string
    {
        return null;
    }

    public function fetchBookings(Unit $unit, array $sourceConfig): array
    {
        $url = $sourceConfig['url'] ?? '';

        if (! $url) {
            throw new \RuntimeException('No URL configured');
        }

        $response = Http::timeout(60)
            ->connectTimeout(30)
            ->retry(2, 2000, fn (\Throwable $e): bool => $e instanceof ConnectionException, throw: false)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/calendar,text/plain,*/*',
            ])
            ->get(url()->query($url, ['seed' => rand(1000, 9999)]));

        if (! $response->successful()) {
            throw new \RuntimeException("Failed to fetch feed ({$response->status()})");
        }

        $events = $this->parser->parseIcal($response->body());

        $label = $this->configLabel($sourceConfig);

        // In-memory source so BookingSyncIcal::parse() needs no changes.
        $icalSource = new IcalSource([
            'unit_id' => $unit->id,
            'name' => $label,
            'url' => $url,
        ]);
        $icalSource->setRelation('unit', $unit);

        $bookings = [];

        foreach ($events as $event) {
            if (! isset($event['UID'], $event['DTSTART'], $event['DTEND'])) {
                continue;
            }

            try {
                $processed = BookingSyncIcal::parse($event, $icalSource);
            } catch (\InvalidArgumentException $e) {
                continue;
            }

            $status = $this->mapStatus($processed['status'] ?? 'undefined');

            // Past or ongoing blocks are artifacts of platform booking rules
            // (e.g. Airbnb rolling availability windows), not intentional
            // blocks. Only future ones are meaningful.
            if ($status === 'blocked'
                && Carbon::parse($processed['check_in'])->lte(now()->startOfDay())) {
                continue;
            }

            $metadata = $processed['metadata'] ?? [];

            $bookings[] = new NormalizedBooking(
                externalId: $event['UID'],
                checkIn: Carbon::parse($processed['check_in'])->format('Y-m-d'),
                checkOut: Carbon::parse($processed['check_out'])->format('Y-m-d'),
                guestName: $processed['guest_name'] ?? 'Guest',
                status: $status,
                email: $metadata['email'] ?? null,
                price: isset($processed['price']) ? (float) $processed['price'] : null,
                commission: isset($processed['commission']) ? (float) $processed['commission'] : null,
                guests: isset($processed['guests']) ? (int) $processed['guests'] : null,
                adults: isset($processed['adults']) ? (int) $processed['adults'] : null,
                children: isset($processed['children']) ? (int) $processed['children'] : null,
                channel: $label,
                metadata: $metadata,
                originHint: $this->detectOriginHint($event['UID']),
                legacyUid: $event['UID'],
            );
        }

        return $bookings;
    }

    /**
     * Map the legacy parser's statuses to Bokit's canonical statuses
     * (see Booking::STATUSES). Platform-specific variants are not kept.
     */
    private function mapStatus(string $status): string
    {
        return match ($status) {
            'unavailable' => 'blocked',
            'cancelled_by_owner', 'cancelled_by_guest' => 'cancelled',
            'request' => 'option',
            'inquiry' => 'quote',
            default => $status,
        };
    }

    /**
     * Some feeds embed the booking's id in the source system inside the UID.
     * Beds24 exports use "…-b{bookId}@beds24.com" — declare it as the origin
     * so the engine matches by id instead of creating a duplicate.
     *
     * @return array{type: string, external_id: string}|null
     */
    private function detectOriginHint(string $uid): ?array
    {
        if (preg_match('/-b(\d+)@beds24\.com$/i', $uid, $matches)) {
            return ['type' => 'beds24', 'external_id' => $matches[1]];
        }

        return null;
    }

    private function configLabel(array $sourceConfig): string
    {
        $url = $sourceConfig['url'] ?? '';

        return $sourceConfig['label']
            ?? ($url ? (parse_url($url, PHP_URL_HOST) ?: $url) : 'unnamed');
    }
}
