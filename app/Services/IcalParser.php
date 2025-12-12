<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\IcalSource;
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
        $seed = rand(1000, 9999);
        try {
            $seededUrl = url()->query($source->url, ["seed" => $seed]);

            Log::info(
                "[IcalParser] Syncing source: {$source->unit->property->name} {$source->unit->name} from {$source->name}",
            );

            // Fetch iCal file
            $response = Http::timeout(30)->get($seededUrl);

            if (!$response->successful()) {
                $message = "Failed to fetch {$source->unit->property->name} {$source->unit->name} from {$source->name} ({$response->status()})";
                Log::error("[IcalParser] {$message}", [
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
            $synced = $this->syncEventsToDatabase($events, $source);

            Log::info(
                "[IcalParser] Synced {$synced} events from source {$source->id}",
            );

            return ["success" => true, "count" => $synced];
        } catch (\Exception $e) {
            $message = "Error syncing {$source->unit->property->name} {$source->unit->name} from {$source->name}";
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
    ): int {
        $synced = 0;

        foreach ($events as $event) {
            // Required fields
            if (
                !isset($event["UID"]) ||
                !isset($event["DTSTART"]) ||
                !isset($event["DTEND"])
            ) {
                continue;
            }

            // Skip "Unavailable" bookings
            $summary = $event["SUMMARY"] ?? "";
            if (strtolower(trim($summary)) === "unavailable") {
                continue;
            }

            // Parse dates
            $checkIn = $this->parseIcalDate($event["DTSTART"]);
            $checkOut = $this->parseIcalDate($event["DTEND"]);

            if (!$checkIn || !$checkOut) {
                continue;
            }

            // Decode and parse metadata from DESCRIPTION field
            $description = $this->decodeIcalText($event["DESCRIPTION"] ?? "");
            $parsed = BookingMetadataParser::parse($description);

            // Extract critical fields
            $status = $parsed["metadata"]["status"] ?? null;
            $adults = $parsed["metadata"]["adult"] ?? null;
            $children = $parsed["metadata"]["child"] ?? null;

            // Create or update booking
            Booking::updateOrCreate(
                [
                    "uid" => $event["UID"],
                    "unit_id" => $source->unit_id,
                ],
                [
                    "guest_name" => $event["SUMMARY"] ?? "Unknown Guest",
                    "check_in" => $checkIn,
                    "check_out" => $checkOut,
                    "status" => strtolower($status),
                    "adults" => $adults,
                    "children" => $children,
                    "notes" => $parsed["notes"] ?: null,
                    "raw_data" => $parsed["metadata"],
                    "source_name" => $source->name ?? "undefined",
                ],
            );

            $synced++;
        }

        return $synced;
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
