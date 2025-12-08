<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Booking extends Model
{
    protected $fillable = [
        'property_id',
        'uid',
        'source_name',
        'guest_name',
        'check_in',
        'check_out',
        'adults',
        'children',
        'price',
        'commission',
        'notes',
        'is_manual',
        'group_id',
        'raw_data',
    ];

    protected $casts = [
        'check_in' => 'date',
        'check_out' => 'date',
        'is_manual' => 'boolean',
        'raw_data' => 'array',
        'price' => 'decimal:2',
        'commission' => 'decimal:2',
    ];

    /**
     * Get the property that owns this booking
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Calculate the number of nights
     */
    public function nights(): int
    {
        return $this->check_in->diffInDays($this->check_out);
    }

    /**
     * Check if the booking is current (includes today)
     */
    public function isCurrent(): bool
    {
        $today = Carbon::today();
        return $this->check_in->lte($today) && $this->check_out->gt($today);
    }

    /**
     * Check if the booking is upcoming
     */
    public function isUpcoming(): bool
    {
        return $this->check_in->isFuture();
    }

    /**
     * Check if the booking is past
     */
    public function isPast(): bool
    {
        return $this->check_out->isPast();
    }

    /**
     * Scope to get bookings for a specific date range
     */
    public function scopeInRange($query, Carbon $start, Carbon $end)
    {
        return $query->where(function ($q) use ($start, $end) {
            $q->whereBetween('check_in', [$start, $end])
              ->orWhereBetween('check_out', [$start, $end])
              ->orWhere(function ($q2) use ($start, $end) {
                  $q2->where('check_in', '<=', $start)
                     ->where('check_out', '>=', $end);
              });
        });
    }

    /**
     * Scope to get manual bookings only
     */
    public function scopeManual($query)
    {
        return $query->where('is_manual', true);
    }

    /**
     * Scope to get imported bookings only
     */
    public function scopeImported($query)
    {
        return $query->where('is_manual', false);
    }
}
