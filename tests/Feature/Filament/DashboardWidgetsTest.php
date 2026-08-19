<?php

use App\Filament\Widgets\BookingsOngoing;
use App\Filament\Widgets\BookingsOptions;
use App\Filament\Widgets\BookingsQuotes;
use App\Filament\Widgets\BookingsUpcoming;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

describe('Dashboard widgets', function () {

    uses(RefreshDatabase::class);

    beforeEach(function () {
        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => 'password',
            'is_admin' => true,
        ]);

        $this->property = Property::create(['name' => 'P', 'slug' => 'p', 'is_active' => true]);
        $this->unit = Unit::create(['property_id' => $this->property->id, 'name' => 'Zetoil', 'slug' => 'zetoil', 'is_active' => true]);

        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->makeBooking = fn (array $attributes): Booking => Booking::create(array_merge([
            'property_id' => $this->property->id,
            'unit_id' => $this->unit->id,
            'guest_name' => 'Guest',
            'status' => 'confirmed',
        ], $attributes));
    });

    test('renders the dashboard with the booking widgets', function () {
        // Widgets are lazy Livewire components: the initial HTML only carries
        // their mount tags, the headings come with the deferred render.
        $this->get('/admin')
            ->assertSuccessful()
            ->assertSeeLivewire(BookingsOngoing::class)
            ->assertSeeLivewire(BookingsUpcoming::class)
            ->assertSeeLivewire(BookingsOptions::class)
            ->assertSeeLivewire(BookingsQuotes::class);
    });

    test('lists ongoing stays ordered by departure', function () {
        $leavingSoon = ($this->makeBooking)([
            'guest_name' => 'Leaving Soon',
            'check_in' => now()->subDays(5)->format('Y-m-d'),
            'check_out' => now()->addDays(2)->format('Y-m-d'),
        ]);
        $leavingLater = ($this->makeBooking)([
            'guest_name' => 'Leaving Later',
            'check_in' => now()->subDays(2)->format('Y-m-d'),
            'check_out' => now()->addDays(9)->format('Y-m-d'),
        ]);
        $upcoming = ($this->makeBooking)([
            'guest_name' => 'Not Here Yet',
            'check_in' => now()->addDays(5)->format('Y-m-d'),
            'check_out' => now()->addDays(10)->format('Y-m-d'),
        ]);
        $past = ($this->makeBooking)([
            'guest_name' => 'Long Gone',
            'check_in' => now()->subDays(20)->format('Y-m-d'),
            'check_out' => now()->subDays(10)->format('Y-m-d'),
        ]);

        Livewire::test(BookingsOngoing::class)
            ->assertCanSeeTableRecords([$leavingSoon, $leavingLater], inOrder: true)
            ->assertCanNotSeeTableRecords([$upcoming, $past]);
    });

    test('shows paid and total amounts on the stays widgets', function () {
        ($this->makeBooking)([
            'guest_name' => 'Paying Guest',
            'check_in' => now()->subDays(2)->format('Y-m-d'),
            'check_out' => now()->addDays(3)->format('Y-m-d'),
            'price' => 1400,
            'metadata' => ['invoice_payment_total' => 450],
        ]);

        Livewire::test(BookingsOngoing::class)
            ->assertSee('450')
            ->assertSee('400');
    });

    test('lists upcoming stays ordered by arrival', function () {
        $arrivingLater = ($this->makeBooking)([
            'guest_name' => 'Arriving Later',
            'check_in' => now()->addDays(20)->format('Y-m-d'),
            'check_out' => now()->addDays(25)->format('Y-m-d'),
        ]);
        $arrivingSoon = ($this->makeBooking)([
            'guest_name' => 'Arriving Soon',
            'check_in' => now()->addDays(3)->format('Y-m-d'),
            'check_out' => now()->addDays(8)->format('Y-m-d'),
        ]);
        $ongoing = ($this->makeBooking)([
            'guest_name' => 'Already Here',
            'check_in' => now()->subDays(2)->format('Y-m-d'),
            'check_out' => now()->addDays(2)->format('Y-m-d'),
        ]);

        Livewire::test(BookingsUpcoming::class)
            ->assertCanSeeTableRecords([$arrivingSoon, $arrivingLater], inOrder: true)
            ->assertCanNotSeeTableRecords([$ongoing]);
    });

    test('lists pending options by modification date, newest first', function () {
        $old = ($this->makeBooking)([
            'guest_name' => 'Old Option',
            'status' => 'option',
            'check_in' => now()->addDays(30)->format('Y-m-d'),
            'check_out' => now()->addDays(35)->format('Y-m-d'),
        ]);
        $old->timestamps = false;
        $old->forceFill(['updated_at' => now()->subDays(10)])->save();

        $fresh = ($this->makeBooking)([
            'guest_name' => 'Fresh Option',
            'status' => 'option',
            'check_in' => now()->addDays(40)->format('Y-m-d'),
            'check_out' => now()->addDays(45)->format('Y-m-d'),
        ]);
        $expired = ($this->makeBooking)([
            'guest_name' => 'Expired Option',
            'status' => 'option',
            'check_in' => now()->subDays(20)->format('Y-m-d'),
            'check_out' => now()->subDays(15)->format('Y-m-d'),
        ]);
        $confirmed = ($this->makeBooking)([
            'guest_name' => 'Confirmed Guest',
            'check_in' => now()->addDays(30)->format('Y-m-d'),
            'check_out' => now()->addDays(35)->format('Y-m-d'),
        ]);

        Livewire::test(BookingsOptions::class)
            ->assertCanSeeTableRecords([$fresh, $old], inOrder: true)
            ->assertCanNotSeeTableRecords([$expired, $confirmed]);
    });

    test('lists pending quotes by modification date', function () {
        $quote = ($this->makeBooking)([
            'guest_name' => 'Quote Guest',
            'status' => 'quote',
            'check_in' => now()->addDays(30)->format('Y-m-d'),
            'check_out' => now()->addDays(35)->format('Y-m-d'),
        ]);
        $option = ($this->makeBooking)([
            'guest_name' => 'Option Guest',
            'status' => 'option',
            'check_in' => now()->addDays(30)->format('Y-m-d'),
            'check_out' => now()->addDays(35)->format('Y-m-d'),
        ]);

        Livewire::test(BookingsQuotes::class)
            ->assertCanSeeTableRecords([$quote])
            ->assertCanNotSeeTableRecords([$option]);
    });

    test('links each widget to the bookings list with its filter and sort', function () {
        $component = Livewire::test(BookingsOptions::class);

        $component->assertSee(__('booking.widget.see_all'));
        $component->assertSee('filters%5Bstatus%5D%5Bvalue%5D=option', escape: false);
        $component->assertSee('filters%5Bperiod%5D%5Bvalue%5D=current', escape: false);
        $component->assertSee('sort=updated_at%3Adesc', escape: false);
    });

    test('shows one row per group reservation', function () {
        $master = ($this->makeBooking)([
            'guest_name' => 'Groupe Kervella',
            'check_in' => now()->subDays(1)->format('Y-m-d'),
            'check_out' => now()->addDays(5)->format('Y-m-d'),
            'group_id' => 777,
        ]);
        $unit2 = Unit::create(['property_id' => $this->property->id, 'name' => 'Moon', 'slug' => 'moon', 'is_active' => true]);
        $member = ($this->makeBooking)([
            'guest_name' => 'Groupe Kervella',
            'unit_id' => $unit2->id,
            'check_in' => now()->subDays(1)->format('Y-m-d'),
            'check_out' => now()->addDays(5)->format('Y-m-d'),
            'group_id' => 777,
        ]);

        Livewire::test(BookingsOngoing::class)
            ->assertCanSeeTableRecords([$master])
            ->assertCanNotSeeTableRecords([$member]);
    });
});
