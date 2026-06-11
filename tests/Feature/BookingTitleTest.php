<?php

use App\Models\Booking;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->property = Property::create(['name' => 'P', 'slug' => 'p', 'is_active' => true]);
    $this->unit = Unit::create(['property_id' => $this->property->id, 'name' => 'Zetoil', 'slug' => 'zetoil', 'is_active' => true]);
});

it('builds the one-line title for a single booking', function () {
    $booking = Booking::create([
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'guest_name' => 'Gudule Lapointe',
        'status' => 'confirmed',
        'check_in' => '2026-07-08',
        'check_out' => '2026-07-11',
        'adults' => 3,
        'children' => 1,
    ]);

    expect($booking->title)->toBe('Gudule Lapointe, Zetoil, 4p from 08/07/2026 to 11/07/2026');
});

it('omits the guest count when unknown', function () {
    $booking = Booking::create([
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'guest_name' => 'Sans Compte',
        'status' => 'confirmed',
        'check_in' => '2026-07-08',
        'check_out' => '2026-07-11',
    ]);

    expect($booking->title)->toBe('Sans Compte, Zetoil from 08/07/2026 to 11/07/2026');
});

it('aggregates units, guests and the date span for group reservations', function () {
    $unit2 = Unit::create(['property_id' => $this->property->id, 'name' => 'Moon', 'slug' => 'moon', 'is_active' => true]);

    $master = Booking::create([
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'guest_name' => 'Groupe Kervella',
        'status' => 'confirmed',
        'check_in' => '2026-10-01',
        'check_out' => '2026-10-08',
        'adults' => 10,
        'group_id' => 555,
    ]);
    Booking::create([
        'property_id' => $this->property->id,
        'unit_id' => $unit2->id,
        'guest_name' => 'Groupe Kervella',
        'status' => 'confirmed',
        'check_in' => '2026-10-02',
        'check_out' => '2026-10-09',
        'adults' => 5,
        'group_id' => 555,
    ]);
    // Cancelled member: counts for nothing.
    Booking::create([
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'guest_name' => 'Groupe Kervella',
        'status' => 'cancelled',
        'check_in' => '2026-09-20',
        'check_out' => '2026-10-20',
        'adults' => 50,
        'group_id' => 555,
    ]);

    expect($master->title)->toBe('Groupe Kervella, 2 units, 15p from 01/10/2026 to 09/10/2026');
});

it('translates the title in french', function () {
    app()->setLocale('fr');

    $booking = Booking::create([
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'guest_name' => 'Gudule Lapointe',
        'status' => 'confirmed',
        'check_in' => '2026-07-08',
        'check_out' => '2026-07-11',
        'adults' => 2,
    ]);

    expect($booking->title)->toBe('Gudule Lapointe, Zetoil, 2p du 08/07/2026 au 11/07/2026');
});
