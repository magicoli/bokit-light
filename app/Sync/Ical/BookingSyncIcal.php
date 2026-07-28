<?php

namespace App\Sync\Ical;

use App\Models\Booking;

class BookingSyncIcal
{
    /**
     * Parse iCal event to extract all booking data
     *
     * This method handles all iCal-specific parsing and returns a standardized
     * $processed array ready for database storage. It's the single source of truth
     * for how iCal data maps to Booking model fields.
     *
     * @param  array  $event  iCal event with SUMMARY, DTSTART, DTEND, DESCRIPTION, etc.
     * @param  object  $source  IcalSource model instance
     * @return array Standardized $processed array ready for storage
     */
    public static function parse(array $event, object $source): array
    {
        // Parse iCal dates - no timezone conversion, save as-is
        // Dates will be converted to unit timezone on read via Booking accessors
        if (!isset($event['DTSTART']) || !isset($event['DTEND'])) {
            throw new \InvalidArgumentException('Missing dates in iCal event');
        }

        $checkIn = $event['DTSTART']; // e.g., "20260110"
        $checkOut = $event['DTEND']; // e.g., "20260117"

        if (!$checkIn || !$checkOut) {
            throw new \InvalidArgumentException('Invalid dates in iCal event');
        }

        // Decode and parse DESCRIPTION field
        $description = self::decodeIcalText($event['DESCRIPTION'] ?? '');
        $summary = self::decodeIcalText($event['SUMMARY'] ?? 'Unknown Guest');

        $metadata = [];
        $remainingLines = [];

        $lines = explode("\n", $description);

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line)) {
                continue;
            }

            // Try to match "KEY: value" pattern
            if (preg_match('/^([A-Z][A-Z0-9]*)\s*:\s*(.*)$/i', $line, $matches)) {
                $key = strtolower($matches[1]);
                $value = trim($matches[2]);

                // Only store if value is not empty
                if ($value !== '') {
                    // Special handling for specific fields
                    switch ($key) {
                        case 'status':
                            $parts = explode('/', $value);
                            $metadata['status'] = strtolower($parts[0] ?? null);
                            $metadata['group_id'] = $parts[1] ?? null;
                            break;

                        case 'guests':
                            // Split guests/adult/child
                            $parts = explode('/', $value);
                            $metadata['guests'] = (int) $parts[0];
                            $metadata['adults'] = (int) ($parts[1] ?? 0);
                            $metadata['children'] = (int) ($parts[2] ?? 0);
                            break;

                        case 'adult':
                        case 'adults':
                            $metadata['adults'] = (int) $value;
                            break;

                        case 'child':
                        case 'children':
                            $metadata['children'] = (int) $value;
                            break;

                        case 'time':
                            $metadata['arrival_time'] = $value;
                            break;

                        case 'phone':
                            $parts = explode('/', $value);
                            $metadata['phone'] = $parts[0] ?? null;
                            $metadata['mobile'] = $parts[1] ?? null;
                            break;

                        case 'mobile':
                            $metadata['mobile'] = $value;
                            break;

                        case 'email':
                            $metadata['email'] = $value;
                            break;

                        case 'ctry':
                        case 'country':
                        case 'country2':
                            $metadata['country'] = $value;
                            break;

                        case 'comments':
                            $metadata['ota_comments'] = $value;
                            break;

                        case 'notes':
                            $metadata['notes'] = $value;
                            break;

                        case 'ota':
                            // Split "VRBO 123456" or "VRBO/123456" into source and ref
                            $value = str_replace(' ', '/', $value);
                            $parts = explode('/', $value, 2);
                            $metadata['api_source'] = $parts[0];
                            if (isset($parts[1])) {
                                $metadata['api_ref'] = $parts[1];
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
         * // KEEP THIS NOTE until properly implemented.
         * // Current method of adding + is only a workaround, it is not proper phone normalization
         * //
         * // TODO: properly normalize phone numbers, see if Laravel-Phone package allow this
         * // https://github.com/Propaganistas/Laravel-Phone
         * $phone = new PhoneNumber('012/34.56.78', 'BE');
         * $phone->format($format);       // See libphonenumber\PhoneNumberFormat
         * $phone->formatE164();          // +3212345678
         * $phone->formatInternational(); // +32 12 34 56 78
         * $phone->formatRFC3966();       // tel:+32-12-34-56-78
         * $phone->formatNational();      // 012 34 56 78
         */
        // For now, assume if it contains only numbers and doesn't start with a zero, it's
        // missing the plus sign.
        if (isset($metadata['phone'])) {
            $metadata['phone'] = preg_replace('/^([1-9][0-9]+)$/', '+$1', $metadata['phone']);
        }
        if (isset($metadata['mobile'])) {
            $metadata['mobile'] = preg_replace('/^([1-9][0-9]+)$/', '+$1', $metadata['mobile']);
        }

        // Add remaining lines to description
        if (!empty($remainingLines)) {
            $metadata['ota_comments'] = implode("\n", $remainingLines);
        }

        // Handle special statuses based on SUMMARY
        $status = strtolower($metadata['status'] ?? '');
        if (empty($status)) {
            switch ($summary) {
                case 'Unavailable':
                case 'Airbnb (Not available)':
                    $status = 'unavailable';
                    $summary = __('Unavailable');
                    break;
                case 'Reserved':
                    $status = 'confirmed';
                    $summary = __('Reserved (Airbnb)');
                    break;
                default:
                    $status = 'undefined';
            }
            $metadata['status'] = $status;
        }

        // Prepend status to summary for cancelled/deleted bookings
        switch ($status) {
            case 'cancelled':
            case 'cancelled_by_owner':
            case 'cancelled_by_guest':
            case 'deleted':
            case 'vanished':
                if (!preg_match("/{$status}/", $summary)) {
                    $summary = "[{$status}] {$summary}";
                }
                break;
        }

        // Get fillable fields from Booking model (exclude system fields)
        $bookingModel = new Booking();
        $fillable = $bookingModel->getFillable();
        $systemFields = [
            'id',
            'slug',
            'created_at',
            'updated_at',
            'deleted_at',
            'sync_data',
        ];
        $modelFields = array_diff($fillable, $systemFields);

        // Build $processed array
        $processed = [];

        // Add standard iCal fields
        $processed['guest_name'] = $summary;
        $processed['check_in'] = $checkIn; // Y-m-d format
        $processed['check_out'] = $checkOut; // Y-m-d format
        $processed['uid'] = $event['UID'];
        $processed['unit_id'] = $source->unit_id;
        $processed['source_name'] = $source->name ?? 'undefined';

        // Separate metadata into model fields vs additional metadata
        $processed['metadata'] = [];
        foreach ($metadata as $key => $value) {
            if (in_array($key, $modelFields)) {
                $processed[$key] = $value;
            } else {
                $processed['metadata'][$key] = $value;
            }
        }

        return $processed;
    }

    /**
     * Parse iCal content and extract events
     */
    public function parseIcal(string $content): array
    {
        $events = [];
        $lines = explode("\n", $content);
        $currentEvent = null;
        $currentField = null;
        $currentValue = '';

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
            if (strpos($line, ':') !== false) {
                [$field, $value] = explode(':', $line, 2);

                // Remove parameters (e.g., DTSTART;VALUE=DATE)
                $field = preg_replace('/;.*$/', '', $field);

                $currentField = $field;
                $currentValue = $value;

                // Start new event
                if ($field === 'BEGIN' && $value === 'VEVENT') {
                    $currentEvent = [];
                }

                // End current event
                if ($field === 'END' && $value === 'VEVENT' && $currentEvent !== null) {
                    $events[] = $currentEvent;
                    $currentEvent = null;
                    $currentField = null;
                    $currentValue = '';
                }
            }
        }

        return $events;
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
        $text = str_replace('\\,', ',', $text); // \, -> ,
        $text = str_replace('\\;', ';', $text); // \; -> ;
        $text = str_replace('\\\\', '\\', $text); // \\ -> \

        return $text;
    }
}
