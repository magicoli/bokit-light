<?php

namespace App\Services;

class BookingMetadataParser
{
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
                            $parts = explode("/", $value);
                            $metadata["status"] = $parts[0] ?? null;
                            $metadata["group_id"] = $parts[1] ?? null;
                            break;

                        case "guests":
                            // Split guests/adult/child
                            $parts = explode("/", $value);
                            $metadata["guests"] = (int) $parts[0];
                            $metadata["adults"] = (int) $parts[1] ?? null;
                            $metadata["children"] = (int) $parts[2] ?? null;
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
                            $metadata[$key] = $value;
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
                            $metadata["guest_comments"] = $value;
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

                        case "time":
                            $metadata["time"] = $value;
                            break;

                        default:
                            // Store any other KEY: value pairs
                            $metadata[$key] = $value;
                    }
                }

                /*
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
                // missing the plus sign
                $metadata["phone"] = preg_replace(
                    '/^([1-9][0-9]+)$/',
                    '+$1',
                    $metadata["phone"] ?? "",
                );
                $metadata["mobile"] = preg_replace(
                    '/^([1-9][0-9]+)$/',
                    '+$1',
                    $metadata["mobile"] ?? "",
                );
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
            "cancelled" => "#88888840", // Gray 50%
            "vanished" => "#88888840", // Gray 50%
            "inquiry" => "#f59e0b80", // Orange 50%
            "request" => "#f59e0bc0", // Orange - booking requests
            "new" => "#3b82f6", // Blue - new/pending bookings
            "confirmed" => "#10b981", // Green - confirmed bookings
            "blocked" => "#000000c0", // Black 50%
            "unavailable" => "#00000080", // Black 50%
        ];

        return $colors[$status] ?? "#888888";
    }

    /**
     * DEPRECATED Get human-readable status label
     *
     * @param string|null $status
     * @return string
     */
    // public static function getStatusLabel(?string $status): string
    // {
    //     $labels = [
    //         "inquiry" => __("app.status.inquiry"), // Non-blocking
    //         "request" => __("app.status.request"), // Blocking (TODO: add a deadline logic to release the dates)
    //         "new" => __("app.status.new"), // Blocking
    //         "confirmed" => __("app.status.confirmed"), // Blocking
    //         "cancelled_by_owner" => __("app.status.cancelled_by_owner"), // Non-blocking
    //         "cancelled_by_guest" => __("app.status.cancelled_by_guest"), // Non-blocking (TODO: add a deadline logic to hide the booking)
    //         "vanished" => __("app.status.vanished"), // Non-blocking (TODO: add a deadline logic to hide the booking)
    //         "deleted" => __("app.status.deleted"), // Non-blocking, not shown in the UI
    //         "blocked" => __("app.status.blocked"), // Blocking
    //         "unavailable" => __("app.status.unavailable"), // Blocking
    //         "undefined" => __("app.status.undefined"), // Blocking
    //     ];

    //     $status = strtolower($status ?? "undefined");

    //     return $labels[$status] ?? "Unknown " . ucfirst($status);
    // }
}
