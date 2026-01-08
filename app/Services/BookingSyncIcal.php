<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\IcalSource;
use App\Support\Options;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BookingSyncIcal
{
    use \App\Traits\BookingSyncTrait;

    protected $sourceType = "ical";
    protected $sourceId;
    protected $sourceEventId;
    protected $propertyId;

    /**
     * Get the control string for this sync event
     * Implements the required method from BookingSyncInterface
     *
     * @return string Control string for this event
     */
    public function getControlString(): string
    {
        return self::calculateControlString(
            $this->sourceType,
            $this->sourceId,
            $this->sourceEventId,
            $this->propertyId,
        );
    }

    /**
     * Sync all iCal sources
     */
    public function syncAll(): array
    {
        $sources = IcalSource::with("unit")->get();
        $results = [];

        foreach ($sources as $source) {
            $results[] = $this->syncSource($source);
        }

        return $results;
    }

    /**
     * Sync a single iCal source
     */
    public function syncSource(IcalSource $source): array
    {
        // Optional delay between requests (configurable via Options, default: 0)
        $delay = (int) Options::get("sync.request_delay", 0);
        if (!is_numeric($delay)) {
            Log::warning(
                "BookingSyncIcal: sync request delay must be numeric",
                [
                    "provided" => $delay,
                    "fallback" => 0,
                ],
            );
        }
        if ($delay > 0) {
            usleep($delay * 1000); // Convert ms to microseconds
        }
        $seed = rand(1000, 9999);
        try {
            $seededUrl = url()->query($source->url, ["seed" => $seed]);

            Log::info(
                "[BookingSyncIcal] Syncing source: {$source->fullname()}",
            );

            // Fetch iCal file with browser-like headers to avoid rate limiting
            $response = Http::timeout(30)
                ->withHeaders([
                    "User-Agent" =>
                        "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
                    "Accept" => "text/calendar,text/plain,*/*",
                ])
                ->get($seededUrl);

            if (!$response->successful()) {
                $message = "Failed to fetch {$source->fullname()}";
                Log::error(
                    "[BookingSyncIcal] {$message} ({$response->status()})",
                    [
                        "url" => $seededUrl,
                        "property_id" => $source->unit->property->id,
                        "unit_id" => $source->unit->id,
                        "source_id" => $source->id,
                        "status" => $response->status(),
                        "reason" => $response->reason(),
                    ],
                );
                return ["success" => false, "error" => $message];
            }

            $icalContent = $response->body();

            // Parse events
            $events = $this->parseIcal($icalContent);

            // Sync to database
            $stats = $this->syncEventsToDatabase($events, $source);

            Log::info("[BookingSyncIcal] Synced {$source->fullname()}", [
                "success" => true,
                "total" => $stats["total"],
                "new" => $stats["new"],
                "updated" => $stats["updated"],
                "deleted" => $stats["deleted"],
                "vanished" => $stats["vanished"],
            ]);

            return [
                "success" => true,
                "total" => $stats["total"],
                "new" => $stats["new"],
                "updated" => $stats["updated"],
                "deleted" => $stats["deleted"],
                "vanished" => $stats["vanished"],
            ];
        } catch (\Exception $e) {
            $message = "Error syncing {$source->fullname()}";
            Log::error("[BookingSyncIcal] {$message}", [
                "error" => $e->getMessage(),
                "url" => $source->url,
                "property_id" => $source->unit->property->id,
                "unit_id" => $source->unit->id,
                "source_id" => $source->id,
            ]);
            return ["success" => false, "error" => $message];
        }
    }

    /**
     * Parse iCal content and extract events
     */
    protected function parseIcal(string $content): array
    {
        $events = [];
        $lines = explode("\n", $content);
        $currentEvent = null;
        $currentField = null;
        $currentValue = "";

        foreach ($lines as $line) {
            $line = rtrim($line, "\r");

            // Handle line continuation (starts with space or tab)
            if (preg_match('/^[ \t]/', $line)) {
                $currentValue .= ltrim($line);
                continue;
            }

            // Store previous field
            if ($currentField && $currentEvent !== null) {
                $currentEvent[$currentField] = $currentValue;
            }

            // Parse new field
            if (strpos($line, ":") !== false) {
                [$field, $value] = explode(":", $line, 2);

                // Remove parameters (e.g., DTSTART;VALUE=DATE)
                $field = preg_replace('/;.*$/', "", $field);

                $currentField = $field;
                $currentValue = $value;

                // Start new event
                if ($field === "BEGIN" && $value === "VEVENT") {
                    $currentEvent = [];
                }

                // End current event
                if (
                    $field === "END" &&
                    $value === "VEVENT" &&
                    $currentEvent !== null
                ) {
                    $events[] = $currentEvent;
                    $currentEvent = null;
                    $currentField = null;
                    $currentValue = "";
                }
            }
        }

        return $events;
    }

    /**
     * Sync events to database
     */
    protected function syncEventsToDatabase(
        array $events,
        IcalSource $source,
    ): array {
        $stats = [
            "total" => 0,
            "new" => 0,
            "updated" => 0,
            "deleted" => 0,
            "vanished" => 0,
        ];

        // Collect UIDs from feed for vanished detection
        $feedUids = [];

        // Source identifier for sync system (e.g., "airbnb_ical")
        $sourceId = ($source->name ?? "unknown") . "_ical";

        foreach ($events as $event) {
            // Required fields
            if (
                !isset($event["UID"]) ||
                !isset($event["DTSTART"]) ||
                !isset($event["DTEND"])
            ) {
                continue;
            }

            $feedUids[] = $event["UID"];

            // Skip "Unavailable" bookings
            $summary = trim($event["SUMMARY"] ?? "");

            // Parse dates
            $checkIn = $this->parseIcalDate($event["DTSTART"]);
            $checkOut = $this->parseIcalDate($event["DTEND"]);

            if (!$checkIn || !$checkOut) {
                continue;
            }

            // Decode and parse metadata from DESCRIPTION field
            $description = $this->decodeIcalText($event["DESCRIPTION"] ?? "");
            $parsed = BookingMetadataParser::parse($description);

            // Set special statuses
            $status = strtolower($parsed["metadata"]["status"] ?? "");
            if (empty($status)) {
                switch ($summary) {
                    case "Unavailable":
                    case "Airbnb (Not available)":
                        $status = "unavailable";
                        $summary = __("Unavailable");
                        break;
                    case "Reserved":
                        $status = "confirmed";
                        $summary = __("Reserved (Airbnb)");
                        break;
                    default:
                        $status = "undefined";
                }
                $parsed["metadata"]["status"] = $status;
                $event["SUMMARY"] = $summary;
            }
            switch ($status) {
                case "cancelled":
                case "cancelled_by_owner":
                case "cancelled_by_guest":
                case "deleted":
                case "vanished":
                    if (!preg_match($status, $summary)) {
                        $summary = "[{$status}] {$summary}";
                    }
                    $event["SUMMARY"] = $summary;
                    break;
            }

            $adults = $parsed["metadata"]["adult"] ?? null;
            $children = $parsed["metadata"]["child"] ?? null;

            // Track deleted bookings (cancelled/deleted status)
            $isDeleted = in_array($status, [
                "cancelled",
                "cancelled_by_owner",
                "cancelled_by_guest",
                "deleted",
            ]);

            // Get existing booking
            $booking = Booking::where("uid", $event["UID"])
                ->where("unit_id", $source->unit_id)
                ->first();

            // PROCESSED: Map raw iCal data to model fields
            // This is the single source of truth for what goes into the model
            $processed = [
                "guest_name" => $event["SUMMARY"] ?? "Unknown Guest",
                "check_in" => $checkIn,  // Already Y-m-d format from parseIcalDate()
                "check_out" => $checkOut,  // Already Y-m-d format from parseIcalDate()
                "status" => $status,
                "adults" => $adults,
                "children" => $children,
                "notes" => $parsed["notes"] ?: null,
            ];

            // Calculate checksum of processed data to detect source changes
            $newChecksum = $this->calculateChecksum($processed);

            // Get existing booking
            $booking = Booking::where("uid", $event["UID"])
                ->where("unit_id", $source->unit_id)
                ->first();

            // Check if data has changed at source (compare checksums)
            $hasSourceChanged = true;
            if ($booking && isset($booking->sync_data[$sourceId])) {
                $oldChecksum = $booking->sync_data[$sourceId]['checksum'] ?? null;
                $hasSourceChanged = ($newChecksum !== $oldChecksum);
            }

            // Skip if nothing changed at source (optimization)
            if ($booking && !$hasSourceChanged) {
                $stats["total"]++;
                continue;
            }

            // $newData is exactly what's in processed (same data, used for model update)
            $newData = $processed;

            if (!$booking) {
                // Create new booking with sync data
                $booking = new Booking([
                    "uid" => $event["UID"],
                    "unit_id" => $source->unit_id,
                    "source_name" => $source->name ?? "undefined",
                ]);
                $booking->fill($newData);

                // Store raw, processed, and checksum in sync_data
                $booking->sync_data = [
                    $sourceId => [
                        "raw" => $event,
                        "processed" => $processed,
                        "checksum" => $newChecksum,
                        "synced_at" => now()->toIso8601String(),
                    ],
                ];

                $booking->save();
                $stats["new"]++;
            } else {
                // Use three-way merge to update existing booking
                $result = $booking->applySyncData($newData, $sourceId, [
                    "raw" => $event,
                    "processed" => $processed,
                    "checksum" => $newChecksum,
                ]);

                // Track stats based on what was updated
                if (!empty($result["updated"])) {
                    $stats["updated"]++;
                }
            }

            // Track deleted bookings
            if ($isDeleted) {
                $stats["deleted"]++;
            }

            $stats["total"]++;
        }

        // Detect vanished bookings (not in feed anymore)
        // Only check for future/current bookings
        // Special handling: unavailable bookings are deleted completely
        // as they are typically auto-generated by availability rules
        $bookingsToVanish = Booking::where("unit_id", $source->unit_id)
            ->where("source_name", $source->name)
            ->where("check_out", ">=", now()->format("Y-m-d"))
            ->whereNotIn("uid", $feedUids)
            ->whereNotIn("status", [
                "cancelled",
                "cancelled_by_owner",
                "cancelled_by_guest",
                "vanished",
            ])
            ->get();

        foreach ($bookingsToVanish as $booking) {
            if ($booking->status === "unavailable") {
                // Delete unavailable bookings completely
                $booking->delete();
                $stats["deleted"]++;
            } else {
                // Mark other bookings as vanished
                $booking->update(["status" => "vanished"]);
                $stats["vanished"]++;
            }
        }

        return $stats;
    }

    /**
     * Check if the given iCal source is the primary source for its unit
     *
     * @param IcalSource $source
     * @return bool
     */
    protected function isPrimaryIcalSource(IcalSource $source): bool
    {
        // For now, consider all iCal sources as primary since we don't have API sources yet
        // When API sources are added, this method should check if this is the first/primary source
        // For example: return $source->is_primary || $source->id === $this->getPrimarySourceId($source->unit_id);
        return true;
    }

    /**
     * Calculate checksum of processed data to detect source changes
     * 
     * @param array $data Processed data
     * @param array $excludeFields Fields to exclude from checksum (whitelist of exclusions)
     * @return string MD5 checksum
     */
    protected function calculateChecksum(array $data, array $excludeFields = []): string
    {
        // Remove excluded fields
        $dataForChecksum = array_diff_key($data, array_flip($excludeFields));
        
        // Sort keys for deterministic result
        ksort($dataForChecksum);
        
        // Convert to JSON and calculate MD5
        return md5(json_encode($dataForChecksum, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Parse iCal date format
     */
    protected function parseIcalDate(string $date): ?string
    {
        // Remove timezone info
        $date = preg_replace("/^TZID=.*?:/", "", $date);

        // Format: YYYYMMDD or YYYYMMDDTHHMMSS
        if (preg_match("/^(\d{4})(\d{2})(\d{2})/", $date, $matches)) {
            return "{$matches[1]}-{$matches[2]}-{$matches[3]}";
        }

        return null;
    }

    /**
     * Decode iCal text escaping
     *
     * iCal spec escapes special characters:
     * - \n -> newline
     * - \, -> comma
     * - \; -> semicolon
     * - \\ -> backslash
     */
    protected function decodeIcalText(string $text): string
    {
        // Decode escape sequences
        $text = str_replace('\\n', "\n", $text); // \n -> actual newline
        $text = str_replace("\\,", ",", $text); // \, -> ,
        $text = str_replace("\\;", ";", $text); // \; -> ;
        $text = str_replace("\\\\", "\\", $text); // \\ -> \

        return $text;
    }
}
