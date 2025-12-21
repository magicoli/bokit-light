<?php

namespace App\Models;

use App\Traits\TimezoneTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingCalculation extends Model
{
    use TimezoneTrait;

    protected $fillable = [
        'booking_id',
        'total_amount',
        'base_amount',
        'calculation_snapshot',
    ];

    protected $casts = [
        'calculation_snapshot' => 'array',
    ];

    /**
     * Get the booking for this calculation
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}