<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\IcalSource;
use App\Support\Options;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IcalParser
{
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
        if ($delay > 0) {
            usleep($delay * 1000); // Convert ms to microseconds
        }

        $seed = rand(1000, 9999);
        try {
            $seededUrl = url()->query($source->url, ["seed" => $seed]);

            Log::info("[IcalParser] Syncing source: {$source->fullname()}");

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
                Log::error("[IcalParser] {$message} ({$response->status()})", [
                    "url" => $seededUrl,
                    "property_id" => $source->unit->property->id,
                    "unit_id" => $source->unit->id,
                    "source_id" => $source->id,
                    "status" => $response->status(),
                    "reason" => $response->reason(),
                ]);
                return ["success" => false, "error" => $message];
            }

            $icalContent = $response->body();

            // Parse events
            $events = $this->parseIcal($icalContent);

            // Sync to database
            $stats = $this->syncEventsToDatabase($events, $source);

            Log::info("[IcalParser] Synced {$source->fullname()}", [
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
            Log::error("[IcalParser] {$message}", [
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

            // Get existing booking to compare for changes
            $existing = Booking::where("uid", $event["UID"])
                ->where("unit_id", $source->unit_id)
                ->first();

            // Snapshot existing data for comparison (convert dates to strings)
            $existingData = $existing
                ? [
                    "guest_name" => $existing->guest_name,
                    "check_in" => $existing->check_in->format("Y-m-d"),
                    "check_out" => $existing->check_out->format("Y-m-d"),
                    "status" => $existing->status,
                    "adults" => $existing->adults,
                    "children" => $existing->children,
                    "notes" => $existing->notes,
                ]
                : null;

            // Create or update booking
            $booking = Booking::updateOrCreate(
                [
                    "uid" => $event["UID"],
                    "unit_id" => $source->unit_id,
                ],
                [
                    "guest_name" => $event["SUMMARY"] ?? "Unknown Guest",
                    "check_in" => $checkIn,
                    "check_out" => $checkOut,
                    "status" => $status,
                    "adults" => $adults,
                    "children" => $children,
                    "notes" => $parsed["notes"] ?: null,
                    "raw_data" => $parsed["metadata"],
                    "source_name" => $source->name ?? "undefined",
                ],
            );

            // Track stats
            if ($isDeleted) {
                $stats["deleted"]++;
            } elseif ($booking->wasRecentlyCreated) {
                $stats["new"]++;
            } elseif (
                $existingData &&
                $this->hasDataChanged($existingData, [
                    "guest_name" => $booking->guest_name,
                    "check_in" => $booking->check_in->format("Y-m-d"),
                    "check_out" => $booking->check_out->format("Y-m-d"),
                    "status" => $booking->status,
                    "adults" => $booking->adults,
                    "children" => $booking->children,
                    "notes" => $booking->notes,
                ])
            ) {
                $stats["updated"]++;
            }

            $stats["total"]++;
        }

        // Detect vanished bookings (not in feed anymore)
        // Only check for future/current bookings
        $vanished = Booking::where("unit_id", $source->unit_id)
            ->where("source_name", $source->name)
            ->where("check_out", ">=", now()->format("Y-m-d"))
            ->whereNotIn("uid", $feedUids)
            ->whereNotIn("status", [
                "cancelled",
                "cancelled_by_owner",
                "cancelled_by_guest",
                "vanished",
            ])
            ->update(["status" => "vanished"]);

        $stats["vanished"] = $vanished;

        return $stats;
    }

    /**
     * Check if booking data has actually changed
     */
    protected function hasDataChanged(array $old, array $new): bool
    {
        return $old["guest_name"] !== $new["guest_name"] ||
            $old["check_in"] !== $new["check_in"] ||
            $old["check_out"] !== $new["check_out"] ||
            $old["status"] !== $new["status"] ||
            $old["adults"] !== $new["adults"] ||
            $old["children"] !== $new["children"] ||
            $old["notes"] !== $new["notes"];
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
