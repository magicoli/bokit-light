<?php

namespace App\Traits;

use App\Support\Options;
use Carbon\Carbon;
use DateTime;
use DateTimeZone;

trait TimezoneTrait
{
    /**
     * Get the timezone for this model
     *
     * Default implementation: uses site-wide display.timezone option
     * Each model should override this method to define its own hierarchy
     *
     * Examples:
     * - Unit: unit.timezone > property.timezone > site > app
     * - Booking: booking.timezone > unit.timezone > property.timezone > site > app
     * - User: user.timezone > site > app
     * - Property: property.timezone > site > app
     */
    public function timezone($short = false): string
    {
        // Check if model has its own timezone column
        if (
            isset($this->attributes["timezone"]) &&
            !empty($this->attributes["timezone"])
        ) {
            $tzString = $this->attributes["timezone"];
        } else {
            $tzString = self::defaultTimezone($short);
        }

        if ($short) {
            $tzString = self::timezoneShort($tzString);
        }

        return $tzString;
    }

    public static function defaultTimezone($short = false): string
    {
        $tzString = Options::get(
            "display.timezone",
            config("app.timezone", "UTC"),
        );
        if ($short) {
            $tzString = self::timezoneShort($tzString);
        }
        return $tzString;
    }

    public static function timezoneShort($tzString): string
    {
        $dt = new DateTime(null, new DateTimeZone($tzString));
        return $dt->format("T");
    }

    /**
     * Format a date for display in this model's timezone
     *
     * @param Carbon|string $date
     * @param string $format Use 'long', 'short', 'date', 'time', or Carbon format string
     * @param bool $showTimezone Whether to append timezone indicator
     * @return string
     */
    public function displayDate(
        $date,
        string $format = "short",
        bool $showTimezone = false,
    ): string {
        if (is_string($date)) {
            $date = Carbon::parse($date);
        }

        $date = $date->timezone($this->timezone());

        // Predefined formats using locale
        $formatted = match ($format) {
            "long" => $date->translatedFormat(
                "l j F Y H:i",
            ), // Monday 21 December 2025 20:30
            "short" => $date->translatedFormat(
                "D j M Y H:i",
            ), // Mon 21 Dec 2025 20:30
            "date" => $date->translatedFormat("j F Y"), // 21 December 2025
            "date_short" => $date->translatedFormat("j M Y"), // 21 Dec 2025
            "time" => $date->format("H:i"), // 20:30
            "day" => $date->translatedFormat("l j F"), // Monday 21 December
            "month" => $date->translatedFormat("F Y"), // December 2025
            default => $date->format($format), // Custom format
        };

        if ($showTimezone) {
            $formatted .= " (" . $this->timezone() . ")";
        }

        return $formatted;
    }
}
