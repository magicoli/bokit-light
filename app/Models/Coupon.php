<?php

namespace App\Models;

use App\Traits\TimezoneTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Coupon extends Model
{
    use TimezoneTrait;

    protected $fillable = [
        "code",
        "name",
        "property_id",
        "discount_amount",
        "discount_type",
        "conditions",
        "is_active",
    ];

    protected $casts = [
        "is_active" => "boolean",
        "conditions" => "array",
    ];

    /**
     * Get the property for this coupon
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Scope to get only active coupons
     */
    public function scopeActive($query)
    {
        return $query->where("is_active", true);
    }

    /**
     * Scope to get coupons for a specific property
     */
    public function scopeForProperty($query, $propertyId)
    {
        return $query->where("property_id", $propertyId);
    }
}
