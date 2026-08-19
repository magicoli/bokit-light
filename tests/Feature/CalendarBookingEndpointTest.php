<?php

use App\Models\Booking;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::create([
        'name' => 'Admin',
        'email' => 'admin@test.local',
        'password' => bcrypt('password'),
        'is_admin' => true,
    ]);

    $this->property = Property::create([
        'name' => 'Test Property',
        'slug' => 'test-property',
        'is_active' => true,
    ]);

    $this->unit = Unit::create([
        'property_id' => $this->property->id,
        'name' => 'Test Unit',
        'slug' => 'test-unit',
        'is_active' => true,
    ]);

    $this->actingAs($this->admin);
});

describe('calendar booking endpoint', function () {
    test('returns booking details with price, paid, balance and panel links', function () {
        $booking = Booking::create([
            'property_id' => $this->property->id,
            'unit_id' => $this->unit->id,
            'guest_name' => 'Jean Dupont',
            'status' => 'confirmed',
            'check_in' => '2026-09-01',
            'check_out' => '2026-09-08',
            'price' => 1400,
            'adults' => 2,
            'metadata' => ['invoice_payment_total' => 500, 'deposit' => 420],
        ]);
        $booking->sources()->create([
            'source_type' => 'beds24',
            'source_key' => 'beds24',
            'external_id' => '66036992',
            'is_origin' => true,
        ]);

        $response = $this->getJson("/booking/{$booking->id}")->assertSuccessful();

        $response
            ->assertJsonPath('guest_name', 'Jean Dupont')
            ->assertJsonPath('price', 1400)
            ->assertJsonPath('deposit', 420)
            ->assertJsonPath('paid', 500)
            ->assertJsonPath('balance', 900)
            ->assertJsonPath('group', null)
            ->assertJsonPath('source.label', 'Beds24 API')
            ->assertJsonPath('source.url', 'https://beds24.com/control2.php?ajax=bookedit&id=66036992');

        expect($response->json('view_url'))->toContain('/admin/bookings/'.$booking->id);
        expect($response->json('edit_url'))->toContain('/admin/bookings/'.$booking->id);
    });

    test('aggregates group reservations and lists members', function () {
        $unit2 = Unit::create([
            'property_id' => $this->property->id,
            'name' => 'Second Unit',
            'slug' => 'second-unit',
            'is_active' => true,
        ]);

        $master = Booking::create([
            'property_id' => $this->property->id,
            'unit_id' => $this->unit->id,
            'guest_name' => 'Groupe Kervella',
            'status' => 'confirmed',
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-08',
            'price' => 3000,
            'adults' => 10,
            'group_id' => 555,
            'metadata' => ['invoice_payment_total' => 1000],
        ]);
        $member = Booking::create([
            'property_id' => $this->property->id,
            'unit_id' => $unit2->id,
            'guest_name' => 'Groupe Kervella',
            'status' => 'confirmed',
            'check_in' => '2026-10-02',
            'check_out' => '2026-10-09',
            'price' => 1200,
            'adults' => 5,
            'group_id' => 555,
        ]);

        // Cancelled member: listed in the detail, excluded from aggregates.
        Booking::create([
            'property_id' => $this->property->id,
            'unit_id' => $this->unit->id,
            'guest_name' => 'Groupe Kervella',
            'status' => 'cancelled',
            'check_in' => '2026-09-20',
            'check_out' => '2026-10-20',
            'price' => 5145,
            'adults' => 50,
            'group_id' => 555,
            'metadata' => ['invoice_payment_total' => 999],
        ]);

        // Clicking any member returns the same group aggregates
        $this->getJson("/booking/{$member->id}")
            ->assertSuccessful()
            ->assertJsonPath('check_in', '2026-10-01')
            ->assertJsonPath('check_out', '2026-10-09')
            ->assertJsonPath('price', 4200)
            ->assertJsonPath('paid', 1000)
            ->assertJsonPath('balance', 3200)
            ->assertJsonPath('adults', 15)
            ->assertJsonPath('group.count', 2)
            ->assertJsonPath('group.members.0.is_cancelled', true)
            ->assertJsonPath('group.members.0.price', null)
            ->assertJsonPath('group.members.2.unit_name', 'Second Unit')
            ->assertJsonPath('group.members.2.is_current', true);
    });

    test('hides amounts and labels the status for cancelled bookings', function () {
        $booking = Booking::create([
            'property_id' => $this->property->id,
            'unit_id' => $this->unit->id,
            'guest_name' => 'Ratel Philippe',
            'status' => 'cancelled',
            'check_in' => '2026-06-27',
            'check_out' => '2026-07-04',
            'price' => 1200,
            'metadata' => ['invoice_payment_total' => 300, 'deposit' => 100],
        ]);

        $this->getJson("/booking/{$booking->id}")
            ->assertSuccessful()
            ->assertJsonPath('status', 'cancelled')
            ->assertJsonPath('status_label', __('booking.status.cancelled'))
            ->assertJsonPath('price', null)
            ->assertJsonPath('deposit', null)
            ->assertJsonPath('paid', null)
            ->assertJsonPath('balance', null);
    });

    test('hides cancelled bookings from the calendar by default and shows them on demand', function () {
        $date = now()->startOfMonth()->addDays(10);

        Booking::create([
            'property_id' => $this->property->id,
            'unit_id' => $this->unit->id,
            'guest_name' => 'Annulée Visible',
            'status' => 'cancelled',
            'check_in' => $date->format('Y-m-d'),
            'check_out' => $date->copy()->addDays(3)->format('Y-m-d'),
        ]);
        Booking::create([
            'property_id' => $this->property->id,
            'unit_id' => $this->unit->id,
            'guest_name' => 'Confirmée Visible',
            'status' => 'confirmed',
            'check_in' => $date->format('Y-m-d'),
            'check_out' => $date->copy()->addDays(3)->format('Y-m-d'),
        ]);

        $this->get('/calendar?date='.$date->format('Y-m-d'))
            ->assertSuccessful()
            ->assertSee('Confirmée Visible')
            ->assertDontSee('Annulée Visible');

        $this->get('/calendar?date='.$date->format('Y-m-d').'&cancelled=1')
            ->assertSuccessful()
            ->assertSee('Annulée Visible');
    });

    test('skips the property header row when a single property is displayed', function () {
        Unit::create([
            'property_id' => $this->property->id,
            'name' => 'Second Unit',
            'slug' => 'second-unit',
            'is_active' => true,
        ]);

        // One active property, several units: no property grouping row.
        $this->get('/calendar')
            ->assertSuccessful()
            ->assertDontSee('property-name');

        // A second active property brings the grouping back.
        $other = Property::create(['name' => 'Other Property', 'slug' => 'other-property', 'is_active' => true]);
        Unit::create(['property_id' => $other->id, 'name' => 'Other Unit A', 'slug' => 'other-unit-a', 'is_active' => true]);
        Unit::create(['property_id' => $other->id, 'name' => 'Other Unit B', 'slug' => 'other-unit-b', 'is_active' => true]);

        $this->get('/calendar')
            ->assertSuccessful()
            ->assertSee('property-name');
    });

    test('hides inactive properties and units from the calendar', function () {
        $inactive = Property::create(['name' => 'Dormant Property', 'slug' => 'dormant', 'is_active' => false]);
        Unit::create(['property_id' => $inactive->id, 'name' => 'Dormant Unit', 'slug' => 'dormant-unit', 'is_active' => true]);
        Unit::create(['property_id' => $this->property->id, 'name' => 'Retired Unit', 'slug' => 'retired-unit', 'is_active' => false]);

        $this->get('/calendar')
            ->assertSuccessful()
            ->assertDontSee('Dormant Property')
            ->assertDontSee('Dormant Unit')
            ->assertDontSee('Retired Unit');
    });

    test('exposes the real origin channel with its direct OTA link', function () {
        $booking = Booking::create([
            'property_id' => $this->property->id,
            'unit_id' => $this->unit->id,
            'guest_name' => 'Airbnb Guest',
            'status' => 'confirmed',
            'check_in' => '2026-09-01',
            'check_out' => '2026-09-08',
            'source_name' => 'airbnb',
            'metadata' => ['api_ref' => 'HMZQ9BFEPN'],
        ]);

        $this->getJson("/booking/{$booking->id}")
            ->assertSuccessful()
            ->assertJsonPath('origin.channel', 'airbnb')
            ->assertJsonPath('origin.slug', 'airbnb')
            ->assertJsonPath('origin.url', 'https://www.airbnb.com/hosting/reservations/details/HMZQ9BFEPN');
    });

    test('has no origin link for a direct beds24 booking', function () {
        $booking = Booking::create([
            'property_id' => $this->property->id,
            'unit_id' => $this->unit->id,
            'guest_name' => 'Direct Guest',
            'status' => 'confirmed',
            'check_in' => '2026-09-01',
            'check_out' => '2026-09-08',
            'source_name' => 'beds24',
            'metadata' => ['api_ref' => '123'],
        ]);

        $this->getJson("/booking/{$booking->id}")
            ->assertSuccessful()
            ->assertJsonPath('origin.slug', 'beds24')
            ->assertJsonPath('origin.url', null);
    });

    test('resyncs a booking unit on demand', function () {
        $booking = Booking::create([
            'property_id' => $this->property->id,
            'unit_id' => $this->unit->id,
            'guest_name' => 'Resync Guest',
            'status' => 'confirmed',
            'check_in' => '2026-09-01',
            'check_out' => '2026-09-08',
        ]);

        // Unit has no sources here, so the endpoint is a safe no-op that
        // confirms routing, access control and the JSON contract.
        $this->postJson("/booking/{$booking->id}/resync")
            ->assertSuccessful()
            ->assertJsonPath('ok', true);
    });

    test('returns null paid and balance when no payment info exists', function () {
        $booking = Booking::create([
            'property_id' => $this->property->id,
            'unit_id' => $this->unit->id,
            'guest_name' => 'Sans Paiement',
            'status' => 'confirmed',
            'check_in' => '2026-09-01',
            'check_out' => '2026-09-08',
            'is_manual' => true,
        ]);

        $this->getJson("/booking/{$booking->id}")
            ->assertSuccessful()
            ->assertJsonPath('price', null)
            ->assertJsonPath('paid', null)
            ->assertJsonPath('balance', null)
            ->assertJsonPath('source.url', null);
    });
});
