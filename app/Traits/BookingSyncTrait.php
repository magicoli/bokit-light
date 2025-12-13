<?php

namespace App\Traits;

use App\Contracts\BookingSyncInterface;

trait BookingSyncTrait
{
    /**
     * Calculate control string using the default implementation
     * This can be overridden by specific sync classes if needed
     *
     * @param mixed ...$args Arguments for control string calculation
     * @return string Control string value
     */
    public static function calculateControlString(...$args): string
    {
        // Default implementation that can be used by all sync classes
        $stringParts = [];

        foreach ($args as $arg) {
            $stringParts[] = self::normalizeArgument($arg);
        }

        return implode(":", $stringParts);
    }

    /**
     * Normalize an argument to string format for control string
     *
     * @param mixed $arg Argument to normalize
     * @return string Normalized string
     */
    protected static function normalizeArgument($arg): string
    {
        if (is_int($arg) || is_float($arg)) {
            return (string) $arg;
        }

        if (is_string($arg)) {
            return $arg;
        }

        if (is_bool($arg)) {
            return $arg ? "1" : "0";
        }

        if (is_array($arg)) {
            return md5(json_encode($arg));
        }

        if (is_object($arg) && method_exists($arg, "__toString")) {
            return (string) $arg;
        }

        return md5(serialize($arg));
    }

    /**
     * Find or create a source mapping for this sync event
     *
     * @param int $bookingId Booking ID to map to
     * @return array ['mapping' => SourceMapping, 'isNew' => bool]
     */
    protected function findOrCreateSourceMapping(int $bookingId): array
    {
        $controlString = $this->getControlString();

        // Try to find existing mapping
        $mapping = \App\Models\SourceMapping::where(
            "control_string",
            $controlString,
        )->first();

        if ($mapping) {
            return ["mapping" => $mapping, "isNew" => false];
        }

        // Create new mapping
        $mapping = \App\Models\SourceMapping::create([
            "booking_id" => $bookingId,
            "control_string" => $controlString,
        ]);

        return ["mapping" => $mapping, "isNew" => true];
    }

    /**
     * Find booking by control string with priority-based matching
     *
     * @return array ['booking' => Booking|null, 'mapping' => SourceMapping|null, 'matchType' => string]
     */
    protected function findBookingWithPriority(): array
    {
        $controlString = $this->getControlString();

        // Priority 1: Direct mapping via control string (most efficient)
        $mapping = \App\Models\SourceMapping::where(
            "control_string",
            $controlString,
        )->first();

        if ($mapping && $mapping->booking) {
            return [
                "booking" => $mapping->booking,
                "mapping" => $mapping,
                "matchType" => "direct_control_match",
            ];
        }

        // Priority 2: Fallback to date/unit/email matching if needed
        // This would be implemented by specific sync classes
        return [
            "booking" => null,
            "mapping" => null,
            "matchType" => "no_match",
        ];
    }

    /**
     * Find bookings that should be checked for "vanished" status
     * for this sync source
     *
     * @param array $currentEventIds Current event IDs that are still valid
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function getVanishedBookings(
        array $currentEventIds,
    ): \Illuminate\Database\Eloquent\Builder {
        // Build current control strings for this source
        $currentControlStrings = [];
        foreach ($currentEventIds as $eventId) {
            $currentControlStrings[] = $this->calculateControlString(
                $this->sourceType,
                $this->sourceId,
                $eventId,
                $this->propertyId,
            );
        }

        return \App\Models\SourceMapping::whereNotIn(
            "control_string",
            $currentControlStrings,
        )->whereHas("booking", function ($query) {
            $query
                ->where("check_out", ">=", now()->format("Y-m-d"))
                ->whereNotIn("status", [
                    "cancelled",
                    "cancelled_by_owner",
                    "cancelled_by_guest",
                    "vanished",
                ]);
        });
    }
}
