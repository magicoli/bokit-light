<?php

namespace App\Models;

use App\Services\BookingMetadataParser;
use App\Traits\AdminResourceTrait;
use App\Traits\TimezoneTrait;
use BladeUI\Icons\Components\Icon;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
// use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Booking extends Model
{
    use AdminResourceTrait;
    use SoftDeletes;
    use TimezoneTrait;

    protected $fillable = [
        "status",
        "guest_name",
        "check_in",
        "check_out",
        "guests",
        "adults",
        "children",
        "property_id",
        "unit_id",
        "source_name",
        "uid",
        "price",
        "commission",
        "notes",
        "is_manual",
        "group_id",
        "raw_data",
    ];

    protected $casts = [
        "check_in" => "date",
        "check_out" => "date",
        "guests" => "integer",
        "adults" => "integer",
        "children" => "integer",
        "is_manual" => "boolean",
        "raw_data" => "array",
        "price" => "decimal:2",
        "commission" => "decimal:2",
        "ota" => "array",
    ];

    protected static $capability = "property_manager";

    protected $appends = [
        "actions",
        "unit_name",
        "ota_url",
        "ota_link",
        "api_source",
    ];

    protected static $searchable = ["guest_name", "source_name"];

    protected static $filterable = ["status", "unit_name", "source_name"];

    protected static $actions = ["status", "edit", "view", "ota"];

    protected $list_columns = [
        "actions",
        // "api_source", // DEBUG
        // "status", // DEBUG
        "unit_name",
        "check_in",
        "check_out",
        "guest_name",
        "guests",
        "adults",
        "children",
        "price",
        "notes",
    ];

    /**
     * Accessor: Calculate unit name from unit_id
     *
     * @return string|null
     */
    protected function unitName(): Attribute
    {
        return Attribute::make(
            get: function () {
                // Later: build popup link
                // if ($this->unit_id ?? null) {
                //     return sprintf(
                //         "<a href='%s'>%s</a>",
                //         route("admin.units.show", $this->unit_id),
                //         $this->unit->name,
                //     );
                // }

                // Now: build unit filter link
                return $this->unit ? $this->unit->name : null;
            },
        );
    }

    /**
     * Accessor: Calculate total guests
     *
     * Priority:
     * 1. Use guests column if set
     * 2. Calculate from adults + children if available
     * 3. Use raw_data[guests] if available
     * 4. Calculate from raw_data[adults] + raw_data[children]
     */
    protected function guests(): Attribute
    {
        return Attribute::make(
            get: function () {
                // Priority 1: return as it is if set
                $guests =
                    $this->attributes["guests"] ??
                    ($this->raw_data["guests"] ?? null);
                if ($guests) {
                    return $guests;
                }

                // Priority 2: adults + children columns
                if (
                    isset($this->attributes["adults"]) ||
                    isset($this->attributes["children"])
                ) {
                    return ($this->attributes["adults"] ?? 0) +
                        ($this->attributes["children"] ?? 0);
                }

                // Priority 3: raw_data[guests]
                if (
                    isset($this->raw_data["guests"]) &&
                    $this->raw_data["guests"] !== null
                ) {
                    return $this->raw_data["guests"];
                }

                // Priority 4: raw_data[adults] + raw_data[children]
                return ($this->raw_data["adults"] ?? 0) +
                    ($this->raw_data["children"] ?? 0);
            },
        );
    }

    protected function adults(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->attributes["adults"] ??
                    ($this->raw_data["adults"] ?? null);
            },
        );
    }

    protected function children(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->attributes["children"] ??
                    ($this->raw_data["children"] ?? null);
            },
        );
    }

    // public static function fillable(): array
    // {
    //     return self::
    //     ];
    // }

    /**
     * Get the unit that owns this booking
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the property that owns this booking
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Find booking by source identifiers with priority order
     *
     * @param string $sourceType Type of source (ical, api, etc.)
     * @param int $sourceId ID of the external source
     * @param string $sourceEventId Event ID from the external source
     * @param int $propertyId Property ID
     * @param string|null $guestEmail Guest email for additional matching
     * @param string $checkIn Check-in date
     * @param string $checkOut Check-out date
     * @param int $unitId Unit ID
     * @return Booking|null
     */
    public static function findBySourceWithPriority(
        string $sourceType,
        int $sourceId,
        string $sourceEventId,
        int $propertyId,
        ?string $guestEmail = null,
        ?string $checkIn = null,
        ?string $checkOut = null,
        ?int $unitId = null,
    ): ?Booking {
        // Priority 1: Exact match on source identifiers (most efficient - uses composite index)
        $booking = self::where("source_type", $sourceType)
            ->where("source_id", $sourceId)
            ->where("source_event_id", $sourceEventId)
            ->first();

        if ($booking) {
            return $booking;
        }

        // Priority 2: Same dates, same unit, same email - definite match
        if ($guestEmail && $checkIn && $checkOut && $unitId) {
            $booking = self::where("unit_id", $unitId)
                ->where("check_in", $checkIn)
                ->where("check_out", $checkOut)
                ->where(function ($query) use ($guestEmail) {
                    $query
                        ->whereJsonContains("raw_data->email", $guestEmail)
                        ->orWhere("notes", "like", "%" . $guestEmail . "%");
                })
                ->first();

            if ($booking) {
                return $booking;
            }
        }

        // Priority 3: Same dates, same unit, no email - probable match
        if ($checkIn && $checkOut && $unitId) {
            $booking = self::where("unit_id", $unitId)
                ->where("check_in", $checkIn)
                ->where("check_out", $checkOut)
                ->first();

            if ($booking) {
                return $booking;
            }
        }

        // Priority 4: Same dates but different unit - probably different booking
        // Priority 5: Different emails - definitely different booking
        // (These cases return null as they're not considered matches)

        return null;
    }

    /**
     * Update or create booking with source identifiers
     *
     * @param array $attributes
     * @param array $values
     * @return Booking
     */
    public static function updateOrCreateWithSource(
        array $attributes,
        array $values,
    ): Booking {
        // Extract source identifiers
        $sourceType = $attributes["source_type"] ?? null;
        $sourceId = $attributes["source_id"] ?? null;
        $sourceEventId = $attributes["source_event_id"] ?? null;
        $propertyId = $attributes["property_id"] ?? null;

        if ($sourceType && $sourceId && $sourceEventId && $propertyId) {
            // Try to find existing booking by source identifiers first
            $existing = self::findBySourceWithPriority(
                $sourceType,
                $sourceId,
                $sourceEventId,
                $propertyId,
                $values["raw_data"]["email"] ?? null,
                $values["check_in"] ?? null,
                $values["check_out"] ?? null,
                $values["unit_id"] ?? null,
            );

            if ($existing) {
                // Update existing booking
                $existing->update($values);
                return $existing;
            }
        }

        // Create new booking
        return self::create($values);
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
            $q->whereBetween("check_in", [$start, $end])
                ->orWhereBetween("check_out", [$start, $end])
                ->orWhere(function ($q2) use ($start, $end) {
                    $q2->where("check_in", "<=", $start)->where(
                        "check_out",
                        ">=",
                        $end,
                    );
                });
        });
    }

    /**
     * Scope to get manual bookings only
     */
    public function scopeManual($query)
    {
        return $query->where("is_manual", true);
    }

    /**
     * Scope to get imported bookings only
     */
    public function scopeImported($query)
    {
        return $query->where("is_manual", false);
    }

    /**
     * Get the color for this booking based on status
     */
    public function getColorAttribute(): string
    {
        return BookingMetadataParser::getStatusColor($this->status);
    }

    /**
     * DEPRECATED Get the human-readable status label
     */
    // public function getStatusLabelAttribute(): string
    // {
    //     return BookingMetadataParser::getStatusLabel($this->status);
    // }

    /**
     * Get metadata value by key with optional default
     */
    public function getMetadata(string $key, $default = null)
    {
        return $this->raw_data[$key] ?? $default;
    }

    /**
     * Accessor for source name
     */
    public function sourceName(): Attribute
    {
        return Attribute::make(
            get: fn(string $value) => self::sourceSlug($value),
        );
    }

    /**
     * Get the source slug for the booking
     */
    public static function sourceSlug(string $source): string
    {
        $source = trim($source);

        return match (true) {
            (bool) preg_match("/airbnb/", $source) => "airbnb",
            (bool) preg_match("/beds24/", $source) => "beds24",
            (bool) preg_match("/booking(\.|dot)?com/", $source)
                => "booking-com",
            default => Str::slug(preg_replace("/^(www|api)\./", "", $source)),
        };
    }

    public function apiSource(): Attribute
    {
        $channel_manager = $this->source_name ?? "";
        $ota_api = $this->getMetadata("api_source", "") ?? "";
        $source = strtolower($ota_api ?? ($channel_manager ?? ""));
        switch ($source) {
            case "direct":
                $source = $channel_manager ?? $source;
                break;
            // case "booking":
            //     $source = "booking-com";
            //     break;
            // default:
            //     $url = null;
        }
        return Attribute::make(get: fn($value) => $source);
    }

    /**
     * Return OTA booking URL for known sources
     *
     * @return string|null
     */
    public function otaUrl(): Attribute
    {
        $source = $this->apiSource ?? null;
        $source_ref = $this->getMetadata("api_ref", "");
        $ota_slug = self::sourceSlug($source);
        if ($source_ref) {
            // e.g.
            // beds24: https://beds24.com/control2.php?ajax=bookedit&id=12345678
            // airbnb: https://www.airbnb.com/hosting/reservations/details/ABCDE12345
            switch ($ota_slug) {
                case "airbnb":
                    $url = "https://www.airbnb.com/hosting/reservations/details/{$source_ref}";
                    break;
                case "booking":
                case "booking.com":
                case "bookingdotcom":
                case "booking-com":
                    if (options("api.booking-com.hotel-id") ?? false) {
                        $url = "https://admin.booking.com/booking/details/{$source_ref}";
                        break;
                    }
                    $url = null;
                    break;
                case "beds24":
                    $url = "https://beds24.com/control2.php?ajax=bookedit&id={$source_ref}";
                    break;
                default:
                    // Unknown OTA
                    $url = null;
            }
        } else {
            // no source reference
            $url = null;
        }

        return Attribute::make(get: fn($value) => $url);
    }

    /**
     * Return OTA booking link
     *
     * @return string|null
     */
    public function otaLink(): Attribute
    {
        $url = $this->ota_url;
        $source = $this->api_source;
        $icon = icon($source) ?? $source;
        if (preg_match("#://#", $url)) {
            $link = sprintf("<a href='%s' target='_blank'>%s</a>", $url, $icon);
        } else {
            $link = $url;
        }
        return Attribute::make(get: fn($value) => $link);
    }

    /**
     * Get all source mappings associated with this booking
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function sourceMappings()
    {
        return $this->hasMany(SourceMapping::class);
    }

    /**
     * Admin resource configuration
     *
     * WE DO NOT IMPLEMENT YET,
     * FIRST WE MAKE SURE THAT ANY MODEL WITH ONLY THE TRAIT ENABLED
     * WILL BEHAVE PROPERLY
     */
    // public static function adminConfig(): array
    // {
    //     self::init();
    //     // For testing purposesn, ONLY Bookings are allowed to property_manager
    //     static::$config["capability"] = "booking_manager";
    //     static::$searchable = ["guest_name", "email"];
    //     // static::$filterable = ["status", "source_name"];
    //     return self::$config;
    // }
}
