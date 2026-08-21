<?php

namespace App\Models;

// use App\Services\BookingMetadataParser;
use App\Filament\Resources\Bookings\BookingResource;
use App\Sync\Support\SyncResolver;
use App\Traits\AdminResourceTrait;
use App\Traits\TimezoneTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
// use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Booking extends Model
{
    use AdminResourceTrait;
    use SoftDeletes;
    use TimezoneTrait;

    /**
     * Canonical internal statuses. Every connector maps its source's
     * statuses to one of these — platform-specific variants are not kept:
     * - confirmed: includes Beds24 Confirmed and New (New only adds the
     *   is_new metadata tag);
     * - option: blocking request, dates are held (Beds24 Request);
     * - quote: priced and dated but not blocking (Beds24 Inquiry);
     * - blocked: availability block (Beds24 Black, iCal Unavailable),
     *   synced for future dates only;
     * - cancelled: nothing is held any more, hidden by default.
     *
     * @var list<string>
     */
    public const STATUSES = ['confirmed', 'option', 'quote', 'blocked', 'cancelled'];

    /**
     * Statuses of bookings that no longer hold the unit: no money is
     * expected from them and default views hide them. 'deleted' and
     * 'vanished' are internal variants of cancelled set by the sync engine.
     *
     * @var list<string>
     */
    public const CANCELLED_STATUSES = ['cancelled', 'deleted', 'vanished'];

    protected $fillable = [
        'status',
        'guest_name',
        'check_in',
        'check_out',
        'guests',
        'adults',
        'children',
        'property_id',
        'unit_id',
        'source_name',
        'uid',
        'price',
        'commission',
        'notes',
        'is_manual',
        'group_id',
        'sync_data',
        'metadata',
        'options',
    ];

    protected $casts = [
        'check_in' => 'date:c',
        'check_out' => 'date:c',
        'guests' => 'integer',
        'adults' => 'integer',
        'children' => 'integer',
        'is_manual' => 'boolean',
        'sync_data' => 'array',
        'metadata' => 'array',
        'options' => 'array',
        'price' => 'decimal:2',
        'commission' => 'decimal:2',
        'ota' => 'array',
        'metadata' => 'array',
    ];

    protected static $capability = 'property_manager';

    /**
     * Always eager-load relationships for timezone() accessor
     */
    protected $with = ['unit', 'property'];

    protected $appends = [
        'actions',
        'unit_name',
        'ota_url',
        'ota_link',
        'api_source',
    ];

    protected static $searchable = ['guest_name', 'source_name'];

    protected static $filterable = ['status', 'unit_name', 'source_name'];

    protected static $actions = ['status', 'view', 'edit', 'ota'];

    protected static $icon = 'calendar';

    protected $list_columns = [
        'actions',
        // "api_source", // DEBUG
        // "status", // DEBUG
        'unit_name',
        'check_in',
        'check_out',
        'guest_name',
        'guests',
        'adults',
        'children',
        'price',
        'paid',
        'balance',
        'notes',
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

    protected function checkIn(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => Carbon::parse($value),
            // unit may be null for group-booking summary rows (unit_id=NULL).
            // Use DB::table() directly for those rows to bypass this mutator.
            set: fn (string $value) => $this->unit
                ? $this->unit->shiftAndFormat($value)
                : $value,
        );
    }

    protected function checkOut(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => Carbon::parse($value),
            // unit may be null for group-booking summary rows (unit_id=NULL).
            // Use DB::table() directly for those rows to bypass this mutator.
            set: fn (string $value) => $this->unit
                ? $this->unit->shiftAndFormat($value)
                : $value,
        );
    }

    /**
     * Accessor: one-line summary of the booking, for widget mini-lists and
     * mail subjects: "{guest}, {unit}, {n}p from {check_in} to {check_out}".
     * Group reservations show the unit count and aggregate guests and the
     * date span over the active (non-cancelled) members.
     */
    protected function title(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $members = $this->groupMembers()
                    ->reject(fn (Booking $m): bool => $m->isCancelled())
                    ->values();

                if ($members->count() > 1) {
                    $units = trans_choice('booking.title.units', $members->count());
                    $guests = $members->sum(fn (Booking $m): int => (int) ($m->guests ?? 0));
                    $from = $members->min('check_in');
                    $to = $members->max('check_out');
                } else {
                    $units = $this->unit?->name;
                    $guests = (int) ($this->guests ?? 0);
                    $from = $this->check_in;
                    $to = $this->check_out;
                }

                $parts = array_filter([
                    $this->guest_name,
                    $units,
                    $guests > 0 ? $guests.'p' : null,
                ]);

                [$fromPart, $toPart] = self::dateRangeNumericParts($from, $to);

                return implode(', ', $parts).' '.__('booking.title.dates', [
                    'from' => $fromPart,
                    'to' => $toPart,
                ]);
            },
        );
    }

    /**
     * Accessor: Calculate total guests
     *
     * Priority:
     * 1. Use guests column if set
     * 2. Calculate from adults + children if available
     * 3. Use metadata[guests] if available
     * 4. Calculate from metadata[adults] + metadata[children]
     */
    protected function guests(): Attribute
    {
        return Attribute::make(
            get: function () {
                // Priority 1: return as it is if set
                $guests =
                    $this->attributes['guests'] ??
                    ($this->metadata['guests'] ?? null);
                if ($guests) {
                    return $guests;
                }

                // Priority 2: adults + children columns
                if (
                    isset($this->attributes['adults']) ||
                    isset($this->attributes['children'])
                ) {
                    return ($this->attributes['adults'] ?? 0) +
                        ($this->attributes['children'] ?? 0);
                }

                // Priority 3: metadata[guests]
                if (
                    isset($this->metadata['guests']) &&
                    $this->metadata['guests'] !== null
                ) {
                    return $this->metadata['guests'];
                }

                // Priority 4: metadata[adults] + metadata[children]
                $metaAdults = $this->metadata['adults'] ?? null;
                $metaChildren = $this->metadata['children'] ?? null;
                if ($metaAdults === null && $metaChildren === null) {
                    return null; // No guest count available — show nothing, not zero
                }

                return ($metaAdults ?? 0) + ($metaChildren ?? 0);
            },
        );
    }

    protected function adults(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->attributes['adults'] ??
                    ($this->metadata['adults'] ?? null);
            },
        );
    }

    protected function children(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->attributes['children'] ??
                    ($this->metadata['children'] ?? null);
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
     * External source references for this booking (one per source).
     */
    public function sources(): HasMany
    {
        return $this->hasMany(BookingSource::class);
    }

    /**
     * The origin source reference — the only source allowed to update
     * dates, prices and critical data.
     */
    public function originSource(): HasOne
    {
        return $this->hasOne(BookingSource::class)->where('is_origin', true);
    }

    /**
     * All bookings of the same group reservation (including this one),
     * one per unit. Empty collection when the booking is not grouped.
     *
     * @return Collection<int, Booking>
     */
    public function groupMembers(): Collection
    {
        if (! $this->group_id) {
            return new Collection;
        }

        return static::where('group_id', $this->group_id)
            ->with('unit')
            ->orderBy('check_in')
            ->get();
    }

    /**
     * Full detail payload — the calendar modal (CalendarController::booking()) and
     * get_booking (App\Mcp\Tools\GetBookingTool) both need the exact same thing, so this lives
     * once, here.
     *
     * Group reservations aggregate dates, price, paid and guest counts over all members and
     * expose the member list for the group detail table. Links point to the Filament admin
     * panel; the origin link comes from the booking's origin source when its connector
     * provides one.
     *
     * @return array<string, mixed>
     */
    public function toDetailPayload(): array
    {
        $members = $this->groupMembers();
        $isGroup = $members->count() > 1;

        // Cancelled members hold nothing: they stay listed in the group
        // detail but count for nothing in the aggregates.
        $active = $members->reject(fn (Booking $m): bool => $m->isCancelled())->values();
        if ($active->isEmpty()) {
            $active = new Collection([$this]);
        }

        $rawPrice = fn (Booking $b): ?float => $b->getRawOriginal('price') !== null
            ? (float) $b->getRawOriginal('price')
            : null;
        $paidOf = function (Booking $b): ?float {
            $paid = $b->getMetadata('invoice_payment_total') ?? $b->getMetadata('paid');

            return $paid !== null ? (float) $paid : null;
        };
        $depositOf = fn (Booking $b): ?float => $b->getMetadata('deposit') !== null
            ? (float) $b->getMetadata('deposit')
            : null;

        if ($this->isCancelled()) {
            // No money is expected from a cancelled booking — hide amounts.
            $rawPrice = fn (Booking $b): ?float => null;
            $paidOf = fn (Booking $b): ?float => null;
            $depositOf = fn (Booking $b): ?float => null;
        }

        $sumOf = fn (Collection $members, callable $amountOf): ?float => $members->contains(
            fn (Booking $m): bool => $amountOf($m) !== null,
        )
                ? $members->sum(fn (Booking $m): float => $amountOf($m) ?? 0)
                : null;

        if ($isGroup) {
            $price = $sumOf($active, $rawPrice);
            $paid = $sumOf($active, $paidOf);
            $deposit = $sumOf($active, $depositOf);
        } else {
            $price = $rawPrice($this);
            $paid = $paidOf($this);
            $deposit = $depositOf($this);
        }

        $origin = $this->originSource;

        return [
            'id' => $this->id,
            'guest_name' => $this->guest_name,
            'status' => $this->status,
            'status_label' => __('booking.status.'.$this->status),
            'display_status' => $this->displayStatus(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'check_in' => ($isGroup ? $active->min('check_in') : $this->check_in)->format('Y-m-d'),
            'check_out' => ($isGroup ? $active->max('check_out') : $this->check_out)->format('Y-m-d'),
            'adults' => $isGroup ? ($active->sum('adults') ?: null) : $this->adults,
            'children' => $isGroup ? ($active->sum('children') ?: null) : $this->children,
            'guests' => $isGroup
                ? ($active->sum(fn (Booking $m): int => (int) ($m->guests ?? 0)) ?: null)
                : $this->guests,
            'price' => $price,
            'deposit' => $deposit,
            'paid' => $paid,
            'balance' => $price !== null ? round($price - ($paid ?? 0), 2) : null,
            'notes' => $this->notes,
            'metadata' => $this->metadata,
            'unit' => [
                'id' => $this->unit?->id,
                'name' => $this->unit?->name,
            ],
            'property' => [
                'id' => $this->property?->id,
                'name' => $this->property?->name,
            ],
            // Property is the App panel's own tenant now (dev/project-app-panel-tenancy.md) —
            // an explicit tenant is required here since this can run outside a live,
            // tenant-resolved panel request (the MCP server, CalendarController's JSON
            // endpoints), where Filament has no "current tenant" to infer one from.
            'view_url' => BookingResource::getUrl('view', ['record' => $this], panel: 'app', tenant: $this->property ?? $this->unit?->property),
            'edit_url' => BookingResource::getUrl('edit', ['record' => $this], panel: 'app', tenant: $this->property ?? $this->unit?->property),
            'source' => [
                'label' => $origin ? preg_replace('/^✓ /u', '', $origin->display_label) : $this->source_name,
                'url' => $origin && ! $origin->is_placeholder ? $origin->external_url : null,
            ],
            // Real origin channel (airbnb, booking.com, …) with its direct
            // link on the OTA — distinct from the transport source above.
            'origin' => [
                'channel' => $this->source_name,
                'slug' => $this->api_source,
                'url' => $this->originUrl(),
                'logo' => icon_ota($this->api_source) ?: icon('arrow-up-right'),
            ],
            'group' => $isGroup
                ? [
                    'count' => $active->count(),
                    'members' => $members
                        ->map(fn (Booking $m): array => [
                            'id' => $m->id,
                            'unit_name' => $m->unit?->name,
                            'check_in' => $m->check_in->format('Y-m-d'),
                            'check_out' => $m->check_out->format('Y-m-d'),
                            'price' => $m->isCancelled() ? null : $rawPrice($m),
                            'is_current' => $m->id === $this->id,
                            'is_cancelled' => $m->isCancelled(),
                        ])
                        ->values()
                        ->all(),
                ] : null,
        ];
    }

    /**
     * Find booking by source identifiers with priority order
     *
     * @param  string  $sourceType  Type of source (ical, api, etc.)
     * @param  int  $sourceId  ID of the external source
     * @param  string  $sourceEventId  Event ID from the external source
     * @param  int  $propertyId  Property ID
     * @param  string|null  $guestEmail  Guest email for additional matching
     * @param  string  $checkIn  Check-in date
     * @param  string  $checkOut  Check-out date
     * @param  int  $unitId  Unit ID
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
        $booking = self::where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('source_event_id', $sourceEventId)
            ->first();

        if ($booking) {
            return $booking;
        }

        // Priority 2: Same dates, same unit, same email - definite match
        if ($guestEmail && $checkIn && $checkOut && $unitId) {
            $booking = self::where('unit_id', $unitId)
                ->where('check_in', $checkIn)
                ->where('check_out', $checkOut)
                ->where(function ($query) use ($guestEmail) {
                    $query
                        ->whereJsonContains('metadata->email', $guestEmail)
                        ->orWhere('notes', 'like', '%'.$guestEmail.'%');
                })
                ->first();

            if ($booking) {
                return $booking;
            }
        }

        // Priority 3: Same dates, same unit, no email - probable match
        if ($checkIn && $checkOut && $unitId) {
            $booking = self::where('unit_id', $unitId)
                ->where('check_in', $checkIn)
                ->where('check_out', $checkOut)
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
     */
    public static function updateOrCreateWithSource(
        array $attributes,
        array $values,
    ): Booking {
        // Extract source identifiers
        $sourceType = $attributes['source_type'] ?? null;
        $sourceId = $attributes['source_id'] ?? null;
        $sourceEventId = $attributes['source_event_id'] ?? null;
        $propertyId = $attributes['property_id'] ?? null;

        if ($sourceType && $sourceId && $sourceEventId && $propertyId) {
            // Try to find existing booking by source identifiers first
            $existing = self::findBySourceWithPriority(
                $sourceType,
                $sourceId,
                $sourceEventId,
                $propertyId,
                $values['metadata']['email'] ?? null,
                $values['check_in'] ?? null,
                $values['check_out'] ?? null,
                $values['unit_id'] ?? null,
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
            $q->whereBetween('check_in', [$start, $end])
                ->orWhereBetween('check_out', [$start, $end])
                ->orWhere(function ($q2) use ($start, $end) {
                    $q2->where('check_in', '<=', $start)->where(
                        'check_out',
                        '>=',
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
        return $query->where('is_manual', true);
    }

    /**
     * Scope to get imported bookings only
     */
    public function scopeImported($query)
    {
        return $query->where('is_manual', false);
    }

    /**
     * Get the color for this booking based on status
     */
    // public function getColorAttribute(): string
    // {
    //     return BookingMetadataParser::getStatusColor($this->status);
    // }

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
        return $this->metadata[$key] ?? $default;
    }

    /**
     * True when the booking is cancelled, deleted or vanished — it no
     * longer holds the unit and no payment is expected.
     */
    public function isCancelled(): bool
    {
        return in_array($this->status, self::CANCELLED_STATUSES, true) || $this->trashed();
    }

    /**
     * The bookings that money-wise represent this reservation: the active
     * (non-cancelled) group members for a group, the booking itself
     * otherwise. Used by the group-aware amount helpers below.
     *
     * @return Collection<int, Booking>
     */
    private function activeMembers(): Collection
    {
        if (! $this->group_id) {
            return new Collection([$this]);
        }

        $members = $this->groupMembers()
            ->reject(fn (Booking $m): bool => $m->isCancelled())
            ->values();

        return $members->isEmpty() ? new Collection([$this]) : $members;
    }

    /**
     * Amount actually received (Beds24 invoice payment lines, or the
     * 'paid' metadata used by other sources). Aggregates the active group
     * members for group reservations — for per-unit amounts on the fiche,
     * read the metadata directly.
     */
    public function paidAmount(): ?float
    {
        $paidOf = function (Booking $b): ?float {
            $paid = $b->getMetadata('invoice_payment_total') ?? $b->getMetadata('paid');

            return $paid !== null ? (float) $paid : null;
        };

        $members = $this->activeMembers();

        return $members->contains(fn (Booking $m): bool => $paidOf($m) !== null)
            ? round($members->sum(fn (Booking $m): float => $paidOf($m) ?? 0), 2)
            : null;
    }

    /**
     * Total price owed, aggregating the active group members for group
     * reservations.
     */
    public function totalAmount(): ?float
    {
        $members = $this->activeMembers();

        return $members->contains(fn (Booking $m): bool => $m->getRawOriginal('price') !== null)
            ? round($members->sum(fn (Booking $m): float => (float) $m->getRawOriginal('price')), 2)
            : null;
    }

    /**
     * What remains to pay, when the total is known.
     */
    public function balanceAmount(): ?float
    {
        $total = $this->totalAmount();

        return $total !== null
            ? round($total - ($this->paidAmount() ?? 0), 2)
            : null;
    }

    /**
     * Status refined by payment for display colors: a confirmed booking is
     * 'paid' when nothing remains to pay, 'due' otherwise (unknown amounts
     * need attention too). Other statuses pass through.
     */
    public function displayStatus(): string
    {
        if ($this->status === 'confirmed') {
            $balance = $this->balanceAmount();

            return $balance !== null && $balance <= 0 ? 'paid' : 'due';
        }

        return $this->status;
    }

    /**
     * Accessor for source name
     */
    public function sourceName(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? self::sourceSlug($value) : null,
        );
    }

    /**
     * Get the source slug for the booking
     */
    public static function sourceSlug(string $source): string
    {
        $source = trim($source);

        return match (true) {
            (bool) preg_match('/airbnb/', $source) => 'airbnb',
            (bool) preg_match('/beds24/', $source) => 'beds24',
            (bool) preg_match("/booking(\.|dot)?com/", $source) => 'booking-com',
            default => Str::slug(preg_replace("/^(www|api)\./", '', $source)),
        };
    }

    /**
     * Real origin channel as a normalized slug (airbnb, booking-com, beds24,
     * hbook, …), derived from source_name. Note: metadata.api_source holds
     * the channel manager's INTERNAL numeric code (Beds24: 46/19/…) and must
     * never be used for display — only source_name names the real channel.
     */
    public function apiSource(): Attribute
    {
        return Attribute::make(get: fn (): string => self::sourceSlug($this->source_name ?? ''));
    }

    /**
     * Direct link to the booking on its real origin OTA (airbnb,
     * booking.com), or null when the origin is not a self-managed OTA
     * (direct, beds24, hbook, …). Distinct from the transport/channel-
     * manager link, which lives on the booking sources.
     */
    public function originUrl(): ?string
    {
        return $this->api_source === 'beds24' ? null : $this->ota_url;
    }

    /**
     * Protected = the real origin is a self-managed OTA (airbnb,
     * booking.com) that owns the change workflow: bokit must not edit its
     * dates/price nor push to it. Everything else (direct, beds24 manual,
     * hbook, manual bokit bookings) is editable, bokit acting as master.
     * The locked-channel list is a global setting.
     */
    public function isProtected(): bool
    {
        $protected = options('sync.protected_channels', ['airbnb', 'booking-com']);

        return in_array($this->api_source, (array) $protected, true);
    }

    /**
     * Return OTA booking URL for known sources
     *
     * @return string|null
     */
    public function otaUrl(): Attribute
    {
        $source = $this->apiSource ?? null;
        $source_ref = $this->getMetadata('api_ref', '');
        $ota_slug = self::sourceSlug($source);
        if ($source_ref) {
            // e.g.
            // beds24: https://beds24.com/control2.php?ajax=bookedit&id=12345678
            // airbnb: https://www.airbnb.com/hosting/reservations/details/ABCDE12345
            switch ($ota_slug) {
                case 'airbnb':
                    $url = "https://www.airbnb.com/hosting/reservations/details/{$source_ref}";
                    break;
                case 'booking':
                case 'booking.com':
                case 'bookingdotcom':
                case 'booking-com':
                    if (options('api.booking-com.hotel-id') ?? false) {
                        $url = "https://admin.booking.com/booking/details/{$source_ref}";
                        break;
                    }
                    $url = null;
                    break;
                case 'beds24':
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

        return Attribute::make(get: fn ($value) => $url);
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
        if (preg_match('#://#', $url)) {
            $link = sprintf("<a href='%s' target='_blank'>%s</a>", $url, $icon);
        } else {
            $link = $url;
        }

        return Attribute::make(get: fn ($value) => $link);
    }

    /**
     * Get all source mappings associated with this booking
     *
     * @return HasMany
     */
    public function sourceMappings()
    {
        return $this->hasMany(SourceMapping::class);
    }

    /**
     * Apply sync data with three-way merge
     *
     * @param  array  $newData  New data from sync source
     * @param  string  $source  Sync source identifier (e.g., 'airbnb_ical', 'beds24_api')
     * @param  array|null  $metadata  Optional metadata to store (raw, processed)
     * @return array ['updated' => [...], 'diffs' => [...]]
     */
    public function applySyncData(
        array $newData,
        string $source,
        ?array $metadata = null,
    ): array {
        return SyncResolver::applySyncData(
            $this,
            $newData,
            $source,
            $metadata,
        );
    }

    /**
     * Get sync differences (fields where local != remote)
     *
     * These are intentional local edits, not conflicts.
     *
     * @param  string|null  $source  Source to check (default: first source)
     * @return array ['field' => ['local' => ..., 'remote' => ...], ...]
     */
    public function getSyncDiffs(?string $source = null): array
    {
        return SyncResolver::getDiffs($this, $source);
    }

    /**
     * Get sync logs for this booking
     *
     * @return MorphMany
     */
    public function syncLogs()
    {
        return $this->morphMany(
            SyncLog::class,
            'model',
            'model_type',
            'model_id',
        );
    }
}
