<?php

use App\Sync\Contracts\SourceConnector;
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
        'request' => ['3', 'option'],
        'black' => ['4', 'blocked'],
        'inquiry' => ['5', 'quote'],
    ]);

    it('reports a definitive zero price when the invoice is emptied', function () {
        $connector = makeBeds24Connector([
            ['bookId' => '300', 'roomId' => '42', 'firstNight' => '2027-05-01', 'lastNight' => '2027-05-05', 'status' => '1', 'guestName' => 'Zeroed Guest', 'price' => '0',
                'invoice' => [['type' => '1', 'description' => 'Hébergement', 'price' => '0']]],
        ]);

        $bookings = $connector->fetchBookings($this->unit, ['type' => 'beds24', 'room_id' => 42]);

        expect($bookings)->toHaveCount(1)
            ->and($bookings[0]->price)->toBe(0.0);
    });

    it('always emits amount metadata as zero so emptied bookings clear them', function () {
        $connector = makeBeds24Connector([
            ['bookId' => '302', 'roomId' => '42', 'firstNight' => '2027-05-01', 'lastNight' => '2027-05-05', 'status' => '1', 'guestName' => 'Emptied Guest', 'price' => '0', 'commission' => '0', 'deposit' => '0'],
        ]);

        $booking = $connector->fetchBookings($this->unit, ['type' => 'beds24', 'room_id' => 42])[0];

        expect($booking->commission)->toBe(0.0)
            ->and($booking->metadata['deposit'])->toBe(0.0)
            ->and($booking->metadata['invoice_payment_total'])->toBe(0.0)
            ->and($booking->metadata['invoice_total'])->toBe(0.0)
            ->and($booking->metadata['invoice_lines'])->toBe([]);
    });

    it('reflects a Beds24 price field of zero as a definitive zero (booking zeroed in Beds24)', function () {
        $connector = makeBeds24Connector([
            ['bookId' => '301', 'roomId' => '42', 'firstNight' => '2027-05-01', 'lastNight' => '2027-05-05', 'status' => '1', 'guestName' => 'Zeroed Solo', 'price' => '0'],
        ]);

        $bookings = $connector->fetchBookings($this->unit, ['type' => 'beds24', 'room_id' => 42]);

        expect($bookings)->toHaveCount(1)
            ->and($bookings[0]->price)->toBe(0.0);
    });

    it('reports a null price for a group member without its own invoice', function () {
        $connector = makeBeds24Connector([
            ['bookId' => '400', 'masterId' => '400', 'group' => ['401'], 'roomId' => '10', 'firstNight' => '2027-05-01', 'lastNight' => '2027-05-05', 'status' => '1', 'guestName' => 'Group Lead', 'price' => '3000'],
            ['bookId' => '401', 'masterId' => '400', 'roomId' => '42', 'firstNight' => '2027-05-01', 'lastNight' => '2027-05-05', 'status' => '3', 'price' => '3000'],
        ]);

        $bookings = $connector->fetchBookings($this->unit, ['type' => 'beds24', 'room_id' => 42]);

        expect($bookings)->toHaveCount(1)
            ->and($bookings[0]->price)->toBeNull();
    });

    it('tags New bookings with is_new metadata while keeping them confirmed', function () {
        $connector = makeBeds24Connector([
            ['bookId' => '1', 'roomId' => '42', 'firstNight' => '2027-01-01', 'lastNight' => '2027-01-02', 'status' => '2', 'guestName' => 'Fresh Guest', 'price' => '100'],
            ['bookId' => '2', 'roomId' => '42', 'firstNight' => '2027-01-05', 'lastNight' => '2027-01-06', 'status' => '1', 'guestName' => 'Old Guest', 'price' => '100'],
        ]);

        $bookings = $connector->fetchBookings($this->unit, ['type' => 'beds24', 'room_id' => 42]);

        expect($bookings[0]->status)->toBe('confirmed')
            ->and($bookings[0]->metadata['is_new'])->toBeTrue()
            ->and($bookings[1]->status)->toBe('confirmed')
            ->and($bookings[1]->metadata['is_new'])->toBeFalse();
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
            ])
            ->and($bookings[0]->claimsOrigin)->toBeFalse();
    });

    it('imports future blocks, skips past blocks and rows from other rooms', function () {
        $past = now()->subDays(10)->format('Y-m-d');
        $connector = makeBeds24Connector([
            // Future Black block: imported as 'blocked' even without guest or price.
            ['bookId' => '1', 'roomId' => '42', 'firstNight' => '2027-01-01', 'lastNight' => '2027-01-02', 'status' => '4'],
            // Past Black block: availability artifact, skipped.
            ['bookId' => '4', 'roomId' => '42', 'firstNight' => $past, 'lastNight' => $past, 'status' => '4'],
            ['bookId' => '2', 'roomId' => '99', 'firstNight' => '2027-01-01', 'lastNight' => '2027-01-02', 'status' => '2', 'guestName' => 'OtherRoom'],
            ['bookId' => '3', 'roomId' => '42', 'firstNight' => '2027-01-05', 'lastNight' => '2027-01-06', 'status' => '2', 'guestName' => 'Keeper', 'price' => '100'],
        ]);

        $bookings = $connector->fetchBookings($this->unit, ['type' => 'beds24', 'room_id' => 42]);

        expect($bookings)->toHaveCount(2)
            ->and($bookings[0]->externalId)->toBe('1')
            ->and($bookings[0]->status)->toBe('blocked')
            ->and($bookings[1]->externalId)->toBe('3');
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

    it('keeps group sub-bookings as options when the master is not confirmed', function () {
        $connector = makeBeds24Connector([
            ['bookId' => '100', 'masterId' => '100', 'group' => ['101'], 'roomId' => '10', 'firstNight' => '2027-02-01', 'lastNight' => '2027-02-05', 'status' => '3', 'guestName' => 'Groupe Peeva', 'price' => '9600'],
            ['bookId' => '101', 'masterId' => '100', 'roomId' => '42', 'firstNight' => '2027-02-01', 'lastNight' => '2027-02-05', 'status' => '3', 'price' => '4800'],
        ]);

        $bookings = $connector->fetchBookings($this->unit, ['type' => 'beds24', 'room_id' => 42]);

        expect($bookings)->toHaveCount(1)
            ->and($bookings[0]->status)->toBe('option')
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

    it('never uses the replicated price field for group sub-bookings', function () {
        // Beds24 copies the group total into every sub's price field;
        // summing them would multiply the group price by the unit count.
        $connector = makeBeds24Connector([
            [
                'bookId' => '100', 'masterId' => '100', 'group' => ['101'], 'roomId' => '10',
                'firstNight' => '2027-02-01', 'lastNight' => '2027-02-05', 'status' => '1',
                'guestName' => 'Combat Ouvrier', 'price' => '3000',
                'invoice' => [['type' => '0', 'description' => 'Forfait site entier', 'price' => '3000']],
            ],
            [
                'bookId' => '101', 'masterId' => '100', 'roomId' => '42',
                'firstNight' => '2027-02-01', 'lastNight' => '2027-02-05', 'status' => '1',
                'guestName' => 'Combat Ouvrier', 'price' => '3000',
            ],
        ]);

        $bookings = $connector->fetchBookings($this->unit, ['type' => 'beds24', 'room_id' => 42]);

        expect($bookings)->toHaveCount(1)
            ->and($bookings[0]->price)->toBeNull()
            ->and($bookings[0]->metadata['group_total'])->toBe(3000.0);
    });

    it('keeps per-unit prices from the sub-booking own invoice', function () {
        $connector = makeBeds24Connector([
            [
                'bookId' => '100', 'masterId' => '100', 'group' => ['101'], 'roomId' => '10',
                'firstNight' => '2027-02-01', 'lastNight' => '2027-02-05', 'status' => '1',
                'guestName' => 'Myriam Masson', 'price' => '700',
                'invoice' => [['type' => '1', 'description' => 'Hébergement', 'price' => '700']],
            ],
            [
                'bookId' => '101', 'masterId' => '100', 'roomId' => '42',
                'firstNight' => '2027-02-01', 'lastNight' => '2027-02-05', 'status' => '1',
                'guestName' => 'Myriam Masson', 'price' => '700',
                'invoice' => [['type' => '1', 'description' => 'Hébergement', 'price' => '1050']],
            ],
        ]);

        $bookings = $connector->fetchBookings($this->unit, ['type' => 'beds24', 'room_id' => 42]);

        expect($bookings)->toHaveCount(1)
            ->and($bookings[0]->price)->toBe(1050.0)
            ->and($bookings[0]->metadata['group_total'])->toBe(700.0);
    });

    it('stores the full invoice line detail in metadata', function () {
        $connector = makeBeds24Connector([
            [
                'bookId' => '200', 'roomId' => '42', 'firstNight' => '2027-03-01', 'lastNight' => '2027-03-05',
                'status' => '1', 'guestName' => 'Detail Guest', 'price' => '0', 'deposit' => '150', 'tax' => '12',
                'invoice' => [
                    ['type' => '1', 'description' => 'Hébergement', 'qty' => '4', 'price' => '500'],
                    ['type' => '0', 'description' => 'Remise', 'price' => '-50'],
                    ['type' => '0', 'description' => 'Taxe de séjour', 'price' => '12'],
                    ['type' => '200', 'description' => 'Paiement', 'price' => '462'],
                ],
            ],
        ]);

        $bookings = $connector->fetchBookings($this->unit, ['type' => 'beds24', 'room_id' => 42]);
        $meta = $bookings[0]->metadata;

        expect($meta['invoice_lines'])->toHaveCount(4)
            ->and($meta['invoice_lines'][0])->toBe(['type' => '1', 'description' => 'Hébergement', 'qty' => 4.0, 'price' => 500.0, 'amount' => 2000.0])
            ->and($meta['invoice_total'])->toBe(1962.0)
            ->and($meta['invoice_acc_ttc'])->toBe(1950.0)
            ->and($meta['invoice_taxe_invoiced'])->toBe(12.0)
            ->and($meta['invoice_payment_total'])->toBe(462.0)
            ->and($meta['deposit'])->toBe(150.0)
            ->and($meta['tax'])->toBe(12.0)
            ->and($bookings[0]->price)->toBe(1962.0);
    });

    it('includes every invoice line in the price, taxe de séjour included', function () {
        // Real-world case: 400 € accommodation + 40 € taxe de séjour →
        // the client owes 440 €, not 400 €.
        $connector = makeBeds24Connector([
            [
                'bookId' => '201', 'roomId' => '42', 'firstNight' => '2027-07-02', 'lastNight' => '2027-07-03',
                'status' => '1', 'guestName' => 'Annabel Demuth', 'price' => '400',
                'invoice' => [
                    ['type' => '1', 'description' => 'Zetoil, 4 bedrooms cottage', 'qty' => '1', 'price' => '400'],
                    ['type' => '0', 'description' => 'Taxe de séjour ville de Sainte-Rose, 5% par adulte', 'qty' => '1', 'price' => '40'],
                ],
            ],
        ]);

        $bookings = $connector->fetchBookings($this->unit, ['type' => 'beds24', 'room_id' => 42]);

        expect($bookings[0]->price)->toBe(440.0)
            ->and($bookings[0]->metadata['invoice_taxe_invoiced'])->toBe(40.0);
    });

    it('multiplies unit prices by quantity', function () {
        // Beds24 invoices carry unit prices: 7 nights at 100 €/night come
        // as qty=7, price=100. Payments come with qty=-1.
        $connector = makeBeds24Connector([
            [
                'bookId' => '202', 'roomId' => '42', 'firstNight' => '2027-08-01', 'lastNight' => '2027-08-07',
                'status' => '1', 'guestName' => 'Long Stay', 'price' => '100',
                'invoice' => [
                    ['type' => '0', 'description' => 'Rental', 'qty' => '7', 'price' => '100'],
                    ['type' => '200', 'description' => 'Paypal', 'qty' => '-1', 'price' => '300'],
                ],
            ],
        ]);

        $bookings = $connector->fetchBookings($this->unit, ['type' => 'beds24', 'room_id' => 42]);

        expect($bookings[0]->price)->toBe(700.0)
            ->and($bookings[0]->metadata['invoice_payment_total'])->toBe(300.0);
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
