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
use Illuminate\Support\Number;
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

it('shows a single row per group reservation with aggregated totals', function () {
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
    ]);
    $member = Booking::create([
        'property_id' => $this->property->id,
        'unit_id' => $unit2->id,
        'guest_name' => 'Groupe Kervella',
        'status' => 'confirmed',
        'check_in' => '2026-10-02',
        'check_out' => '2026-10-09',
        'adults' => 5,
        'group_id' => 555,
    ]);
    $single = Booking::create([
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'guest_name' => 'Solo Guest',
        'status' => 'confirmed',
        'check_in' => '2026-10-03',
        'check_out' => '2026-10-05',
        'is_manual' => true,
    ]);

    Livewire::test(ListBookings::class)
        ->assertCanSeeTableRecords([$master, $single])
        ->assertCanNotSeeTableRecords([$member])
        // Aggregates: master unit + member count, summed adults
        ->assertSee('Test Unit + 1')
        ->assertSee('15');
});

it('hides cancelled bookings by default and shows them via filters', function () {
    $confirmed = Booking::create([
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'guest_name' => 'Effective Guest',
        'status' => 'confirmed',
        'check_in' => '2026-07-01',
        'check_out' => '2026-07-08',
    ]);
    $cancelled = Booking::create([
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'guest_name' => 'Cancelled Guest',
        'status' => 'cancelled',
        'check_in' => '2026-07-10',
        'check_out' => '2026-07-15',
    ]);
    $vanished = Booking::create([
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'guest_name' => 'Vanished Guest',
        'status' => 'vanished',
        'check_in' => '2026-07-20',
        'check_out' => '2026-07-25',
    ]);

    // Default: only effective bookings
    Livewire::test(ListBookings::class)
        ->assertCanSeeTableRecords([$confirmed])
        ->assertCanNotSeeTableRecords([$cancelled, $vanished]);

    // Untoggling the effective filter reveals everything
    Livewire::test(ListBookings::class)
        ->removeTableFilter('effective')
        ->assertCanSeeTableRecords([$confirmed, $cancelled, $vanished]);

    // Picking a cancelled status explicitly works even with the toggle on
    Livewire::test(ListBookings::class)
        ->filterTable('status', 'cancelled')
        ->assertCanSeeTableRecords([$cancelled])
        ->assertCanNotSeeTableRecords([$confirmed, $vanished]);
});

it('hides past and ongoing availability blocks from the list', function () {
    $past = Booking::create([
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'guest_name' => 'Unavailable',
        'status' => 'blocked',
        'check_in' => now()->subDays(10)->format('Y-m-d'),
        'check_out' => now()->addDays(5)->format('Y-m-d'),
    ]);
    $future = Booking::create([
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'guest_name' => 'Unavailable',
        'status' => 'blocked',
        'check_in' => now()->addDays(30)->format('Y-m-d'),
        'check_out' => now()->addDays(40)->format('Y-m-d'),
    ]);

    Livewire::test(ListBookings::class)
        ->assertCanSeeTableRecords([$future])
        ->assertCanNotSeeTableRecords([$past]);
});

it('renders the group detail table on the view page', function () {
    $unit2 = Unit::create([
        'property_id' => $this->property->id,
        'name' => 'Second Unit',
        'slug' => 'second-unit-2',
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
        'group_id' => 556,
        'metadata' => ['group_total' => 3000],
    ]);
    Booking::create([
        'property_id' => $this->property->id,
        'unit_id' => $unit2->id,
        'guest_name' => 'Groupe Kervella',
        'status' => 'confirmed',
        'check_in' => '2026-10-01',
        'check_out' => '2026-10-08',
        'group_id' => 556,
    ]);

    Livewire::test(ViewBooking::class, ['record' => $master->id])
        ->assertSuccessful()
        ->assertSee(__('booking.section.group'))
        ->assertSee('Second Unit')
        ->assertSee(Number::currency(3000, 'EUR', app()->getLocale()));
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
        ->assertSee('66036992')
        ->assertSee('https://beds24.com/control2.php?ajax=bookedit&amp;id=66036992', escape: false);
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
