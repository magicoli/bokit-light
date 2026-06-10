<?php

namespace App\Support;

/**
 * Source-agnostic representation of one booking fetched from an external source.
 *
 * Connectors (Beds24, iCal, HBook, …) fetch raw data, normalize it into this
 * shape and hand it to the SyncEngine. They never touch the database — all
 * matching, ownership and persistence logic lives in the engine.
 */
class NormalizedBooking
{
    /**
     * @param  string  $externalId  The booking's id in the source system (authoritative match key).
     * @param  string  $checkIn  Y-m-d
     * @param  string  $checkOut  Y-m-d
     * @param  string  $guestName  Guest display name ('Guest' when unknown).
     * @param  string  $status  confirmed|pending|cancelled|unavailable|undefined|…
     * @param  string|null  $email  Guest email, used for fallback matching.
     * @param  float|null  $price  Total accommodation price (null when the source doesn't provide it).
     * @param  float|null  $commission  Channel commission.
     * @param  int|null  $guests  Total guest count.
     * @param  string|null  $channel  Marketing channel (airbnb, booking.com, beds24, feed label, …) → bookings.source_name.
     * @param  array<string,mixed>  $metadata  Extra data merged into bookings.metadata.
     * @param  array{type: string, external_id: string}|null  $originHint  Set when the source knows the booking
     *                                                                     originated elsewhere (e.g. Beds24 'referer' = iCal Import).
     * @param  string|null  $legacyUid  Value historically stored in bookings.uid, used for transitional matching.
     * @param  bool  $claimsOrigin  True when the source can reliably assert this booking originated with it.
     *                              iCal feeds can never tell, so they always leave this false.
     */
    public function __construct(
        public string $externalId,
        public string $checkIn,
        public string $checkOut,
        public string $guestName = 'Guest',
        public string $status = 'undefined',
        public ?string $email = null,
        public ?float $price = null,
        public ?float $commission = null,
        public ?int $guests = null,
        public ?int $adults = null,
        public ?int $children = null,
        public ?string $channel = null,
        public array $metadata = [],
        public ?array $originHint = null,
        public ?string $legacyUid = null,
        public bool $claimsOrigin = false,
    ) {}
}
