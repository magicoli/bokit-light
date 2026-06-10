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
            ->and($booking->legacyUid)->toBe('beds24-79643287');
    });

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
            ]);
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
});
