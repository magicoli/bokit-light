<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourceMapping extends Model
{
    protected $fillable = ["booking_id", "control_string"];

    /**
     * Get the booking that owns this source mapping
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Find booking from event
     * - using source control string if provided
     * - or checkin, checkout and unit as fallback.
     *
     * @param string $controlString
     * @param array $eventData
     * @return array [
     *     'success' => bool,
     *     'booking' => Booking|null,
     *     'score' => float,
     *     'error' => string|null,
     * ]
     */
    public static function findBookingFromEvent(
        string $controlString,
        array $eventData,
    ): array {
        return [
            "success" => false,
            "booking" => null,
            "score" => false,
            "error" => "Not implemented",
        ];
        // Implement logic to find booking from event data
        // Return array ['booking' => Booking|null, 'mapping' => SourceMapping|null, 'matchType' => string]
    }

    /**
     * Calculate control string for source mapping
     *
     * @param string $sourceType
     * @param int $sourceId
     * @param string $sourceEventId
     * @param int $propertyId
     * @return string Control string
     */
    public static function calculateControlString(
        string $sourceType,
        int $sourceId,
        string $sourceEventId,
        int $propertyId,
    ): string {
        return sprintf(
            "%s:%d:%s:%d",
            $sourceType,
            $sourceId,
            $sourceEventId,
            $propertyId,
        );
    }

    /**
     * Get all bookings that should be checked for "vanished" status
     * for a specific source
     *
     * @param array $currentControlStrings Current control strings that are still valid
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function getBookingsForVanishedCheck(
        array $currentControlStrings,
    ) {
        return self::whereNotIn(
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
