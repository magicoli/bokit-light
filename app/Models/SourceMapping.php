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
