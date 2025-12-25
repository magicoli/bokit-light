<?php

namespace App\Models;

use App\Traits\FormTrait;
use App\Traits\ListTrait;
use App\Traits\TimezoneTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rate extends Model
{
    use FormTrait;
    use ListTrait;
    use TimezoneTrait;

    protected $fillable = [
        "name",
        "property_id",
        "unit_type",
        "unit_id",
        "coupon_code",
        "base_rate",
        "reference_rate_id",
        "calculation_formula",
        "priority",
        "is_active",
        "booking_from",
        "booking_to",
        "stay_from",
        "stay_to",
        "conditions",
        "settings",
    ];

    protected $casts = [
        "is_active" => "boolean",
        "booking_from" => "date",
        "booking_to" => "date",
        "stay_from" => "date",
        "stay_to" => "date",
        "conditions" => "array",
        "settings" => "array",
    ];

    /**
     * Boot method - auto-generate slug
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($rate) {
            if (empty($rate->slug)) {
                $rate->slug = \Illuminate\Support\Str::slug(
                    ($rate->name ?: "rate") . "-" . uniqid(),
                );
            }
        });
    }

    /**
     * Validation rules for creating/updating rates
     */
    public static function validationRules(bool $forUpdate = false): array
    {
        $sometimes = $forUpdate ? "sometimes|" : "";

        return [
            "name" => "nullable|string|max:255",
            "property_id" => "{$sometimes}required|exists:properties,id",
            "unit_type" => "nullable|string|max:100",
            "unit_id" => "nullable|exists:units,id",
            "coupon_code" => "nullable|string|max:100",
            "base_rate" => "{$sometimes}required|numeric|min:0",
            "reference_rate_id" => "nullable|exists:rates,id",
            "calculation_formula" => "{$sometimes}required|string|max:500",
            "priority" => "nullable|in:high,normal,low",
            "is_active" => "nullable|boolean",
            "booking_from" => "nullable|date",
            "booking_to" => "nullable|date|after_or_equal:booking_from",
            "stay_from" => "nullable|date",
            "stay_to" => "nullable|date|after_or_equal:stay_from",
            "conditions" => "nullable|array",
        ];
    }

    /**
     * Form fields structure (hierarchical - reflects HTML structure)
     */
    public static function formFields(): array
    {
        return [
            "scope" => [
                "type" => "fields-row",
                "items" => [
                    "property_id" => [
                        "type" => "select",
                        "label" => __("app.property"),
                        "required" => true,
                    ],
                    "unit_type" => [
                        "type" => "select",
                        "label" => __("forms.unit_type"),
                        "required" => false,
                    ],
                    "unit_id" => [
                        "type" => "select",
                        "label" => __("forms.unit"),
                        "required" => false,
                    ],
                    "coupon_code" => [
                        "type" => "select",
                        "label" => __("forms.coupon"),
                        "required" => false,
                    ],
                ],
            ],

            "pricing-row" => [
                "type" => "fields-row",
                "items" => [
                    "base_rate" => [
                        "type" => "number",
                        "label" => __("rates.base_rate"),
                        "required" => true,
                        "attributes" => [
                            "step" => "0.01",
                            "suffix" => $unit_currency ?? "EUR", // Not yet implemented
                        ],
                    ],
                    "reference_rate_id" => [
                        "type" => "select",
                        "label" => __("rates.reference_rate"),
                        "required" => false,
                    ],
                    "calculation_formula" => [
                        "type" => "text",
                        "label" => __("rates.calculation_formula"),
                        "required" => true,
                        "default" => "booking_nights * base_rate",
                        "attributes" => [
                            "placeholder" => "booking_nights * base_rate",
                        ],
                    ],
                ],
            ],

            "dates" => [
                "type" => "fields-row",
                "items" => [
                    "booking_from" => [
                        "type" => "date",
                        "label" =>
                            __("rates.booking_date") .
                            " " .
                            __("forms.date_from"),
                        "required" => false,
                        "default" => "",
                        "attributes" => [
                            "autocomplete" => "off",
                        ],
                    ],
                    "booking_to" => [
                        "type" => "date",
                        "label" => __("forms.date_to"),
                        "required" => false,
                        "default" => "",
                        "attributes" => [
                            "autocomplete" => "off",
                        ],
                    ],
                    "stay_from" => [
                        "type" => "date",
                        "label" =>
                            __("rates.stay") . " " . __("forms.date_from"),
                        "required" => false,
                    ],
                    "stay_to" => [
                        "type" => "date",
                        "label" => __("forms.date_to"),
                        "required" => false,
                    ],
                ],
            ],

            "config-row" => [
                "type" => "fields-row",
                "items" => [
                    "priority" => [
                        "type" => "select",
                        "label" => __("rates.priority"),
                        "required" => false,
                    ],
                    "name" => [
                        "type" => "text",
                        "label" => __("rates.name_this_rate"),
                        "required" => false,
                        "attributes" => [
                            "placeholder" => __(
                                "rates.name_this_rate_placeholder",
                            ),
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * List columns configuration
     */
    public static function listColumns(): array
    {
        return [
            "display_name" => ["label" => __("rates.column_display_name")],
            "base_rate" => [
                "label" => __("rates.column_base_rate"),
                "format" => "currency",
            ],
            "calculation_formula" => ["label" => __("rates.column_formula")],
            "priority" => ["label" => __("rates.column_priority")],
            "is_active" => [
                "label" => __("rates.column_enabled"),
                "format" => "boolean",
            ],
        ];
    }

    // ... (rest of the file continues - relationships, accessors, etc.)

    /**
     * Relationships
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function referenceRate(): BelongsTo
    {
        return $this->belongsTo(Rate::class, "reference_rate_id");
    }

    public function rateProperty(): BelongsTo
    {
        return $this->belongsTo(Property::class, "property_id");
    }

    /**
     * Accessors
     */
    public function getDisplayNameAttribute(): string
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

        if ($this->coupon_code) {
            $parts[] = "Coupon: {$this->coupon_code}";
        }

        if ($this->name) {
            $parts[] = $this->name;
        }

        return implode(" - ", $parts) ?: "Rate #{$this->id}";
    }
}
