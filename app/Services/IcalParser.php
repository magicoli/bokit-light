<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\IcalSource;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Sabre\VObject;
use Exception;

class IcalParser
{
    /**
     * Synchronize a single iCal source
     */
    public function syncSource(IcalSource $source): array
    {
        try {
            Log::info("Syncing iCal source: {$source->name} for property {$source->property_id}");

            // 1. Fetch the iCal feed
            $response = Http::timeout(30)->get($source->url);

            if (!$response->successful()) {
                throw new Exception("Failed to fetch iCal feed. HTTP Status: " . $response->status());
            }

            // 2. Parse with sabre/vobject
            $vcalendar = VObject\Reader::read($response->body());

            // 3. Extract events
            $events = $this->extractEvents($vcalendar);

            Log::info("Found " . count($events) . " events in iCal feed");

            // 4. Sync to database
            $stats = $this->syncEventsToDatabase($source, $events);

            // 5. Mark as successfully synced
            $source->markAsSynced();

            Log::info("Sync completed: {$stats['created']} created, {$stats['updated']} updated");

            return $stats;

        } catch (Exception $e) {
            Log::error("Sync failed for {$source->name}: {$e->getMessage()}");
            $source->markAsErrored($e->getMessage());
            throw $e;
        }
    }

    /**
     * Extract events from a VCalendar object
     */
    protected function extractEvents(VObject\Component\VCalendar $vcalendar): array
    {
        $events = [];

        if (!isset($vcalendar->VEVENT)) {
            return $events;
        }

        foreach ($vcalendar->VEVENT as $vevent) {
            try {
                $event = [
                    'uid' => (string) ($vevent->UID ?? null),
                    'summary' => (string) ($vevent->SUMMARY ?? 'No title'),
                    'description' => (string) ($vevent->DESCRIPTION ?? ''),
                    'dtstart' => $this->parseDate($vevent->DTSTART),
                    'dtend' => $this->parseDate($vevent->DTEND),
                    'raw' => $vevent->serialize(),
                ];

                // Only add if we have valid dates
                if ($event['dtstart'] && $event['dtend']) {
                    $events[] = $event;
                }
            } catch (Exception $e) {
                Log::warning("Failed to parse event: {$e->getMessage()}");
                continue;
            }
        }

        return $events;
    }

    /**
     * Parse a date property from VObject
     */
    protected function parseDate($dateProperty): ?\DateTimeInterface
    {
        if (!$dateProperty) {
            return null;
        }

        try {
            return $dateProperty->getDateTime();
        } catch (Exception $e) {
            Log::warning("Failed to parse date: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Sync events to database
     */
    protected function syncEventsToDatabase(IcalSource $source, array $events): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'deleted' => 0];

        // Get all UIDs from the feed
        $feedUids = array_column($events, 'uid');

        foreach ($events as $event) {
            // Use UID + property_id as unique constraint
            $booking = Booking::withTrashed()->updateOrCreate(
                [
                    'uid' => $event['uid'],
                    'property_id' => $source->property_id,
                ],
                [
                    'guest_name' => $this->cleanGuestName($event['summary']),
                    'check_in' => $event['dtstart']->format('Y-m-d'),
                    'check_out' => $event['dtend']->format('Y-m-d'), // iCal dates are already correct (real check-in/check-out)
                    'source_name' => $source->name,
                    'raw_data' => [
                        'ical' => $event['raw'],
                        'description' => $event['description'],
                    ],
                    'is_manual' => false,
                    'deleted_at' => null, // Restore if was previously deleted
                ]
            );

            if ($booking->wasRecentlyCreated) {
                $stats['created']++;
            } else {
                $stats['updated']++;
            }
        }

        // Soft delete bookings from this source that are no longer in the feed
        // BUT ONLY if check_out is in the future (don't delete past bookings as iCal feeds don't include history)
        $deleted = Booking::where('property_id', $source->property_id)
            ->where('source_name', $source->name)
            ->where('check_out', '>=', now()->format('Y-m-d')) // Only future/current bookings
            ->whereNotIn('uid', $feedUids)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);

        $stats['deleted'] = $deleted;

        if ($deleted > 0) {
            Log::info("Soft deleted {$deleted} bookings that are no longer in the feed");
        }

        return $stats;
    }

    /**
     * Clean guest name from summary (remove common prefixes)
     */
    protected function cleanGuestName(string $summary): string
    {
        // Remove common prefixes like "Reserved", "Booking.com:", etc.
        $cleaners = [
            '/^Reserved\s*[-:]\s*/i',
            '/^Booking\.com\s*[-:]\s*/i',
            '/^Airbnb\s*[-:]\s*/i',
            '/^VRBO\s*[-:]\s*/i',
        ];

        foreach ($cleaners as $pattern) {
            $summary = preg_replace($pattern, '', $summary);
        }

        return trim($summary) ?: 'Guest';
    }

    /**
     * Sync all enabled sources
     */
    public function syncAllSources(): array
    {
        $sources = IcalSource::enabled()->get();
        $results = [];

        foreach ($sources as $source) {
            try {
                $results[$source->id] = [
                    'success' => true,
                    'stats' => $this->syncSource($source),
                ];
            } catch (Exception $e) {
                $results[$source->id] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}
