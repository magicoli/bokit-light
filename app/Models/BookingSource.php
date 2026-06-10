<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cross-reference between a booking and one external source.
 *
 * A booking can be known to several sources (Beds24 API, iCal feeds, HBook, …)
 * but has exactly one origin (is_origin = true), which is the only source
 * allowed to update dates, prices and other critical data. The
 * (source_key, external_id) pair is the authoritative match key.
 */
class BookingSource extends Model
{
    protected $fillable = [
        'booking_id',
        'source_type',
        'source_key',
        'external_id',
        'is_origin',
        'is_placeholder',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'is_origin' => 'boolean',
            'is_placeholder' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * True when this row was created from another source's origin hint and
     * no real source has claimed it yet.
     */
    public function isPlaceholder(): bool
    {
        return $this->is_placeholder;
    }
}
