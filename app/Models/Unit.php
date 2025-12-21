<?php

namespace App\Models;

use App\Traits\TimezoneTrait;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use TimezoneTrait;

    protected $fillable = [
        "property_id",
        "slug",
        "name",
        "description",
        "is_active",
        "settings",
    ];

    protected $casts = [
        "is_active" => "boolean",
        "settings" => "array",
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

    /**
     * Get the full name including property and unit
     */
    public function fullname(): string
    {
        return trim("{$this->property->name} {$this->name}");
    }
}
