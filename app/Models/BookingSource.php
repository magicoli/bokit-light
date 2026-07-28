<?php

namespace App\Models;

use App\Sync\SyncRegistry;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cross-reference between a booking and one external source.
 *
 * A booking can be known to several sources (Beds24 API, iCal feeds, HBook, …)
 * but has exactly one origin (is_origin = true), which is the only source
 * allowed to update dates, prices and other critical data. The
 * (source_key, external_id) pair is the authoritative match key.
 */
class BookingSource extends Model
{
    protected $fillable = [
        'booking_id',
        'source_type',
        'source_key',
        'external_id',
        'is_origin',
        'is_placeholder',
        'last_seen_at',
        'pushed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_origin' => 'boolean',
            'is_placeholder' => 'boolean',
            'last_seen_at' => 'datetime',
            'pushed_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * True when this row was created from another source's origin hint and
     * no real source has claimed it yet.
     */
    public function isPlaceholder(): bool
    {
        return $this->is_placeholder;
    }

    /**
     * Direct URL to the booking's page in the source system, when the
     * source connector provides one (API sources; iCal feeds don't).
     */
    protected function externalUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => app(SyncRegistry::class)
            ->getForType($this->source_type)
            ?->externalBookingUrl($this));
    }

    /**
     * Human-readable label for display: connector label plus the source
     * instance name, prefixed with a check mark when this is the origin.
     * Examples: "✓ Beds24 API", "iCal beds24.com", "✓ iCal (not connected)".
     */
    protected function displayLabel(): Attribute
    {
        return Attribute::get(function (): string {
            $base = app(SyncRegistry::class)->getForType($this->source_type)?->label()
                ?? $this->source_type;

            $instance = str_contains($this->source_key, ':')
                ? ' '.explode(':', $this->source_key, 2)[1]
                : '';

            $pending = $this->is_placeholder
                ? ' ('.__('booking.source.not_connected').')'
                : '';

            $check = $this->is_origin ? '✓ ' : '';

            return $check.$base.$instance.$pending;
        });
    }
}
