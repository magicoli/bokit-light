<?php

namespace App\Models;

use App\Traits\AdminResourceTrait;
use App\Traits\TimezoneTrait;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use AdminResourceTrait;
    use TimezoneTrait;

    protected $fillable = [
        'property_id',
        'slug',
        'name',
        'description',
        'is_active',
        'options',
        'unit_type',
        'bedrooms',
        'max_guests',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'options' => 'array',
        'unit_type' => 'string',
    ];

    protected $appends = [];

    protected $list_columns = [
        'actions',
        'name',
        'property_id',
        'unit_type',
        'bedrooms',
        'max_guests',
        'is_active',
    ];

    protected static $icon = 'bed-outline';

    /**
     * Get the property that owns this unit
     */
    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * A unit never has its own timezone — it always uses its property's (a unit belongs to one
     * property, "par la force des choses"). Overrides TimezoneTrait's default, which would
     * otherwise look for a (nonexistent) timezone column on units itself.
     */
    public function timezone($short = false): string
    {
        return $this->property->timezone($short);
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

    /**
     * Get an option value with cascade: unit options -> property options -> global options
     *
     * @param  string  $key  Option key
     * @param  mixed  $default  Default value if not found anywhere
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $this->property->options($key, $default);
    }
}
