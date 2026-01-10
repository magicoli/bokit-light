<?php

namespace App\Traits;

use App\Support\Options;
use Carbon\Carbon;
use DateTime;
use DateTimeZone;
use Illuminate\Support\Facades\Cache;

trait TimezoneTrait
{
    /**
     * Get cached HTML <option> elements for all timezones
     * Used in select dropdowns across the app (User, Property, Unit, etc.)
     *
     * @param string|null $selected Currently selected timezone
     * @return string HTML options
     */
    public static function timezoneOptionsHtml(?string $selected = null): string
    {
        // Generate cache key including selected value to cache different states
        $cacheKey = "timezone_options_html";

        // Get base HTML (without selection) from cache
        $baseHtml = Cache::remember($cacheKey, 86400 * 30, function () {
            $timezones = timezone_identifiers_list();
            $options = [];
            foreach ($timezones as $timezone) {
                $options[] = sprintf(
                    '<option value="%s">%s</option>',
                    e($timezone),
                    e($timezone),
                );
            }
            return implode("\n", $options);
        });

        // If no selection needed, return cached HTML as-is
        if (!$selected) {
            return $baseHtml;
        }

        // Add 'selected' attribute to the correct option
        return str_replace(
            'value="' . e($selected) . '"',
            'value="' . e($selected) . '" selected',
            $baseHtml,
        );
    }
    /**
     * Get the timezone for this model
     *
     * Default implementation: uses site-wide display_timezone option
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
        $tzString = Options::get("timezone", config("app.timezone", "UTC"));
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
    public function formatDate(
        $date,
        string $format = "d/m/Y",
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
            "short" => $date->translatedFormat("d/m/y H:i"), // 21/12/2025 20:30
            "medium" => $date->translatedFormat(
                "J  M Y H:i",
            ), // 21/12/2025 20:30
            "date" => $date->translatedFormat("j F Y"), // 21 December 2025
            "date_short" => $date->translatedFormat("d/m/y"), // 21/12/25
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
     * Format a datetime for display (alias with different default)
     *
     * @param Carbon|string $date
     * @param string $format Use 'long', 'short', or Carbon format string
     * @param bool $showTimezone Whether to append timezone indicator
     * @return string
     */
    public function formatDateTime(
        $date,
        string $format = "d/m/Y H:i",
        bool $showTimezone = false,
    ): string {
        return $this->formatDate($date, $format, $showTimezone);
    }

    /**
     * Format a date range intelligently based on context
     *
     * @param Carbon $start
     * @param Carbon $end
     * @param string|bool $format 'short', 'medium', 'long', or boolean (false=long, true=short)
     * @return string
     */
    public static function dateRange($start, $end, $format = "long"): string
    {
        // Handle boolean format
        if (is_bool($format)) {
            $format = $format ? "short" : "long";
        }

        $sameMonth =
            $start->month === $end->month && $start->year === $end->year;
        $sameYear = $start->year === $end->year;

        return match ($format) {
            "short" => self::dateRangeShort(
                $start,
                $end,
                $sameMonth,
                $sameYear,
            ),
            "medium" => self::dateRangeMedium(
                $start,
                $end,
                $sameMonth,
                $sameYear,
            ),
            default => self::dateRangeLong(
                $start,
                $end,
                $sameMonth,
                $sameYear,
            ),
        };
    }

    /**
     * Short format: 21-28/12 or 29/12-04/01
     */
    private static function dateRangeShort(
        $start,
        $end,
        $sameMonth,
        $sameYear,
    ): string {
        if ($sameMonth) {
            // 21-28/12
            return $start->translatedFormat("j") .
                "-" .
                $end->translatedFormat("j/m");
        } else {
            // 29/12-04/01
            return $start->translatedFormat("j/m") .
                "-" .
                $end->translatedFormat("j/m");
        }
    }

    /**
     * Medium format: 21 - 28 Dec 2025 or 29 Dec 2025 - 4 Jan
     */
    private static function dateRangeMedium(
        $start,
        $end,
        $sameMonth,
        $sameYear,
    ): string {
        if ($sameMonth) {
            // 21 - 28 Dec 2025
            return $start->translatedFormat("j") .
                " - " .
                $end->translatedFormat("j M Y");
        } elseif ($sameYear) {
            // 29 Dec 2025 - 4 Jan
            return $start->translatedFormat("j M Y") .
                " - " .
                $end->translatedFormat("j M");
        } else {
            // 29 Dec 2025 - 4 Jan 2026
            return $start->translatedFormat("j M Y") .
                " - " .
                $end->translatedFormat("j M Y");
        }
    }

    /**
     * Long format: 21 - 28 December 2025 or 29 December 2025 - 4 January 2026
     */
    private static function dateRangeLong(
        $start,
        $end,
        $sameMonth,
        $sameYear,
    ): string {
        if ($sameMonth) {
            // 21 - 28 December 2025
            return $start->translatedFormat("j") .
                " - " .
                $end->translatedFormat("j F Y");
        } elseif ($sameYear) {
            // 29 December 2025 - 4 January
            return $start->translatedFormat("j F Y") .
                " - " .
                $end->translatedFormat("j F");
        } else {
            // 29 December 2025 - 4 January 2026
            return $start->translatedFormat("j F Y") .
                " - " .
                $end->translatedFormat("j F Y");
        }
    }

    function fixTimezone($date): string
    {
        if (is_string($date)) {
            $date = Carbon::parse($date);
        }

        return $date->shiftTimezone($this->timezone());
    }

    function shiftAndFormat(
        $date,
        string $format = "c",
        bool $showTimezone = false,
    ): string {
        if (is_string($date)) {
            $date = Carbon::parse($date);
        }

        $date = $date->shiftTimezone($this->timezone());
        return $this->formatDate($date, $format, $showTimezone);
    }
}
