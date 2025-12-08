<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'color',
        'capacity',
        'settings',
        'is_active',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the iCal sources for this property
     */
    public function icalSources(): HasMany
    {
        return $this->hasMany(IcalSource::class);
    }

    /**
     * Get the bookings for this property
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Get active iCal sources
     */
    public function activeIcalSources(): HasMany
    {
        return $this->icalSources()->where('sync_enabled', true);
    }

    /**
     * Scope to get only active properties
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
