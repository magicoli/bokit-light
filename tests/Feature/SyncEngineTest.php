<?php

use App\Contracts\SourceConnector;
use App\Models\Booking;
use App\Models\BookingSource;
use App\Models\Property;
use App\Models\Unit;
use App\Services\SyncEngine;
use App\Support\NormalizedBooking;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeEngineConnector(string $type, string $key, array $bookings): SourceConnector
{
    return new class($type, $key, $bookings) implements SourceConnector
    {
        public function __construct(
            private string $type,
            private string $key,
            public array $bookings,
        ) {}

        public function sourceType(): string
        {
            return $this->type;
        }

        public function label(): string
        {
            return ucfirst($this->type);
        }

        public function displayLabel(array $sourceConfig): string
        {
            return $this->label();
        }

        public function sourceKey(Unit $unit, array $sourceConfig): string
        {
            return $this->key;
        }

        public function fetchBookings(Unit $unit, array $sourceConfig): array
        {
            return $this->bookings;
        }

        public function externalBookingUrl(BookingSource $source): ?string
        {
            return null;
        }
    };
}

describe('SyncEngine', function () {
    beforeEach(function () {
        $this->property = Property::create(['name' => 'EngineP', 'slug' => 'engine-p', 'is_active' => true]);
        $this->unit = Unit::create([
            'property_id' => $this->property->id,
            'name' => 'EngineU',
            'slug' => 'engine-u',
            'is_active' => true,
        ]);
        $this->unit->setRelation('property', $this->property);
        $this->engine = new SyncEngine;
    });

    it('creates a new booking with an origin reference', function () {
        $connector = makeEngineConnector('beds24', 'beds24', [
            new NormalizedBooking(
                externalId: '111',
                checkIn: '2027-01-08',
                checkOut: '2027-01-11',
                guestName: 'Gudule Lapointe',
                status: 'confirmed',
                email: 'gudule@example.com',
                price: 500.0,
                channel: 'beds24',
                legacyUid: 'beds24-111',
                claimsOrigin: true,
            ),
        ]);

        $stats = $this->engine->sync($this->unit, [], $connector);

        expect($stats['new'])->toBe(1)
            ->and(Booking::count())->toBe(1);

        $booking = Booking::first();
        expect($booking->guest_name)->toBe('Gudule Lapointe')
            ->and($booking->uid)->toBe('beds24-111')
            ->and($booking->sources)->toHaveCount(1)
            ->and($booking->sources->first()->is_origin)->toBeTrue()
            ->and($booking->sources->first()->source_key)->toBe('beds24')
            ->and($booking->sources->first()->external_id)->toBe('111');
    });

    it('reattaches a leftover reference instead of violating the unique pair', function () {
        // A reference pair left behind (e.g. bookings wiped manually without
        // FK cascades) must not block recreation — it gets reattached.
        $stale = Booking::create([
            'unit_id' => $this->unit->id,
            'property_id' => $this->property->id,
            'uid' => 'stale-1',
            'check_in' => '2026-01-01',
            'check_out' => '2026-01-05',
            'guest_name' => 'Stale Guest',
            'status' => 'confirmed',
        ]);
        $stale->sources()->create([
            'source_type' => 'ical',
            'source_key' => 'ical',
            'external_id' => 'uid-hint@gites-mosaiques.com',
            'is_origin' => true,
            'is_placeholder' => true,
        ]);

        $connector = makeEngineConnector('beds24', 'beds24', [
            new NormalizedBooking(
                externalId: '555',
                checkIn: '2027-09-01',
                checkOut: '2027-09-08',
                guestName: 'Nouveau Client',
                status: 'confirmed',
                originHint: ['type' => 'ical', 'external_id' => 'uid-hint@gites-mosaiques.com'],
            ),
        ]);

        // Different dates and guest: no heuristic match with the stale booking,
        // but the hint lookup finds it through the leftover pair — and if it
        // didn't (orphaned row), creation must still not crash.
        $stats = $this->engine->sync($this->unit, [], $connector);

        expect($stats['success'])->toBeTrue()
            ->and(BookingSource::where('external_id', 'uid-hint@gites-mosaiques.com')->count())->toBe(1);
    });

    it('persists and updates the group linkage', function () {
        $connector = makeEngineConnector('beds24', 'beds24', [
            new NormalizedBooking(
                externalId: '101',
                checkIn: '2027-02-01',
                checkOut: '2027-02-05',
                guestName: 'Groupe Kervella',
                status: 'confirmed',
                claimsOrigin: true,
                groupId: '100',
            ),
        ]);

        $this->engine->sync($this->unit, [], $connector);

        expect((string) Booking::first()->group_id)->toBe('100');
    });

    it('is idempotent on consecutive runs', function () {
        $connector = makeEngineConnector('beds24', 'beds24', [
            new NormalizedBooking(
                externalId: '111',
                checkIn: '2027-01-08',
                checkOut: '2027-01-11',
                guestName: 'Gudule Lapointe',
                status: 'confirmed',
                price: 500.0,
                channel: 'beds24',
            ),
        ]);

        $this->engine->sync($this->unit, [], $connector);
        $stats = $this->engine->sync($this->unit, [], $connector);

        expect($stats['new'])->toBe(0)
            ->and($stats['updated'])->toBe(0)
            ->and(Booking::count())->toBe(1);
    });

    it('matches by external id even when dates and guest changed at source', function () {
        $connector = makeEngineConnector('beds24', 'beds24', [
            new NormalizedBooking(
                externalId: '111',
                checkIn: '2027-01-08',
                checkOut: '2027-01-11',
                guestName: 'Gudule Lapointe',
                status: 'confirmed',
            ),
        ]);
        $this->engine->sync($this->unit, [], $connector);

        $connector->bookings = [
            new NormalizedBooking(
                externalId: '111',
                checkIn: '2027-02-01',
                checkOut: '2027-02-05',
                guestName: 'Gudule Lapointe-Tremblay',
                status: 'confirmed',
            ),
        ];
        $stats = $this->engine->sync($this->unit, [], $connector);

        expect($stats['new'])->toBe(0)
            ->and($stats['updated'])->toBe(1)
            ->and(Booking::count())->toBe(1);

        $booking = Booking::first();
        expect($booking->check_in->format('Y-m-d'))->toBe('2027-02-01')
            ->and($booking->check_out->format('Y-m-d'))->toBe('2027-02-05')
            ->and($booking->guest_name)->toBe('Gudule Lapointe-Tremblay');
    });

    it('attaches a reference instead of duplicating when a second source matches by email and dates', function () {
        $beds24 = makeEngineConnector('beds24', 'beds24', [
            new NormalizedBooking(
                externalId: '111',
                checkIn: '2027-01-08',
                checkOut: '2027-01-11',
                guestName: 'Gudule Lapointe',
                status: 'confirmed',
                email: 'gudule@example.com',
                price: 500.0,
                channel: 'beds24',
                claimsOrigin: true,
            ),
        ]);
        $this->engine->sync($this->unit, [], $beds24);

        $ical = makeEngineConnector('ical', 'ical:beds24.com', [
            new NormalizedBooking(
                externalId: 'uid-aaa@beds24.com',
                checkIn: '2027-01-08',
                checkOut: '2027-01-11',
                guestName: 'Gudule L.',
                status: 'confirmed',
                email: 'gudule@example.com',
                channel: 'beds24.com',
            ),
        ]);
        $stats = $this->engine->sync($this->unit, [], $ical);

        expect($stats['new'])->toBe(0)
            ->and(Booking::count())->toBe(1);

        $booking = Booking::first();
        expect($booking->sources)->toHaveCount(2)
            ->and($booking->guest_name)->toBe('Gudule Lapointe')
            ->and((float) $booking->price)->toBe(500.0)
            ->and($booking->source_name)->toBe('beds24');

        $icalRef = $booking->sources->firstWhere('source_key', 'ical:beds24.com');
        expect($icalRef)->not->toBeNull()
            ->and($icalRef->is_origin)->toBeFalse();
    });

    it('attaches a reference when a second source matches by guest name and dates', function () {
        $beds24 = makeEngineConnector('beds24', 'beds24', [
            new NormalizedBooking(
                externalId: '111',
                checkIn: '2027-01-08',
                checkOut: '2027-01-11',
                guestName: 'Gudule Lapointe',
                status: 'confirmed',
            ),
        ]);
        $this->engine->sync($this->unit, [], $beds24);

        $ical = makeEngineConnector('ical', 'ical:beds24.com', [
            new NormalizedBooking(
                externalId: 'uid-aaa@beds24.com',
                checkIn: '2027-01-08',
                checkOut: '2027-01-11',
                guestName: 'Gudule Lapointe',
                status: 'confirmed',
            ),
        ]);
        $stats = $this->engine->sync($this->unit, [], $ical);

        expect($stats['new'])->toBe(0)
            ->and(Booking::count())->toBe(1)
            ->and(Booking::first()->sources)->toHaveCount(2);
    });

    it('records additional sources for information only, without modifying the booking', function () {
        $beds24 = makeEngineConnector('beds24', 'beds24', [
            new NormalizedBooking(
                externalId: '111',
                checkIn: '2027-01-08',
                checkOut: '2027-01-11',
                guestName: 'Gudule Lapointe',
                status: 'confirmed',
                email: 'gudule@example.com',
                claimsOrigin: true,
            ),
        ]);
        $this->engine->sync($this->unit, [], $beds24);

        $ical = makeEngineConnector('ical', 'ical:beds24.com', [
            new NormalizedBooking(
                externalId: 'uid-aaa@beds24.com',
                checkIn: '2027-01-08',
                checkOut: '2027-01-11',
                guestName: 'Someone Else',
                status: 'confirmed',
                email: 'gudule@example.com',
                adults: 4,
                metadata: ['phone' => '+590690000000'],
            ),
        ]);
        $stats = $this->engine->sync($this->unit, [], $ical);

        $booking = Booking::first();
        expect($stats['updated'])->toBe(0)
            ->and($booking->sources)->toHaveCount(2)
            ->and($booking->guest_name)->toBe('Gudule Lapointe')
            ->and($booking->adults)->toBeNull()
            ->and($booking->metadata)->not->toHaveKey('phone');
    });

    it('never lets a non-origin source change dates or price', function () {
        $beds24 = makeEngineConnector('beds24', 'beds24', [
            new NormalizedBooking(
                externalId: '111',
                checkIn: '2027-01-08',
                checkOut: '2027-01-11',
                guestName: 'Gudule Lapointe',
                status: 'confirmed',
                email: 'gudule@example.com',
                price: 500.0,
                claimsOrigin: true,
            ),
        ]);
        $this->engine->sync($this->unit, [], $beds24);

        $ical = makeEngineConnector('ical', 'ical:beds24.com', [
            new NormalizedBooking(
                externalId: 'uid-aaa@beds24.com',
                checkIn: '2027-01-08',
                checkOut: '2027-01-11',
                guestName: 'Gudule Lapointe',
                status: 'confirmed',
            ),
        ]);
        $this->engine->sync($this->unit, [], $ical);

        // The non-origin feed now reports different dates and price.
        $ical->bookings = [
            new NormalizedBooking(
                externalId: 'uid-aaa@beds24.com',
                checkIn: '2027-01-09',
                checkOut: '2027-01-12',
                guestName: 'Gudule Lapointe',
                status: 'confirmed',
                price: 999.0,
            ),
        ];
        $this->engine->sync($this->unit, [], $ical);

        $booking = Booking::first();
        expect($booking->check_in->format('Y-m-d'))->toBe('2027-01-08')
            ->and($booking->check_out->format('Y-m-d'))->toBe('2027-01-11')
            ->and((float) $booking->price)->toBe(500.0);
    });

    it('records an origin hint as a placeholder; an iCal feed takes it over without becoming origin', function () {
        // Beds24 reports a booking it imported from an external iCal feed.
        $beds24 = makeEngineConnector('beds24', 'beds24', [
            new NormalizedBooking(
                externalId: '222',
                checkIn: '2027-03-01',
                checkOut: '2027-03-08',
                guestName: 'Hector Berlioz',
                status: 'confirmed',
                price: 700.0,
                originHint: ['type' => 'ical', 'external_id' => 'uid-origin@gites-mosaiques.com'],
            ),
        ]);
        $this->engine->sync($this->unit, [], $beds24);

        $booking = Booking::first();
        expect($booking->sources)->toHaveCount(2);

        $beds24Ref = $booking->sources->firstWhere('source_key', 'beds24');
        $placeholder = $booking->sources->firstWhere('source_type', 'ical');
        expect($beds24Ref->is_origin)->toBeFalse()
            ->and($placeholder->is_origin)->toBeTrue()
            ->and($placeholder->isPlaceholder())->toBeTrue();

        // The feed carrying that UID takes over the placeholder, but iCal
        // sources can never reliably claim origin — Beds24 stays the acting
        // origin as the first source, so the feed's dates are ignored.
        $ical = makeEngineConnector('ical', 'ical:gites-mosaiques.com', [
            new NormalizedBooking(
                externalId: 'uid-origin@gites-mosaiques.com',
                checkIn: '2027-03-02',
                checkOut: '2027-03-08',
                guestName: 'Hector Berlioz',
                status: 'confirmed',
            ),
        ]);
        $stats = $this->engine->sync($this->unit, [], $ical);

        expect($stats['new'])->toBe(0)
            ->and(Booking::count())->toBe(1);

        $booking->refresh()->load('sources');
        $icalRef = $booking->sources->firstWhere('source_key', 'ical:gites-mosaiques.com');
        expect($booking->sources)->toHaveCount(2)
            ->and($icalRef)->not->toBeNull()
            ->and($icalRef->is_origin)->toBeFalse()
            ->and($icalRef->isPlaceholder())->toBeFalse()
            ->and($booking->check_in->format('Y-m-d'))->toBe('2027-03-01');
    });

    it('lets the first source sync when no source claims origin', function () {
        $ical = makeEngineConnector('ical', 'ical:airbnb', [
            new NormalizedBooking(
                externalId: 'uid-bbb@airbnb.com',
                checkIn: '2027-06-01',
                checkOut: '2027-06-08',
                guestName: 'Felicie Tropfort',
                status: 'confirmed',
            ),
        ]);
        $this->engine->sync($this->unit, [], $ical);

        $booking = Booking::first();
        expect($booking->sources->first()->is_origin)->toBeFalse();

        // The same feed updates dates — as the first (and only) source it syncs.
        $ical->bookings = [
            new NormalizedBooking(
                externalId: 'uid-bbb@airbnb.com',
                checkIn: '2027-06-02',
                checkOut: '2027-06-09',
                guestName: 'Felicie Tropfort',
                status: 'confirmed',
            ),
        ];
        $stats = $this->engine->sync($this->unit, [], $ical);

        expect($stats['updated'])->toBe(1)
            ->and(Booking::first()->check_in->format('Y-m-d'))->toBe('2027-06-02');
    });

    it('hands ownership to a source that reliably claims origin', function () {
        // An iCal feed found the booking first (no reliable origin).
        $ical = makeEngineConnector('ical', 'ical:beds24.com', [
            new NormalizedBooking(
                externalId: 'uid-ccc@beds24.com',
                checkIn: '2027-07-01',
                checkOut: '2027-07-08',
                guestName: 'Aline Verte',
                status: 'confirmed',
            ),
        ]);
        $this->engine->sync($this->unit, [], $ical);

        // Beds24 then reports the same booking as natively its own.
        $beds24 = makeEngineConnector('beds24', 'beds24', [
            new NormalizedBooking(
                externalId: '444',
                checkIn: '2027-07-01',
                checkOut: '2027-07-08',
                guestName: 'Aline Verte',
                status: 'confirmed',
                price: 800.0,
                claimsOrigin: true,
            ),
        ]);
        $this->engine->sync($this->unit, [], $beds24);

        $booking = Booking::first();
        $beds24Ref = $booking->sources->firstWhere('source_key', 'beds24');
        expect(Booking::count())->toBe(1)
            ->and($beds24Ref->is_origin)->toBeTrue()
            ->and((float) $booking->price)->toBe(800.0);

        // The feed no longer syncs anything, even though it was first.
        $ical->bookings = [
            new NormalizedBooking(
                externalId: 'uid-ccc@beds24.com',
                checkIn: '2027-07-02',
                checkOut: '2027-07-09',
                guestName: 'Aline Verte',
                status: 'confirmed',
            ),
        ];
        $stats = $this->engine->sync($this->unit, [], $ical);

        expect($stats['updated'])->toBe(0)
            ->and(Booking::first()->check_in->format('Y-m-d'))->toBe('2027-07-01');
    });

    it('attaches to a manual booking without claiming origin or overwriting', function () {
        $booking = Booking::create([
            'unit_id' => $this->unit->id,
            'property_id' => $this->property->id,
            'uid' => 'manual-1',
            'check_in' => '2027-04-01',
            'check_out' => '2027-04-05',
            'guest_name' => 'Manon Manuelle',
            'status' => 'confirmed',
            'is_manual' => true,
        ]);

        $connector = makeEngineConnector('beds24', 'beds24', [
            new NormalizedBooking(
                externalId: '333',
                checkIn: '2027-04-01',
                checkOut: '2027-04-05',
                guestName: 'Manon Manuelle',
                status: 'confirmed',
                price: 400.0,
                channel: 'beds24',
                claimsOrigin: true,
            ),
        ]);
        $this->engine->sync($this->unit, [], $connector);

        expect(Booking::count())->toBe(1);

        $booking->refresh()->load('sources');
        expect($booking->sources)->toHaveCount(1)
            ->and($booking->sources->first()->is_origin)->toBeFalse()
            ->and($booking->source_name)->toBeNull()
            ->and($booking->price)->toBeNull();
    });

    it('marks vanished bookings when the origin stops reporting them', function () {
        $connector = makeEngineConnector('beds24', 'beds24', [
            new NormalizedBooking(
                externalId: '111',
                checkIn: '2027-01-08',
                checkOut: '2027-01-11',
                guestName: 'Gudule Lapointe',
                status: 'confirmed',
            ),
        ]);
        $this->engine->sync($this->unit, [], $connector);

        $connector->bookings = [];
        $stats = $this->engine->sync($this->unit, [], $connector);

        expect($stats['vanished'])->toBe(1)
            ->and(Booking::first()->status)->toBe('vanished');
    });

    it('detaches the reference when a non-origin source stops reporting a booking', function () {
        $beds24 = makeEngineConnector('beds24', 'beds24', [
            new NormalizedBooking(
                externalId: '111',
                checkIn: '2027-01-08',
                checkOut: '2027-01-11',
                guestName: 'Gudule Lapointe',
                status: 'confirmed',
            ),
        ]);
        $this->engine->sync($this->unit, [], $beds24);

        $ical = makeEngineConnector('ical', 'ical:beds24.com', [
            new NormalizedBooking(
                externalId: 'uid-aaa@beds24.com',
                checkIn: '2027-01-08',
                checkOut: '2027-01-11',
                guestName: 'Gudule Lapointe',
                status: 'confirmed',
            ),
        ]);
        $this->engine->sync($this->unit, [], $ical);
        expect(BookingSource::count())->toBe(2);

        $ical->bookings = [];
        $stats = $this->engine->sync($this->unit, [], $ical);

        expect($stats['vanished'])->toBe(0)
            ->and(BookingSource::count())->toBe(1)
            ->and(Booking::first()->status)->toBe('confirmed');
    });

    it('never merges two bookings of the same source, even on identical dates and guest', function () {
        // Real confirmed booking, no amount (group sub style).
        $real = new NormalizedBooking(
            externalId: '79549562',
            checkIn: '2026-12-26',
            checkOut: '2027-01-02',
            guestName: 'Eric Antoine',
            status: 'confirmed',
            claimsOrigin: true,
        );
        $connector = makeEngineConnector('beds24', 'beds24', [$real]);
        $this->engine->sync($this->unit, [], $connector);
        expect(Booking::count())->toBe(1);

        // The same source also reports a cancelled booking on the exact same
        // dates and guest (a 'mauvaise manip' replaced by the real one). It
        // must become its own booking, not overwrite the confirmed one.
        $connector->bookings = [
            $real,
            new NormalizedBooking(
                externalId: '79643211',
                checkIn: '2026-12-26',
                checkOut: '2027-01-02',
                guestName: 'Eric Antoine',
                status: 'cancelled',
                price: 5145.0,
                claimsOrigin: true,
            ),
        ];
        $this->engine->sync($this->unit, [], $connector);

        expect(Booking::count())->toBe(2);

        $realBooking = Booking::whereHas('sources', fn ($q) => $q->where('external_id', '79549562'))->first();
        expect($realBooking->status)->toBe('confirmed')
            ->and($realBooking->getRawOriginal('price'))->toBeNull()
            ->and($realBooking->sources()->where('source_key', 'beds24')->count())->toBe(1);

        $cancelledBooking = Booking::whereHas('sources', fn ($q) => $q->where('external_id', '79643211'))->first();
        expect($cancelledBooking->id)->not->toBe($realBooking->id)
            ->and($cancelledBooking->status)->toBe('cancelled');
    });

    it('lets the real origin claim a placeholder typed by another source guess', function () {
        $hbookUid = 'D2025-12-14T07:57:39U693e6df31291b@https://gites-mosaiques.com';

        // Beds24 imports the booking first, declaring an iCal-typed origin
        // hint (referer 'iCal Import') — a placeholder origin is created.
        $beds24 = makeEngineConnector('beds24', 'beds24', [
            new NormalizedBooking(
                externalId: '79549562',
                checkIn: '2026-12-26',
                checkOut: '2027-01-02',
                guestName: 'Eric Antoine',
                status: 'confirmed',
                originHint: ['type' => 'ical', 'external_id' => $hbookUid],
            ),
        ]);
        $this->engine->sync($this->unit, [], $beds24);

        $booking = Booking::first();
        expect($booking->sources()->where('is_placeholder', true)->where('is_origin', true)->count())->toBe(1);

        // The actual origin connects later as hbook with the same uid.
        $hbook = makeEngineConnector('hbook', 'hbook:gites-mosaiques.com', [
            new NormalizedBooking(
                externalId: $hbookUid,
                checkIn: '2026-12-26',
                checkOut: '2027-01-02',
                guestName: 'Eric Antoine',
                status: 'confirmed',
                price: 5145.0,
                claimsOrigin: true,
            ),
        ]);
        $this->engine->sync($this->unit, [], $hbook);

        $booking->refresh();
        $origin = $booking->originSource;

        // The placeholder was taken over: hbook is now the real origin.
        expect(Booking::count())->toBe(1)
            ->and($booking->sources()->where('is_placeholder', true)->count())->toBe(0)
            ->and($origin->source_key)->toBe('hbook:gites-mosaiques.com')
            ->and($origin->external_id)->toBe($hbookUid)
            ->and((float) $booking->getRawOriginal('price'))->toBe(5145.0);
    });

    it('clears a stale price on group members the claiming origin reports priceless', function () {
        // The booking got a price from a first source: Beds24 saw it via an
        // iCal import, so it does not claim origin (placeholder hint only).
        $beds24 = makeEngineConnector('beds24', 'beds24', [
            new NormalizedBooking(
                externalId: '79549561',
                checkIn: '2026-12-26',
                checkOut: '2027-01-02',
                guestName: 'Eric Antoine',
                status: 'confirmed',
                price: 5145.0,
                originHint: ['type' => 'ical', 'external_id' => 'uid'],
                groupId: '79549561',
            ),
        ]);
        $this->engine->sync($this->unit, [], $beds24);
        expect((float) Booking::first()->getRawOriginal('price'))->toBe(5145.0);

        // …then the real origin claims it as a non-carrier group member:
        // the group total lives on another member, this one must not keep
        // the old price or the group sum doubles.
        $hbook = makeEngineConnector('hbook', 'hbook:site', [
            new NormalizedBooking(
                externalId: 'uid#3573_1',
                checkIn: '2026-12-26',
                checkOut: '2027-01-02',
                guestName: 'Eric Antoine',
                status: 'confirmed',
                price: null,
                claimsOrigin: true,
                groupId: 'hbook-12',
            ),
        ]);
        $this->engine->sync($this->unit, [], $hbook);

        $booking = Booking::first();
        expect($booking->getRawOriginal('price'))->toBeNull()
            ->and((string) $booking->getRawOriginal('group_id'))->toBe('hbook-12');
    });

    it('lets the acting origin clear a price down to zero', function () {
        $connector = makeEngineConnector('beds24', 'beds24', [
            new NormalizedBooking(externalId: '700', checkIn: '2027-06-01', checkOut: '2027-06-05', guestName: 'Zeroable', status: 'confirmed', price: 500.0, claimsOrigin: true),
        ]);
        $this->engine->sync($this->unit, [], $connector);
        expect((float) Booking::first()->getRawOriginal('price'))->toBe(500.0);

        // Same booking, price emptied in the source.
        $connector->bookings = [
            new NormalizedBooking(externalId: '700', checkIn: '2027-06-01', checkOut: '2027-06-05', guestName: 'Zeroable', status: 'confirmed', price: 0.0, claimsOrigin: true),
        ];
        $stats = $this->engine->sync($this->unit, [], $connector);

        expect($stats['updated'])->toBe(1)
            ->and((float) Booking::first()->getRawOriginal('price'))->toBe(0.0);
    });

    it('never touches the price when the source reports it as unknown (null)', function () {
        $connector = makeEngineConnector('beds24', 'beds24', [
            new NormalizedBooking(externalId: '701', checkIn: '2027-06-01', checkOut: '2027-06-05', guestName: 'Keepme', status: 'confirmed', price: 700.0, claimsOrigin: true),
        ]);
        $this->engine->sync($this->unit, [], $connector);

        $connector->bookings = [
            new NormalizedBooking(externalId: '701', checkIn: '2027-06-01', checkOut: '2027-06-05', guestName: 'Keepme', status: 'confirmed', price: null, claimsOrigin: true),
        ];
        $this->engine->sync($this->unit, [], $connector);

        expect((float) Booking::first()->getRawOriginal('price'))->toBe(700.0);
    });

    it('aligns created_at and updated_at on the source dates', function () {
        $connector = makeEngineConnector('beds24', 'beds24', [
            new NormalizedBooking(
                externalId: '500',
                checkIn: '2027-03-01',
                checkOut: '2027-03-08',
                guestName: 'Dated Guest',
                status: 'confirmed',
                price: 700.0,
                claimsOrigin: true,
                sourceCreatedAt: '2026-04-16 00:01:08',
                sourceUpdatedAt: '2026-05-02 10:30:00',
            ),
        ]);
        $this->engine->sync($this->unit, [], $connector);

        $booking = Booking::first();
        expect($booking->created_at->format('Y-m-d H:i:s'))->toBe('2026-04-16 00:01:08')
            ->and($booking->updated_at->format('Y-m-d H:i:s'))->toBe('2026-05-02 10:30:00');

        // A later modification at the source moves updated_at to the
        // source's date, not to the sync time.
        $connector->bookings = [
            new NormalizedBooking(
                externalId: '500',
                checkIn: '2027-03-01',
                checkOut: '2027-03-08',
                guestName: 'Dated Guest',
                status: 'confirmed',
                price: 850.0,
                claimsOrigin: true,
                sourceCreatedAt: '2026-04-16 00:01:08',
                sourceUpdatedAt: '2026-06-01 08:00:00',
            ),
        ];
        $stats = $this->engine->sync($this->unit, [], $connector);

        $booking->refresh();
        expect($stats['updated'])->toBe(1)
            ->and((float) $booking->getRawOriginal('price'))->toBe(850.0)
            ->and($booking->updated_at->format('Y-m-d H:i:s'))->toBe('2026-06-01 08:00:00')
            ->and($booking->created_at->format('Y-m-d H:i:s'))->toBe('2026-04-16 00:01:08');

        // Stable when nothing moved at the source.
        $stats = $this->engine->sync($this->unit, [], $connector);
        expect($stats['updated'])->toBe(0);
    });

    it('deletes auto-generated availability blocks when they vanish', function () {
        $connector = makeEngineConnector('ical', 'ical:airbnb', [
            new NormalizedBooking(
                externalId: 'uid-block@airbnb.com',
                checkIn: '2027-05-01',
                checkOut: '2027-05-10',
                guestName: 'Unavailable',
                status: 'blocked',
            ),
        ]);
        $this->engine->sync($this->unit, [], $connector);
        expect(Booking::count())->toBe(1);

        $connector->bookings = [];
        $stats = $this->engine->sync($this->unit, [], $connector);

        expect($stats['deleted'])->toBe(1)
            ->and(Booking::count())->toBe(0);
    });

    it('does not write anything in dry-run mode', function () {
        $connector = makeEngineConnector('beds24', 'beds24', [
            new NormalizedBooking(
                externalId: '111',
                checkIn: '2027-01-08',
                checkOut: '2027-01-11',
                guestName: 'Gudule Lapointe',
                status: 'confirmed',
            ),
        ]);

        $stats = $this->engine->sync($this->unit, [], $connector, dryRun: true);

        expect($stats['new'])->toBe(1)
            ->and(Booking::count())->toBe(0)
            ->and(BookingSource::count())->toBe(0);
    });

    it('returns a failure result when the connector throws', function () {
        $connector = new class implements SourceConnector
        {
            public function sourceType(): string
            {
                return 'broken';
            }

            public function label(): string
            {
                return 'Broken';
            }

            public function displayLabel(array $sourceConfig): string
            {
                return 'Broken';
            }

            public function sourceKey(Unit $unit, array $sourceConfig): string
            {
                return 'broken';
            }

            public function fetchBookings(Unit $unit, array $sourceConfig): array
            {
                throw new RuntimeException('Source unreachable');
            }

            public function externalBookingUrl(BookingSource $source): ?string
            {
                return null;
            }
        };

        $stats = (new SyncEngine)->sync($this->unit, [], $connector);

        expect($stats['success'])->toBeFalse()
            ->and($stats['error'])->toBe('Source unreachable');
    });
});
