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

it('returns booking details with price, paid, balance and panel links', function () {
    $booking = Booking::create([
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'guest_name' => 'Jean Dupont',
        'status' => 'confirmed',
        'check_in' => '2026-09-01',
        'check_out' => '2026-09-08',
        'price' => 1400,
        'adults' => 2,
        'metadata' => ['invoice_payment_total' => 500],
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
        ->assertJsonPath('paid', 500)
        ->assertJsonPath('balance', 900)
        ->assertJsonPath('group', null)
        ->assertJsonPath('source.label', 'Beds24 API')
        ->assertJsonPath('source.url', 'https://beds24.com/control2.php?ajax=bookedit&id=66036992');

    expect($response->json('view_url'))->toContain('/admin/bookings/'.$booking->id);
    expect($response->json('edit_url'))->toContain('/admin/bookings/'.$booking->id);
});

it('aggregates group reservations and lists members', function () {
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
        ->assertJsonPath('group.members.0.unit_name', 'Test Unit')
        ->assertJsonPath('group.members.1.unit_name', 'Second Unit')
        ->assertJsonPath('group.members.1.is_current', true);
});

it('hides amounts and labels the status for cancelled bookings', function () {
    $booking = Booking::create([
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'guest_name' => 'Ratel Philippe',
        'status' => 'cancelled',
        'check_in' => '2026-06-27',
        'check_out' => '2026-07-04',
        'price' => 1200,
        'metadata' => ['invoice_payment_total' => 300],
    ]);

    $this->getJson("/booking/{$booking->id}")
        ->assertSuccessful()
        ->assertJsonPath('status', 'cancelled')
        ->assertJsonPath('status_label', __('booking.status.cancelled'))
        ->assertJsonPath('price', null)
        ->assertJsonPath('paid', null)
        ->assertJsonPath('balance', null);
});

it('hides cancelled bookings from the calendar by default and shows them on demand', function () {
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

it('returns null paid and balance when no payment info exists', function () {
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
