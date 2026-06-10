<?php

use App\Contracts\SourceConnector;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Beds24\Services\Beds24Connector;

uses(RefreshDatabase::class);

/**
 * Beds24Connector with the API call stubbed out.
 */
function makeBeds24Connector(array $rows): Beds24Connector
{
    return new class($rows) extends Beds24Connector
    {
        public function __construct(private array $rows) {}

        protected function fetchRows(Property $property): array
        {
            return $this->rows;
        }
    };
}

describe('Beds24Connector', function () {
    beforeEach(function () {
        $this->property = Property::create(['name' => 'B24P', 'slug' => 'b24-p', 'is_active' => true]);
        $this->unit = Unit::create(['property_id' => $this->property->id, 'name' => 'B24U', 'slug' => 'b24-u', 'is_active' => true]);
        $this->unit->setRelation('property', $this->property);
    });

    it('implements SourceConnector', function () {
        expect(new Beds24Connector)->toBeInstanceOf(SourceConnector::class);
    });

    it('has source type beds24', function () {
        expect((new Beds24Connector)->sourceType())->toBe('beds24');
    });

    it('throws when no room_id is configured', function () {
        makeBeds24Connector([])->fetchBookings($this->unit, ['type' => 'beds24']);
    })->throws(RuntimeException::class, 'room_id');

    it('normalizes a booking row', function () {
        $connector = makeBeds24Connector([
            [
                'bookId' => '79643287',
                'roomId' => '42',
                'firstNight' => '2027-01-08',
                'lastNight' => '2027-01-10',
                'guestFirstName' => 'Gudule',
                'guestName' => 'Lapointe',
                'guestEmail' => 'gudule@example.com',
                'status' => '2',
                'price' => '500.00',
                'numAdult' => '2',
                'numChild' => '1',
                'apiSource' => '46',
                'referer' => 'magicoli',
            ],
        ]);

        $bookings = $connector->fetchBookings($this->unit, ['type' => 'beds24', 'room_id' => 42]);

        expect($bookings)->toHaveCount(1);

        $booking = $bookings[0];
        expect($booking->externalId)->toBe('79643287')
            ->and($booking->checkIn)->toBe('2027-01-08')
            ->and($booking->checkOut)->toBe('2027-01-11')
            ->and($booking->guestName)->toBe('Gudule Lapointe')
            ->and($booking->status)->toBe('confirmed')
            ->and($booking->email)->toBe('gudule@example.com')
            ->and($booking->price)->toBe(500.0)
            ->and($booking->adults)->toBe(2)
            ->and($booking->children)->toBe(1)
            ->and($booking->channel)->toBe('airbnb')
            ->and($booking->originHint)->toBeNull()
            ->and($booking->legacyUid)->toBe('beds24-79643287')
            ->and($booking->claimsOrigin)->toBeTrue();
    });

    it('maps Beds24 v1 status codes correctly', function (string $rawStatus, string $expected) {
        $connector = makeBeds24Connector([
            ['bookId' => '1', 'roomId' => '42', 'firstNight' => '2027-01-01', 'lastNight' => '2027-01-02', 'status' => $rawStatus, 'guestName' => 'Guest X', 'price' => '100'],
        ]);

        $bookings = $connector->fetchBookings($this->unit, ['type' => 'beds24', 'room_id' => 42]);

        expect($bookings[0]->status)->toBe($expected);
    })->with([
        'cancelled' => ['0', 'cancelled'],
        'confirmed' => ['1', 'confirmed'],
        'new' => ['2', 'confirmed'],
        'request' => ['3', 'pending'],
    ]);

    it('sets an origin hint for iCal-imported bookings, trimming the uid', function () {
        $connector = makeBeds24Connector([
            [
                'bookId' => '79549562',
                'roomId' => '42',
                'firstNight' => '2027-02-01',
                'lastNight' => '2027-02-04',
                'guestFirstName' => 'Hector',
                'guestName' => 'Berlioz',
                'status' => '1',
                'price' => '300.00',
                'referer' => 'iCal Import 2',
                'apiReference' => "uid-origin@gites-mosaiques.com\r",
            ],
        ]);

        $bookings = $connector->fetchBookings($this->unit, ['type' => 'beds24', 'room_id' => 42]);

        expect($bookings)->toHaveCount(1)
            ->and($bookings[0]->originHint)->toBe([
                'type' => 'ical',
                'external_id' => 'uid-origin@gites-mosaiques.com',
            ])
            ->and($bookings[0]->claimsOrigin)->toBeFalse();
    });

    it('skips availability blocks and rows from other rooms', function () {
        $connector = makeBeds24Connector([
            ['bookId' => '1', 'roomId' => '42', 'firstNight' => '2027-01-01', 'lastNight' => '2027-01-02', 'status' => '4', 'guestName' => 'Block'],
            ['bookId' => '2', 'roomId' => '99', 'firstNight' => '2027-01-01', 'lastNight' => '2027-01-02', 'status' => '2', 'guestName' => 'OtherRoom'],
            ['bookId' => '3', 'roomId' => '42', 'firstNight' => '2027-01-05', 'lastNight' => '2027-01-06', 'status' => '2', 'guestName' => 'Keeper', 'price' => '100'],
        ]);

        $bookings = $connector->fetchBookings($this->unit, ['type' => 'beds24', 'room_id' => 42]);

        expect($bookings)->toHaveCount(1)
            ->and($bookings[0]->externalId)->toBe('3');
    });

    it('skips empty placeholder rows with no guest and no money', function () {
        $connector = makeBeds24Connector([
            ['bookId' => '1', 'roomId' => '42', 'firstNight' => '2027-01-01', 'lastNight' => '2027-01-02', 'status' => '2'],
        ]);

        expect($connector->fetchBookings($this->unit, ['type' => 'beds24', 'room_id' => 42]))->toBeEmpty();
    });

    it('imports group sub-bookings as confirmed when the master is confirmed', function () {
        $connector = makeBeds24Connector([
            // Master in another room, confirmed, carries the group field.
            ['bookId' => '100', 'masterId' => '100', 'group' => ['101'], 'roomId' => '10', 'firstNight' => '2027-02-01', 'lastNight' => '2027-02-05', 'status' => '1', 'guestName' => 'Groupe Kervella', 'price' => '0'],
            // Sub-booking in our room: placeholder status 3, no guest, no money.
            ['bookId' => '101', 'masterId' => '100', 'roomId' => '42', 'firstNight' => '2027-02-01', 'lastNight' => '2027-02-05', 'status' => '3'],
        ]);

        $bookings = $connector->fetchBookings($this->unit, ['type' => 'beds24', 'room_id' => 42]);

        expect($bookings)->toHaveCount(1);

        $sub = $bookings[0];
        expect($sub->externalId)->toBe('101')
            ->and($sub->status)->toBe('confirmed')
            ->and($sub->guestName)->toBe('Groupe Kervella')
            ->and($sub->groupId)->toBe('100');
    });

    it('keeps group sub-bookings pending when the master is not confirmed', function () {
        $connector = makeBeds24Connector([
            ['bookId' => '100', 'masterId' => '100', 'group' => ['101'], 'roomId' => '10', 'firstNight' => '2027-02-01', 'lastNight' => '2027-02-05', 'status' => '3', 'guestName' => 'Groupe Peeva', 'price' => '9600'],
            ['bookId' => '101', 'masterId' => '100', 'roomId' => '42', 'firstNight' => '2027-02-01', 'lastNight' => '2027-02-05', 'status' => '3', 'price' => '4800'],
        ]);

        $bookings = $connector->fetchBookings($this->unit, ['type' => 'beds24', 'room_id' => 42]);

        expect($bookings)->toHaveCount(1)
            ->and($bookings[0]->status)->toBe('pending')
            ->and($bookings[0]->guestName)->toBe('Groupe Peeva');
    });

    it('reports no price for a group master without invoice', function () {
        $connector = makeBeds24Connector([
            ['bookId' => '100', 'masterId' => '100', 'group' => ['101'], 'roomId' => '42', 'firstNight' => '2027-02-01', 'lastNight' => '2027-02-05', 'status' => '1', 'guestName' => 'Groupe CESL', 'price' => '2100'],
        ]);

        $bookings = $connector->fetchBookings($this->unit, ['type' => 'beds24', 'room_id' => 42]);

        expect($bookings)->toHaveCount(1)
            ->and($bookings[0]->price)->toBeNull()
            ->and($bookings[0]->groupId)->toBe('100');
    });

    it('uses payment lines when the invoice nets to zero on a standalone booking', function () {
        $connector = makeBeds24Connector([
            [
                'bookId' => '200', 'roomId' => '42', 'firstNight' => '2027-03-01', 'lastNight' => '2027-03-05',
                'status' => '1', 'guestName' => 'Remise Totale', 'price' => '0',
                'invoice' => [
                    ['type' => '1', 'description' => 'Hébergement', 'price' => '500'],
                    ['type' => '0', 'description' => 'Remise exceptionnelle', 'price' => '-500'],
                    ['type' => '200', 'description' => 'Paiement CB', 'price' => '450'],
                ],
            ],
        ]);

        $bookings = $connector->fetchBookings($this->unit, ['type' => 'beds24', 'room_id' => 42]);

        expect($bookings)->toHaveCount(1)
            ->and($bookings[0]->price)->toBe(450.0);
    });
});
