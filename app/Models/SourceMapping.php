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
     * Find booking by source identifiers with priority-based matching
     *
     * @param string $sourceType
     * @param int $sourceId
     * @param string $sourceEventId
     * @param int $propertyId
     * @param string|null $guestEmail
     * @param string|null $checkIn
     * @param string|null $checkOut
     * @param int|null $unitId
     * @return array ['booking' => Booking|null, 'mapping' => SourceMapping|null, 'matchType' => string]
     */
    public static function findBookingWithPriority(
        string $sourceType,
        int $sourceId,
        string $sourceEventId,
        int $propertyId,
        ?string $guestEmail = null,
        ?string $checkIn = null,
        ?string $checkOut = null,
        ?int $unitId = null,
    ): array {
        // Calculate the control value
        $controlString = self::calculateControlString(
            $sourceType,
            $sourceId,
            $sourceEventId,
            $propertyId,
        );

        // Priority 1: Direct mapping via control string (most efficient)
        $mapping = self::where("control_string", $controlString)->first();

        if ($mapping && $mapping->booking) {
            return [
                "booking" => $mapping->booking,
                "mapping" => $mapping,
                "matchType" => "direct_control_match",
            ];
        }

        // Priority 2: Same dates, same unit, same email - definite match
        if ($guestEmail && $checkIn && $checkOut && $unitId) {
            $booking = Booking::where("unit_id", $unitId)
                ->where("check_in", $checkIn)
                ->where("check_out", $checkOut)
                ->where(function ($query) use ($guestEmail) {
                    $query
                        ->whereJsonContains("raw_data->email", $guestEmail)
                        ->orWhere("notes", "like", "%" . $guestEmail . "%");
                })
                ->first();

            if ($booking) {
                return [
                    "booking" => $booking,
                    "mapping" => null,
                    "matchType" => "date_unit_email_match",
                ];
            }
        }

        // Priority 3: Same dates, same unit, no email - probable match
        if ($checkIn && $checkOut && $unitId) {
            $booking = Booking::where("unit_id", $unitId)
                ->where("check_in", $checkIn)
                ->where("check_out", $checkOut)
                ->first();

            if ($booking) {
                return [
                    "booking" => $booking,
                    "mapping" => null,
                    "matchType" => "date_unit_match",
                ];
            }
        }

        return [
            "booking" => null,
            "mapping" => null,
            "matchType" => "no_match",
        ];
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
