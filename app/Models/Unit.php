<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $fillable = [
        'property_id',
        'name',
        'slug',
        'color',
        'capacity',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    /**
     * Get the property that owns this unit
     */
    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Get the iCal sources for this unit
     */
    public function icalSources()
    {
        return $this->hasMany(IcalSource::class);
    }

    /**
     * Get the bookings for this unit
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
