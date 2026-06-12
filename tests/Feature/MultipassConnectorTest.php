<?php

use App\Models\Booking;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Multipass\Services\MultipassConnector;

uses(RefreshDatabase::class);

/**
 * Connector with canned WP endpoint prestations instead of HTTP.
 */
function makeMultipassConnector(array $rows): MultipassConnector
{
    return new class($rows) extends MultipassConnector
    {
        public function __construct(private array $rows) {}

        protected function fetchRows(Property $property): array
        {
            return $this->rows;
        }
    };
}

/**
 * A raw prestation as returned by /wp-json/bokit/v1/bookings/multipass.
 */
function multipassRow(array $overrides = []): array
{
    return array_merge([
        'id' => 501,
        'title' => 'Gudule Lapointe',
        'status' => 'publish',
        'check_in' => '2024-04-01',
        'check_out' => '2024-04-08',
        'total' => 980.0,
        'deposit' => 294.0,
        'paid' => 980.0,
        'origin' => null,
        'adults' => 2,
        'children' => 1,
        'babies' => null,
        'contact_name' => 'Gudule Lapointe',
        'contact_email' => 'gudule@example.com',
        'contact_phone' => '+590690000000',
        'created_at' => '2024-01-15 09:00:00',
        'updated_at' => '2024-02-01 18:30:00',
        'units' => [
            ['detail_id' => 7001, 'status' => 'publish', 'unit' => 'Sun', 'resource_id' => 9587, 'check_in' => '2024-04-01', 'check_out' => '2024-04-08', 'subtotal' => 900.0],
        ],
    ], $overrides);
}

