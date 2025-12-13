<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use App\Services\BookingMetadataParser;
use Str;

class Booking extends Model
{
    use SoftDeletes;
    protected $fillable = [
        "unit_id",
        "uid",
        "source_name",
        "status",
        "guest_name",
        "check_in",
        "check_out",
        "adults",
        "children",
        "price",
        "commission",
        "notes",
        "is_manual",
        "group_id",
        "raw_data",
    ];

    protected $appends = ["ota_url", "ota_link"];

    protected $casts = [
        "check_in" => "date",
        "check_out" => "date",
        "is_manual" => "boolean",
        "raw_data" => "array",
        "price" => "decimal:2",
        "commission" => "decimal:2",
        "ota" => "array",
    ];

    /**
     * Get the unit that owns this booking
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
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
     * Get the human-readable status label
     */
    public function getStatusLabelAttribute(): string
    {
        return BookingMetadataParser::getStatusLabel($this->status);
    }

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

    public static function sourceSlug(string $source): string
    {
        $source = trim($source);

        return match (true) {
            (bool) preg_match("/airbnb/", $source) => "airbnb",
            (bool) preg_match("/beds24/", $source) => "beds24",
            (bool) preg_match("/booking\.com/", $source) => "bookingcom",
            default => Str::slug(preg_replace("/^(www|api)\./", "", $source)),
        };
    }

    /**
     * Return OTA booking URL for known sources
     *
     * @return string|null
     */
    public function otaUrl(): Attribute
    {
        $source_ref = $this->getMetadata("source_ref", "");
        $ota_slug = $this->source_name;
        if ($source_ref) {
            // e.g.
            // beds24: https://beds24.com/control2.php?ajax=bookedit&id=12345678
            // airbnb: https://www.airbnb.com/hosting/reservations/details/ABCDE12345
            switch ($ota_slug) {
                case "airbnb":
                    $url = "https://www.airbnb.com/hosting/reservations/details/{$source_ref}";
                    break;
                case "beds24":
                    $url = "https://beds24.com/control2.php?ajax=bookedit&id={$source_ref}";
                    break;
                default:
                    $url = "no url";
            }
        } else {
            $url = "no source reference";
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
        if (preg_match("#://#", $url)) {
            $link = sprintf(
                "<a href='%s' target='_blank'>%s</a>",
                $url,
                sprintf(__("View on %s"), $this->source_name),
            );
        } else {
            $link = $url;
        }
        return Attribute::make(get: fn($value) => $link);
    }
}
