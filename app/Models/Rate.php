<?php

namespace App\Models;

use App\Traits\AdminResourceTrait;
use App\Traits\FormTrait;
use App\Traits\ListTrait;
use App\Traits\TimezoneTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rate extends Model
{
    use AdminResourceTrait;
    use FormTrait;
    use ListTrait;
    use TimezoneTrait;

    protected $fillable = [
        "name",
        "property_id",
        "unit_type",
        "unit_id",
        "coupon_code",
        "base",
        "parent_rate_id",
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
     * Boot method - auto-generate slug and setup observers
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

            // If parent_rate_id is set, copy parent's base
            if ($rate->parent_rate_id) {
                $parent = Rate::find($rate->parent_rate_id);
                if ($parent) {
                    $rate->base = $parent->base;
                }
            }
        });

        static::updating(function ($rate) {
            // If parent_rate_id changed, update base
            if ($rate->isDirty("parent_rate_id") && $rate->parent_rate_id) {
                $parent = Rate::find($rate->parent_rate_id);
                if ($parent) {
                    $rate->base = $parent->base;
                }
            }
        });

        static::updated(function ($rate) {
            // If base changed, update all child rates
            if ($rate->isDirty("base")) {
                Rate::where("parent_rate_id", $rate->id)->update([
                    "base" => $rate->base,
                ]);
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
            "base" => "{$sometimes}required|numeric|min:0",
            "parent_rate_id" => "nullable|exists:rates,id",
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
     * Form layout for add rate (alias to formEdit)
     */
    public static function formAdd(): array
    {
        return static::formEdit();
    }

    /**
     * Form layout for edit rate
     */
    public static function formEdit(): array
    {
        $minimumStay = options("rates.default-stay", 3);
        $defaultStay = options("rates.default-stay", 7);
        return [
            "scope" => [
                "type" => "fields-row",
                "items" => [
                    "property_id" => [
                        "type" => "select",
                        "label" => __("app.property"),
                        "required" => true,
                        "placeholder" => __("rates.select_a_property"),
                    ],
                    "unit_id" => [
                        "type" => "select",
                        "label" => __("app.unit"),
                        "placeholder" => __("rates.all_units"),
                    ],
                    "unit_type" => [
                        "type" => "select",
                        "label" => __("rates.scope_type"),
                        // "placeholder" => __("rates.all_types"), // disabled for debug
                        "attributes" => [
                            "data-add-new" => "unit_type",
                        ],
                    ],
                    "coupon_code" => [
                        "type" => "select",
                        "label" => __("rates.scope_coupon"),
                        "placeholder" => __("rates.no_coupon"),
                        "attributes" => [
                            "data-add-new" => "coupon",
                        ],
                    ],
                    "suffix" => [
                        "label" => __("rates.scope_suffix"),
                        "placeholder" => __("rates.optional_suffix"),
                    ],
                    "priority" => [
                        "type" => "select",
                        "label" => __("rates.priority"),
                        "default" => "normal",
                        "placeholder" => __("rates.select_priority"),
                    ],
                    "name" => [
                        "type" => "text",
                        "label" => __("rates.internal_name"),
                        "attributes" => [
                            "placeholder" => __(
                                "rates.name_this_rate_placeholder",
                            ),
                            "readonly" => true,
                        ],
                        "class" => "autofill",
                    ],
                ],
            ],

            "pricing-row" => [
                "type" => "fields-row",
                "items" => [
                    "base" => [
                        "type" => "number",
                        "label" => __("rates.base"),
                        "required" => true,
                        "attributes" => [
                            "step" => "0.01",
                            "id" => "base",
                            "size" => 7,
                        ],
                    ],
                    "parent_rate_id" => [
                        "type" => "select",
                        "label" => __("rates.parent_rate"),
                        "placeholder" => __("rates.no_parent_rate"),
                        "attributes" => [
                            "id" => "parent_rate_id",
                        ],
                    ],
                    "calculation_formula" => [
                        "type" => "text",
                        "label" => __("rates.calculation_formula"),
                        "required" => true,
                        "description" =>
                            "Allowed tags: base, parent_rate, booking_nights, guests, adults, children",
                        "default" => "base * booking_nights",
                        "placeholder" => "base * booking_nights",
                        "class" => "autofill",
                    ],
                ],
            ],

            "dates" => [
                "type" => "fields-row",
                "items" => [
                    "booking" => [
                        "type" => "input-group",
                        "label" => __("rates.booking"),
                        "description" => __(
                            "rates.date_of_the_booking_request",
                        ),
                        "items" => [
                            "booking_from" => [
                                "type" => "date",
                                "placeholder" => __("forms.date_from"),
                                "attributes" => [
                                    "min" => "today",
                                ],
                            ],
                            "booking_to" => [
                                "type" => "date",
                                "placeholder" => __("forms.date_to"),
                                "attributes" => [
                                    "min" => "today",
                                ],
                            ],
                        ],
                    ],
                    "stay" => [
                        "type" => "input-group",
                        "label" => __("rates.stay"),
                        "description" => __("rates.dates_of_the_stay"),
                        "items" => [
                            "stay_from" => [
                                "type" => "date",
                                "placeholder" => __("forms.date_from"),
                                "attributes" => [
                                    "min" => "today",
                                ],
                            ],
                            "stay_to" => [
                                "type" => "date",
                                "placeholder" => __("forms.date_to"),
                                "attributes" => [
                                    "min" => "today",
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Form layout for search/calculator widget
     */
    public static function formBookWidget(): array
    {
        $defaultStay = options("rates.default-stay", 7);
        $minDaysBefore = options("rates.mimum-before", 1);
        $defaultRange = [
            now()->addDay($minDaysBefore)->format("Y-m-d"),
            now()
                ->addDay($minDaysBefore + $defaultStay)
                ->format("Y-m-d"),
        ];
        return [
            "search-row" => [
                "type" => "fields-row",
                "items" => [
                    "dates" => [
                        "type" => "date-range",
                        "label" =>
                            __("app.check_in") . " - " . __("app.check_out"),
                        "required" => true,
                        "default" => $defaultRange,
                        "attributes" => [
                            "min" => now()
                                ->addDay($minDaysBefore)
                                ->format("Y-m-d"),
                            "data-minimum-stay" => options(
                                "rates.minimum-stay",
                                3,
                            ),
                        ],
                    ],
                    "guest" => [
                        "type" => "input-group",
                        "label" => "",
                        "items" => [
                            "adults" => [
                                "type" => "number",
                                "label" => __("app.adults"),
                                "required" => true,
                                "default" => 2,
                                "attributes" => [
                                    "min" => 1,
                                    "size" => 5,
                                    // "style" => "width: 5rem",
                                ],
                            ],
                            "children" => [
                                "type" => "number",
                                "label" => __("app.children"),
                                "default" => 0,
                                "attributes" => [
                                    "min" => 0,
                                    "size" => 5,
                                    // "style" => "width: 5rem",
                                ],
                            ],
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
            "display_name" => [
                "label" => __("rates.column_display_name"),
            ],
            "base" => [
                "label" => __("rates.column_base"),
                "format" => "custom",
                "formatter" => function ($rate) {
                    $display = number_format($rate->base, 2);
                    if ($rate->parent_rate_id && $rate->parentRate) {
                        $display .=
                            ' <span class="parent_rate">(' .
                            $rate->parentRate->display_name .
                            ")</span>";
                    }
                    return $display;
                },
            ],
            "calculation_formula" => ["label" => __("rates.column_formula")],
            "booking_dates" => [
                "label" => __("rates.column_booking"),
                "format" => "custom",
                "formatter" => function ($rate) {
                    if (!$rate->booking_from && !$rate->booking_to) {
                        return "-";
                    }
                    $from = $rate->booking_from
                        ? $rate->booking_from->format("Y-m-d")
                        : "...";
                    $to = $rate->booking_to
                        ? $rate->booking_to->format("Y-m-d")
                        : "...";
                    return "{$from} → {$to}";
                },
            ],
            "stay_dates" => [
                "label" => __("rates.column_stay"),
                "format" => "custom",
                "formatter" => function ($rate) {
                    if (!$rate->stay_from && !$rate->stay_to) {
                        return "-";
                    }
                    $from = $rate->stay_from
                        ? $rate->stay_from->format("Y-m-d")
                        : "...";
                    $to = $rate->stay_to
                        ? $rate->stay_to->format("Y-m-d")
                        : "...";
                    return "{$from} → {$to}";
                },
            ],
            "priority" => ["label" => __("rates.column_priority")],
            "is_active" => [
                "label" => __("rates.column_enabled"),
                "format" => "boolean",
            ],
        ];
    }

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

    public function parentRate(): BelongsTo
    {
        return $this->belongsTo(Rate::class, "parent_rate_id");
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
        // If name is set, use it (it's the calculated name from the form)
        if ($this->name) {
            return $this->name;
        }

        // Otherwise, build it from parts
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

        return implode(" - ", $parts) ?: "Rate #{$this->id}";
    }

    /**
     * Admin resource configuration
     *
     * WE DO NOT IMPLEMENT YET,
     * FIRST WE MAKE SURE THAT ANY MODEL WITH ONLY THE TRAIT ENABLED
     * WILL BEHAVE PROPERLY
     */
    public static function adminConfig(): array
    {
        self::init();
        static::$config["capability"] = "property_manager";
        return self::$config;
    }
}
