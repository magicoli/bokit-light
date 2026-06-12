<?php

use App\Models\Property;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hbook\Services\HbookConnector;

uses(RefreshDatabase::class);

/**
 * Connector with canned WP endpoint rows instead of HTTP.
 */
function makeHbookConnector(array $rows): HbookConnector
{
    return new class($rows) extends HbookConnector
    {
        public function __construct(private array $rows) {}

        protected function fetchRows(Property $property): array
        {
            return $this->rows;
        }
    };
}

/**
 * A raw row as returned by /wp-json/bokit/v1/bookings/hbook.
 */
function hbookRow(array $overrides = []): array
{
    return array_merge([
        'hbook_uid' => 'D2026-01-01T10:00:00U1@https://example.com',
        'is_blocked' => false,
        'id' => 42,
        'check_in' => '2027-04-01',
        'check_out' => '2027-04-08',
        'unit_id' => '3539_1',
        'unit' => 'Sun',
        'adults' => 2,
        'children' => 1,
        'price' => 980.0,
        'deposit' => 294.0,
        'paid' => 294.0,
        'status' => 'confirmed',
        'guest_name' => 'Gudule Lapointe',
        'guest_email' => 'gudule@example.com',
        'guest_phone' => '+590690000000',
    ], $overrides);
}

describe('HbookConnector', function () {
    beforeEach(function () {
        $this->property = Property::create([
            'name' => 'Mosaiques',
            'slug' => 'mosaiques',
            'is_active' => true,
            'options' => ['wp_url' => 'https://gites-mosaiques.com', 'wp_user' => 'x', 'wp_app_password' => 'y'],
        ]);
        $this->unit = Unit::create(['property_id' => $this->property->id, 'name' => 'Sun', 'slug' => 'sun', 'is_active' => true]);
        $this->unit->setRelation('property', $this->property);
    });

    it('normalizes a solo booking with amounts and origin claim', function () {
        $connector = makeHbookConnector([hbookRow()]);

        $bookings = $connector->fetchBookings($this->unit, ['type' => 'hbook', 'hbook_unit_id' => '3539_1']);

        expect($bookings)->toHaveCount(1);

        $booking = $bookings[0];
        expect($booking->externalId)->toBe('D2026-01-01T10:00:00U1@https://example.com')
            ->and($booking->guestName)->toBe('Gudule Lapointe')
            ->and($booking->status)->toBe('confirmed')
            ->and($booking->price)->toBe(980.0)
            ->and($booking->adults)->toBe(2)
            ->and($booking->children)->toBe(1)
            ->and($booking->email)->toBe('gudule@example.com')
            ->and($booking->channel)->toBe('hbook')
            ->and($booking->claimsOrigin)->toBeTrue()
            ->and($booking->groupId)->toBeNull()
            ->and($booking->legacyUid)->toBe('hbook:D2026-01-01T10:00:00U1@https://example.com')
            ->and($booking->metadata['paid'])->toBe(294.0)
            ->and($booking->metadata['deposit'])->toBe(294.0);
    });

    it('ignores self-blocks of a solo booking', function () {
        $connector = makeHbookConnector([
            hbookRow(),
            hbookRow(['is_blocked' => true]),
        ]);

        $bookings = $connector->fetchBookings($this->unit, ['type' => 'hbook', 'hbook_unit_id' => '3539_1']);

        expect($bookings)->toHaveCount(1)
            ->and($bookings[0]->groupId)->toBeNull();
    });

    it('maps pending bookings to options', function () {
        $connector = makeHbookConnector([hbookRow(['status' => 'pending'])]);

        $bookings = $connector->fetchBookings($this->unit, ['type' => 'hbook', 'hbook_unit_id' => '3539_1']);

        expect($bookings[0]->status)->toBe('option');
    });

    it('skips bookings of other accommodations', function () {
        $connector = makeHbookConnector([hbookRow(['unit_id' => '3539_2'])]);

        expect($connector->fetchBookings($this->unit, ['type' => 'hbook', 'hbook_unit_id' => '3539_1']))
            ->toBeEmpty();
    });

    it('lets the lowest-accommodation member carry the group amounts', function () {
        // "Site entier" package on an untracked accommodation, blocking two units.
        $rows = [
            hbookRow(['unit_id' => '3573_9', 'unit' => 'Site entier', 'price' => 5000.0, 'paid' => 1500.0, 'adults' => 12, 'children' => 3]),
            hbookRow(['is_blocked' => true, 'unit_id' => '3539_1', 'unit' => 'Sun', 'check_in' => '2027-04-02']),
            hbookRow(['is_blocked' => true, 'unit_id' => '3539_2', 'unit' => 'Moon']),
        ];

        // Unit Sun (3539_1) — lowest accommodation: carries the amounts.
        $sun = makeHbookConnector($rows)->fetchBookings($this->unit, ['type' => 'hbook', 'hbook_unit_id' => '3539_1']);
        expect($sun)->toHaveCount(1)
            ->and($sun[0]->externalId)->toBe('D2026-01-01T10:00:00U1@https://example.com#3539_1')
            ->and($sun[0]->groupId)->toBe('hbook-42')
            ->and($sun[0]->price)->toBe(5000.0)
            ->and($sun[0]->adults)->toBe(12)
            ->and($sun[0]->checkIn)->toBe('2027-04-02')
            ->and($sun[0]->metadata['paid'])->toBe(1500.0)
            ->and($sun[0]->metadata['group_total'])->toBe(5000.0)
            ->and($sun[0]->guestName)->toBe('Gudule Lapointe');

        // Unit Moon (3539_2) — member without amounts, same group.
        $moon = Unit::create(['property_id' => $this->property->id, 'name' => 'Moon', 'slug' => 'moon', 'is_active' => true]);
        $moon->setRelation('property', $this->property);
        $moonBookings = makeHbookConnector($rows)->fetchBookings($moon, ['type' => 'hbook', 'hbook_unit_id' => '3539_2']);
        expect($moonBookings)->toHaveCount(1)
            ->and($moonBookings[0]->externalId)->toBe('D2026-01-01T10:00:00U1@https://example.com#3539_2')
            ->and($moonBookings[0]->groupId)->toBe('hbook-42')
            ->and($moonBookings[0]->price)->toBeNull()
            ->and($moonBookings[0]->adults)->toBeNull()
            ->and($moonBookings[0]->metadata['group_total'])->toBe(5000.0)
            ->and($moonBookings[0]->guestName)->toBe('Gudule Lapointe');
    });

    it('skips orphaned blocked rows without their parent booking', function () {
        $connector = makeHbookConnector([hbookRow(['is_blocked' => true])]);

        expect($connector->fetchBookings($this->unit, ['type' => 'hbook', 'hbook_unit_id' => '3539_1']))
            ->toBeEmpty();
    });

    it('builds the source key from the WordPress host', function () {
        $connector = makeHbookConnector([]);

        expect($connector->sourceKey($this->unit, []))->toBe('hbook:gites-mosaiques.com');
    });
});
