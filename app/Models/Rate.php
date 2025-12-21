<?php

namespace App\Models;

use App\Traits\TimezoneTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rate extends Model
{
    use TimezoneTrait;

    protected $fillable = [
        'name',
        'slug',
        'unit_id',
        'unit_type',
        'property_id',
        'base_amount',
        'calculation_formula',
        'is_active',
        'priority',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    /**
     * Get the unit for this rate
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the property for this rate
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Scope to get only active rates
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get rates for a specific unit
     */
    public function scopeForUnit($query, $unitId)
    {
        return $query->where('unit_id', $unitId);
    }

    /**
     * Scope to get rates for a specific unit type
     */
    public function scopeForUnitType($query, $unitType)
    {
        return $query->where('unit_type', $unitType);
    }

    /**
     * Scope to get rates for a specific property
     */
    public function scopeForProperty($query, $propertyId)
    {
        return $query->where('property_id', $propertyId);
    }
}