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
        'base_rate',
        'reference_rate_id',
        'calculation_formula',
        'is_active',
        'priority',
        'booking_from',
        'booking_to',
        'stay_from',
        'stay_to',
        'conditions',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

/**
     * Get the unit that owns this rate
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the property for this rate
     */
    public function rateProperty(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Get the reference rate
     */
    public function referenceRate(): BelongsTo
    {
        return $this->belongsTo(Rate::class, 'reference_rate_id');
    }

    /**
     * Get the display name for this rate
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->calculateDisplayName();
    }

    /**
     * Calculate display name automatically
     */
    private function calculateDisplayName(): string
    {
        $parts = [];

        if ($this->rateProperty) {
            $parts[] = $this->rateProperty->name;
        }

        if ($this->unit_type) {
            $parts[] = $this->unit_type;
        }

        if ($this->unit) {
            $parts[] = $this->unit->name;
        }

        if (empty($parts)) {
            $parts[] = 'Standard';
        }

        $baseName = implode('-', $parts);

        // Add unique identifier if same name exists
        return $baseName . ' #' . $this->id;
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

    /**
     * Scope to get rates with reference rates for a property
     */
    public function scopeWithReferenceOptions($query, $propertyId)
    {
        return $query->where('property_id', $propertyId)
                    ->orWhereNull('property_id');
    }
}