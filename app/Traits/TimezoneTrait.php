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

    /**
     * Format a date range intelligently based on context
     *
     * @param Carbon $start
     * @param Carbon $end
     * @param string|bool $format 'short', 'medium', 'long', or boolean (false=long, true=short)
     * @return string
     */
    public static function dateRange($start, $end, $format = 'long'): string
    {
        // Handle boolean format
        if (is_bool($format)) {
            $format = $format ? 'short' : 'long';
        }

        $sameMonth = $start->month === $end->month && $start->year === $end->year;
        $sameYear = $start->year === $end->year;

        return match($format) {
            'short' => self::dateRangeShort($start, $end, $sameMonth, $sameYear),
            'medium' => self::dateRangeMedium($start, $end, $sameMonth, $sameYear),
            default => self::dateRangeLong($start, $end, $sameMonth, $sameYear),
        };
    }

    /**
     * Short format: 21-28/12 or 29/12-04/01
     */
    private static function dateRangeShort($start, $end, $sameMonth, $sameYear): string
    {
        if ($sameMonth) {
            // 21-28/12
            return $start->translatedFormat('j') . '-' . $end->translatedFormat('j/m');
        } else {
            // 29/12-04/01
            return $start->translatedFormat('j/m') . '-' . $end->translatedFormat('j/m');
        }
    }

    /**
     * Medium format: 21 - 28 Dec 2025 or 29 Dec 2025 - 4 Jan
     */
    private static function dateRangeMedium($start, $end, $sameMonth, $sameYear): string
    {
        if ($sameMonth) {
            // 21 - 28 Dec 2025
            return $start->translatedFormat('j') . ' - ' . $end->translatedFormat('j M Y');
        } elseif ($sameYear) {
            // 29 Dec 2025 - 4 Jan
            return $start->translatedFormat('j M Y') . ' - ' . $end->translatedFormat('j M');
        } else {
            // 29 Dec 2025 - 4 Jan 2026
            return $start->translatedFormat('j M Y') . ' - ' . $end->translatedFormat('j M Y');
        }
    }

    /**
     * Long format: 21 - 28 December 2025 or 29 December 2025 - 4 January 2026
     */
    private static function dateRangeLong($start, $end, $sameMonth, $sameYear): string
    {
        if ($sameMonth) {
            // 21 - 28 December 2025
            return $start->translatedFormat('j') . ' - ' . $end->translatedFormat('j F Y');
        } elseif ($sameYear) {
            // 29 December 2025 - 4 January
            return $start->translatedFormat('j F Y') . ' - ' . $end->translatedFormat('j F');
        } else {
            // 29 December 2025 - 4 January 2026
            return $start->translatedFormat('j F Y') . ' - ' . $end->translatedFormat('j F Y');
        }
    }
}
