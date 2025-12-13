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
}
