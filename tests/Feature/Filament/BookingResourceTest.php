<?php

use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Bookings\Pages\CreateBooking;
use App\Filament\Resources\Bookings\Pages\EditBooking;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Filament\Resources\Bookings\Pages\ViewBooking;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

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
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    BookingResource::skipAuthorization();
});

afterEach(function () {
    BookingResource::skipAuthorization(false);
});

it('redirects guests from the bookings list', function () {
    auth()->logout();
    $this->get('/admin/bookings')->assertRedirect('/admin/login');
});

it('renders the bookings list', function () {
    Livewire::test(ListBookings::class)->assertSuccessful();
});

it('renders the create page', function () {
    Livewire::test(CreateBooking::class)->assertSuccessful();
});

it('can see bookings in the list', function () {
    $booking = Booking::create([
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'guest_name' => 'Jean Dupont',
        'status' => 'confirmed',
        'check_in' => '2026-04-01',
        'check_out' => '2026-04-07',
        'is_manual' => true,
    ]);

    Livewire::test(ListBookings::class)
        ->assertCanSeeTableRecords([$booking]);
});

it('can create a booking', function () {
    Livewire::test(CreateBooking::class)
        ->fillForm([
            'property_id' => $this->property->id,
            'unit_id' => $this->unit->id,
            'guest_name' => 'Marie Martin',
            'status' => 'confirmed',
            'check_in' => '2026-05-01',
            'check_out' => '2026-05-05',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Booking::where('guest_name', 'Marie Martin')->exists())->toBeTrue();
});

it('validates required fields on create', function () {
    Livewire::test(CreateBooking::class)
        ->fillForm([
            'guest_name' => null,
            'check_in' => null,
            'check_out' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['guest_name', 'check_in', 'check_out']);
});

it('renders the view page with the sources table', function () {
    $booking = Booking::create([
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'guest_name' => 'Gudule Lapointe',
        'status' => 'confirmed',
        'check_in' => '2026-09-01',
        'check_out' => '2026-09-08',
    ]);
    $booking->sources()->create([
        'source_type' => 'beds24',
        'source_key' => 'beds24',
        'external_id' => '66036992',
        'is_origin' => true,
    ]);
    $booking->sources()->create([
        'source_type' => 'ical',
        'source_key' => 'ical:beds24.com',
        'external_id' => 'uid-x@beds24.com',
        'is_origin' => false,
    ]);

    Livewire::test(ViewBooking::class, ['record' => $booking->id])
        ->assertSuccessful()
        ->assertSee('✓ Beds24 API')
        ->assertSee('iCal beds24.com')
        ->assertSee('66036992');
});

it('can edit a booking', function () {
    $booking = Booking::create([
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'guest_name' => 'Pierre Durand',
        'status' => 'confirmed',
        'check_in' => '2026-06-01',
        'check_out' => '2026-06-08',
        'is_manual' => true,
    ]);

    Livewire::test(EditBooking::class, ['record' => $booking->id])
        ->fillForm(['guest_name' => 'Pierre Durand Modifié'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($booking->fresh()->guest_name)->toBe('Pierre Durand Modifié');
});
