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
     * Parse structured metadata from iCal description
     *
     * Expected format from Beds24:
     * STATUS:[STATUS]/[GROUPID]
     * GUESTS:[NUMPEOPLE1]/[NUMADULT1]/[NUMCHILD1]
     * TIME:[GUESTARRIVALTIME]
     * PHONE:[GUESTPHONE]/[GUESTMOBILE]
     * EMAIL:[GUESTEMAIL]
     * CTRY:[GUESTCOUNTRY2]
     * OTA:[APISOURCETEXT] [APIREF]
     * COMMENTS:[GUESTCOMMENTS]
     * NOTES:[NOTES]
     * Any remaining text...
     *
     * @param string $description Raw iCal DESCRIPTION field
     * @return array ['metadata' => [...], 'notes' => '...']
     */
    /**
     * Parse iCal event to extract all booking data
     *
     * This method handles all iCal-specific parsing and returns a standardized
     * $processed array ready for database storage. It's the single source of truth
     * for how iCal data maps to Booking model fields.
     *
     * @param array $event iCal event with SUMMARY, DTSTART, DTEND, DESCRIPTION, etc.
     * @param object $source IcalSource model instance
     * @return array Standardized $processed array ready for storage
     */
    public static function parse(array $event, object $source): array
    {
        // Parse iCal dates - no timezone conversion, save as-is
        // Dates will be converted to unit timezone on read via Booking accessors
        if (!isset($event["DTSTART"]) || !isset($event["DTEND"])) {
            throw new \InvalidArgumentException("Missing dates in iCal event");
        }

        $checkIn = $event["DTSTART"]; // e.g., "20260110"
        $checkOut = $event["DTEND"]; // e.g., "20260117"

        if (!$checkIn || !$checkOut) {
            throw new \InvalidArgumentException("Invalid dates in iCal event");
        }

        // Decode and parse DESCRIPTION field
        $description = self::decodeIcalText($event["DESCRIPTION"] ?? "");
        $summary = self::decodeIcalText($event["SUMMARY"] ?? "Unknown Guest");

        $metadata = [];
        $remainingLines = [];

        $lines = explode("\n", $description);

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line)) {
                continue;
            }

            // Try to match "KEY: value" pattern
            if (
                preg_match('/^([A-Z][A-Z0-9]*)\s*:\s*(.*)$/i', $line, $matches)
            ) {
                $key = strtolower($matches[1]);
                $value = trim($matches[2]);

                // Only store if value is not empty
                if ($value !== "") {
                    // Special handling for specific fields
                    switch ($key) {
                        case "status":
                            $parts = explode("/", $value);
                            $metadata["status"] = strtolower($parts[0] ?? null);
                            $metadata["group_id"] = $parts[1] ?? null;
                            break;

                        case "guests":
                            // Split guests/adult/child
                            $parts = explode("/", $value);
                            $metadata["guests"] = (int) $parts[0];
                            $metadata["adults"] = (int) ($parts[1] ?? 0);
                            $metadata["children"] = (int) ($parts[2] ?? 0);
                            break;

                        case "adult":
                        case "adults":
                            $metadata["adults"] = (int) $value;
                            break;

                        case "child":
                        case "children":
                            $metadata["children"] = (int) $value;
                            break;

                        case "time":
                            $metadata["arrival_time"] = $value;
                            break;

                        case "phone":
                            $parts = explode("/", $value);
                            $metadata["phone"] = $parts[0] ?? null;
                            $metadata["mobile"] = $parts[1] ?? null;
                            break;

                        case "mobile":
                            $metadata["mobile"] = $value;
                            break;

                        case "email":
                            $metadata["email"] = $value;
                            break;

                        case "ctry":
                        case "country":
                        case "country2":
                            $metadata["country"] = $value;
                            break;

                        case "comments":
                            $metadata["ota_comments"] = $value;
                            break;

                        case "notes":
                            $metadata["notes"] = $value;
                            break;

                        case "ota":
                            // Split "VRBO 123456" or "VRBO/123456" into source and ref
                            $value = str_replace(" ", "/", $value);
                            $parts = explode("/", $value, 2);
                            $metadata["api_source"] = $parts[0];
                            if (isset($parts[1])) {
                                $metadata["api_ref"] = $parts[1];
                            }
                            break;

                        default:
                            // Store any other KEY: value pairs
                            $metadata[$key] = $value;
                    }
                }
            } else {
                // Not a "KEY: value" line, keep it
                $remainingLines[] = $line;
            }
        }

        /*
        // KEEP THIS NOTE until properly implemented.
        // Current method of adding + is only a workaround, it is not proper phone normalization
        //
        // TODO: properly normalize phone numbers, see if Laravel-Phone package allow this
        // https://github.com/Propaganistas/Laravel-Phone
        $phone = new PhoneNumber('012/34.56.78', 'BE');
        $phone->format($format);       // See libphonenumber\PhoneNumberFormat
        $phone->formatE164();          // +3212345678
        $phone->formatInternational(); // +32 12 34 56 78
        $phone->formatRFC3966();       // tel:+32-12-34-56-78
        $phone->formatNational();      // 012 34 56 78
        */
        // For now, assume if it contains only numbers and doesn't start with a zero, it's
        // missing the plus sign.
        if (isset($metadata["phone"])) {
            $metadata["phone"] = preg_replace(
                '/^([1-9][0-9]+)$/',
                '+$1',
                $metadata["phone"],
            );
        }
        if (isset($metadata["mobile"])) {
            $metadata["mobile"] = preg_replace(
                '/^([1-9][0-9]+)$/',
                '+$1',
                $metadata["mobile"],
            );
        }

        // Add remaining lines to description
        if (!empty($remainingLines)) {
            $metadata["ota_comments"] = implode("\n", $remainingLines);
        }

        // Handle special statuses based on SUMMARY
        $status = strtolower($metadata["status"] ?? "");
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
            $metadata["status"] = $status;
        }

        // Prepend status to summary for cancelled/deleted bookings
        switch ($status) {
            case "cancelled":
            case "cancelled_by_owner":
            case "cancelled_by_guest":
            case "deleted":
            case "vanished":
                if (!preg_match("/{$status}/", $summary)) {
                    $summary = "[{$status}] {$summary}";
                }
                break;
        }

        // Get fillable fields from Booking model (exclude system fields)
        $bookingModel = new \App\Models\Booking();
        $fillable = $bookingModel->getFillable();
        $systemFields = [
            "id",
            "slug",
            "created_at",
            "updated_at",
            "deleted_at",
            "sync_data",
        ];
        $modelFields = array_diff($fillable, $systemFields);

        // Build $processed array
        $processed = [];

        // Add standard iCal fields
        $processed["guest_name"] = $summary;
        $processed["check_in"] = $checkIn; // Y-m-d format
        $processed["check_out"] = $checkOut; // Y-m-d format
        $processed["uid"] = $event["UID"];
        $processed["unit_id"] = $source->unit_id;
        $processed["source_name"] = $source->name ?? "undefined";

        // Separate metadata into model fields vs additional metadata
        $processed["metadata"] = [];
        foreach ($metadata as $key => $value) {
            if (in_array($key, $modelFields)) {
                $processed[$key] = $value;
            } else {
                $processed["metadata"][$key] = $value;
            }
        }

        return $processed;
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

            // Parse event to get standardized $processed array
            // This is format-specific (iCal) and handles all data mapping
            try {
                $processed = self::parse($event, $source);
            } catch (\InvalidArgumentException $e) {
                // Skip events with invalid dates
                continue;
            }

            // Extract for convenience
            $status = $processed["status"] ?? "undefined";

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

            // Calculate checksum of processed data to detect source changes
            $newChecksum = $this->calculateChecksum($processed);

            // Check if data has changed at source (compare checksums)
            $hasSourceChanged = true;
            if ($booking && isset($booking->sync_data[$sourceId])) {
                $oldChecksum =
                    $booking->sync_data[$sourceId]["checksum"] ?? null;
                // $hasSourceChanged = $newChecksum !== $oldChecksum;
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
                // Check if existing sync_data is complete (has all essential fields)
                $oldProcessed =
                    $booking->sync_data[$sourceId]["processed"] ?? [];
                $isIncompleteBaseline =
                    empty($oldProcessed) ||
                    !isset($oldProcessed["guest_name"]) ||
                    !isset($oldProcessed["check_in"]) ||
                    !isset($oldProcessed["check_out"]);

                if ($isIncompleteBaseline) {
                    // Old/incomplete sync_data format - force full update without three-way merge
                    $booking->fill($newData);

                    // Update sync_data with new complete structure
                    $booking->sync_data = array_merge(
                        $booking->sync_data ?? [],
                        [
                            $sourceId => [
                                "raw" => $event,
                                "processed" => $processed,
                                "checksum" => $newChecksum,
                                "synced_at" => now()->toIso8601String(),
                            ],
                        ],
                    );

                    $booking->save();
                    $stats["updated"]++;
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
    protected function calculateChecksum(
        array $data,
        array $excludeFields = [],
    ): string {
        // Remove excluded fields
        $dataForChecksum = array_diff_key($data, array_flip($excludeFields));

        // Sort keys for deterministic result
        ksort($dataForChecksum);

        // Convert to JSON and calculate MD5
        return md5(json_encode($dataForChecksum, JSON_UNESCAPED_UNICODE));
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
    protected static function decodeIcalText(string $text): string
    {
        // Decode escape sequences
        $text = str_replace('\\n', "\n", $text); // \n -> actual newline
        $text = str_replace("\\,", ",", $text); // \, -> ,
        $text = str_replace("\\;", ";", $text); // \; -> ;
        $text = str_replace("\\\\", "\\", $text); // \\ -> \

        return $text;
    }
}