describe('MultipassConnector', function () {
    beforeEach(function () {
        $this->property = Property::create([
            'name' => 'Mosaiques',
            'slug' => 'mosaiques',
            'is_active' => true,
            'options' => ['wp_url' => 'https://gites-mosaiques.com', 'wp_user' => 'x', 'wp_app_password' => 'y'],
        ]);
        $this->unit = Unit::create([
            'property_id' => $this->property->id,
            'name' => 'Sun',
            'slug' => 'sun',
            'is_active' => true,
            'options' => ['sources' => [['type' => 'multipass', 'multipass_unit_id' => '9587', 'enabled' => true]]],
        ]);
        $this->moon = Unit::create([
            'property_id' => $this->property->id,
            'name' => 'Moon',
            'slug' => 'moon',
            'is_active' => true,
            'options' => ['sources' => [['type' => 'multipass', 'multipass_unit_id' => '9586', 'enabled' => true]]],
        ]);
        $this->unit->setRelation('property', $this->property->load('units'));
        $this->moon->setRelation('property', $this->property);
    });

    it('normalizes a solo prestation with the full total and source timestamps', function () {
        $connector = makeMultipassConnector([multipassRow()]);

        $bookings = $connector->fetchBookings($this->unit, ['type' => 'multipass', 'multipass_unit_id' => '9587']);

        expect($bookings)->toHaveCount(1);

        $booking = $bookings[0];
        expect($booking->externalId)->toBe('501')
            ->and($booking->guestName)->toBe('Gudule Lapointe')
            ->and($booking->status)->toBe('confirmed')
            ->and($booking->price)->toBe(980.0)
            ->and($booking->adults)->toBe(2)
            ->and($booking->channel)->toBe('multipass')
            ->and($booking->claimsOrigin)->toBeFalse()
            ->and($booking->groupId)->toBeNull()
            ->and($booking->metadata['paid'])->toBe(980.0)
            ->and($booking->metadata['deposit'])->toBe(294.0)
            ->and($booking->sourceCreatedAt)->toBe('2024-01-15 09:00:00')
            ->and($booking->sourceUpdatedAt)->toBe('2024-02-01 18:30:00');
    });

    it('keeps the OTA origin as channel when present', function () {
        $connector = makeMultipassConnector([multipassRow(['origin' => 'bookingcom'])]);

        $bookings = $connector->fetchBookings($this->unit, ['type' => 'multipass', 'multipass_unit_id' => '9587']);

        expect($bookings[0]->channel)->toBe('bookingcom');
    });

    it('maps prestation statuses to canonical statuses', function (string $raw, string $expected) {
        $connector = makeMultipassConnector([multipassRow(['status' => $raw])]);

        $bookings = $connector->fetchBookings($this->unit, ['type' => 'multipass', 'multipass_unit_id' => '9587']);

        expect($bookings[0]->status)->toBe($expected);
    })->with([
        'publish' => ['publish', 'confirmed'],
        'canceled' => ['canceled', 'cancelled'],
        'open' => ['open', 'option'],
        'draft' => ['draft', 'quote'],
    ]);

    it('ignores prestations without a detail on this unit', function () {
        $connector = makeMultipassConnector([multipassRow([
            'units' => [
                ['detail_id' => 7002, 'status' => 'publish', 'unit' => 'Moon', 'resource_id' => 9586, 'check_in' => '2024-04-01', 'check_out' => '2024-04-08', 'subtotal' => 900.0],
            ],
        ])]);

        expect($connector->fetchBookings($this->unit, ['type' => 'multipass', 'multipass_unit_id' => '9587']))
            ->toBeEmpty();
    });

    it('treats service resources as part of the total, not as occupancies', function () {
        // Lodging + car rental: solo booking carrying the full total.
        $connector = makeMultipassConnector([multipassRow([
            'total' => 1480.0,
            'units' => [
                ['detail_id' => 7001, 'status' => 'publish', 'unit' => 'Sun', 'resource_id' => 9587, 'check_in' => '2024-04-01', 'check_out' => '2024-04-08', 'subtotal' => 900.0],
                ['detail_id' => 7003, 'status' => 'publish', 'unit' => 'Hyundai H1', 'resource_id' => 9904, 'check_in' => '2024-04-01', 'check_out' => '2024-04-08', 'subtotal' => 500.0],
            ],
        ])]);

        $bookings = $connector->fetchBookings($this->unit, ['type' => 'multipass', 'multipass_unit_id' => '9587']);

        expect($bookings)->toHaveCount(1)
            ->and($bookings[0]->groupId)->toBeNull()
            ->and($bookings[0]->price)->toBe(1480.0);
    });

    it('splits group prestations: members keep their subtotal, the carrier absorbs the rest', function () {
        $rows = [multipassRow([
            'total' => 2000.0,
            'units' => [
                ['detail_id' => 7001, 'status' => 'publish', 'unit' => 'Sun', 'resource_id' => 9587, 'check_in' => '2024-04-02', 'check_out' => '2024-04-08', 'subtotal' => 900.0],
                ['detail_id' => 7002, 'status' => 'publish', 'unit' => 'Moon', 'resource_id' => 9586, 'check_in' => '2024-04-01', 'check_out' => '2024-04-07', 'subtotal' => 800.0],
                // Car rental: folds into the carrier's remainder.
                ['detail_id' => 7003, 'status' => 'publish', 'unit' => 'Defender', 'resource_id' => 13353, 'check_in' => '2024-04-01', 'check_out' => '2024-04-08', 'subtotal' => 300.0],
            ],
        ])];

        // Moon (9586) — lowest resource id: carrier, total minus Sun's subtotal.
        $moonBookings = makeMultipassConnector($rows)->fetchBookings($this->moon, ['type' => 'multipass', 'multipass_unit_id' => '9586']);
        expect($moonBookings)->toHaveCount(1)
            ->and($moonBookings[0]->externalId)->toBe('501#9586')
            ->and($moonBookings[0]->groupId)->toBe('multipass-501')
            ->and($moonBookings[0]->price)->toBe(1100.0)
            ->and($moonBookings[0]->checkIn)->toBe('2024-04-01')
            ->and($moonBookings[0]->metadata['paid'])->toBe(980.0)
            ->and($moonBookings[0]->metadata['group_total'])->toBe(2000.0);

        // Sun (9587) — member with its own subtotal and dates.
        $sunBookings = makeMultipassConnector($rows)->fetchBookings($this->unit, ['type' => 'multipass', 'multipass_unit_id' => '9587']);
        expect($sunBookings)->toHaveCount(1)
            ->and($sunBookings[0]->externalId)->toBe('501#9587')
            ->and($sunBookings[0]->groupId)->toBe('multipass-501')
            ->and($sunBookings[0]->price)->toBe(900.0)
            ->and($sunBookings[0]->checkIn)->toBe('2024-04-02')
            ->and($sunBookings[0]->metadata)->not->toHaveKey('paid');
    });

    it('skips dateless prestations', function () {
        $connector = makeMultipassConnector([multipassRow([
            'check_in' => null,
            'check_out' => null,
            'units' => [
                ['detail_id' => 7001, 'status' => 'publish', 'unit' => 'Sun', 'resource_id' => 9587, 'check_in' => null, 'check_out' => null, 'subtotal' => 0.0],
            ],
        ])]);

        expect($connector->fetchBookings($this->unit, ['type' => 'multipass', 'multipass_unit_id' => '9587']))
            ->toBeEmpty();
    });

    it('links to the prestation edit page in the WordPress admin', function () {
        $booking = Booking::create([
            'property_id' => $this->property->id,
            'unit_id' => $this->unit->id,
            'guest_name' => 'Gudule Lapointe',
            'status' => 'confirmed',
            'check_in' => '2024-04-01',
            'check_out' => '2024-04-08',
        ]);
        $source = $booking->sources()->create([
            'source_type' => 'multipass',
            'source_key' => 'multipass:gites-mosaiques.com',
            'external_id' => '501#9587',
            'is_origin' => false,
        ]);

        expect(makeMultipassConnector([])->externalBookingUrl($source))
            ->toBe('https://gites-mosaiques.com/wp-admin/post.php?post=501&action=edit');
    });
});
