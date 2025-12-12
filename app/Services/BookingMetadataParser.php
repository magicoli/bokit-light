<?php

namespace App\Services;

class BookingMetadataParser
{
    /**
     * Parse structured metadata from iCal description
     *
     * Expected format from Beds24:
     * STATUS: Confirmed
     * GUESTS: 2
     * ADULT: 2
     * CHILD: 0
     * TIME: 14:00
     * PHONE: 1234567890
     * MOBILE: 0987654321
     * COUNTRY: US
     * COMMENTS: Special requests here
     * OTA: VRBO 123456
     * Any remaining text...
     *
     * @param string $description Raw iCal DESCRIPTION field
     * @return array ['metadata' => [...], 'notes' => '...']
     */
    public static function parse(string $description): array
    {
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
                            $metadata["status"] = $value;
                            break;

                        case "guests":
                        case "adult":
                        case "child":
                            $metadata[$key] = (int) $value;
                            break;

                        case "time":
                            $metadata["arrival_time"] = $value;
                            break;

                        case "phone":
                        case "mobile":
                            $metadata[$key] = $value;
                            break;

                        case "email":
                            $metadata["email"] = $value;
                            break;

                        case "country":
                        case "country2":
                            $metadata["country"] = $value;
                            break;

                        case "comments":
                            $metadata["guest_comments"] = $value;
                            break;

                        case "ota":
                            // Split "VRBO 123456" into source and ref
                            $parts = explode(" ", $value, 2);
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
                // Not a "KEY: value" line, keep it as notes
                $remainingLines[] = $line;
            }
        }

        return [
            "metadata" => $metadata,
            "notes" => implode("\n", $remainingLines),
        ];
    }

    /**
     * Get color for booking status
     *
     * @param string|null $status
     * @return string Hex color code
     */
    public static function getStatusColor(?string $status): string
    {
        $status = strtolower($status ?? "");
        $colors = [
            "cancelled" => "#88888880", // Gray 50%
            "vanished" => "#88888880", // Gray 50%
            "inquiry" => "#f59e0b80", // Orange 50%
            "request" => "#f59e0b", // Orange - booking requests
            "new" => "#3b82f6", // Blue - new/pending bookings
            "confirmed" => "#10b981", // Green - confirmed bookings
            "blocked" => "#0000080", // Black 50%
            "unavailable" => "#0000080", // Black 50%
        ];

        return $colors[$status] ?? "#888888";
    }

    /**
     * Get human-readable status label
     *
     * @param string|null $status
     * @return string
     */
    public static function getStatusLabel(?string $status): string
    {
        $labels = [
            "inquiry" => __("Inquiry"), // Non-blocking
            "request" => __("Request"), // Blocking (TODO: add a deadline logic to release the dates)
            "new" => __("New"), // Blocking
            "confirmed" => __("Confirmed"), // Blocking
            "cancelled_by_owner" => __("Cancelled by Owner"), // Non-blocking
            "cancelled_by_guest" => __("Cancelled by Guest"), // Non-blocking (TODO: add a deadline logic to hide the booking)
            "vanished" => __("Vanished"), // Non-blocking (TODO: add a deadline logic to hide the booking)
            "deleted" => __("Deleted"), // Non-blocking, not shown in the UI
            "blocked" => __("Blocked"), // Blocking
            "unavailable" => __("Unavailable"), // Blocking
            "undefined" => __("Undefined"), // Blocking
        ];

        $status = strtolower($status ?? "undefined");

        return $labels[$status] ?? ucfirst($status);
    }
}
