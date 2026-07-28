<?php

use App\Sync\Jobs\SyncBookingsJob;
use App\Models\Booking;
use App\Models\BookingSource;
use App\Models\IcalSource;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Support\NormalizedBooking;
use App\Sync\Contracts\SourceConnector;
use App\Sync\SyncRegistry;
use App\Sync\SyncRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeAutoSyncConnector(string $type, array $bookings, ?array &$calledUnits = null): SourceConnector
{
    return new class($type, $bookings, $calledUnits) implements SourceConnector {
        public function __construct(
            private string $type,
            private array $bookings,
            private ?array &$calledUnits,
        ) {}

        public function sourceType(): string
        {
            return $this->type;
        }

        public function label(): string
        {
            return ucfirst($this->type);
        }

        public function displayLabel(array $sourceConfig): string
        {
            return $this->label();
        }

        public function sourceKey(Unit $unit, array $sourceConfig): string
        {
            return $this->type;
        }

        public function fetchBookings(Unit $unit, array $sourceConfig): array
        {
            if ($this->calledUnits !== null) {
                $this->calledUnits[] = $unit->name;
            }

            return $this->bookings;
        }

        public function externalBookingUrl(BookingSource $source): ?string
        {
            return null;
        }
    };
}

beforeEach(function () {
    app()->instance(SyncRegistry::class, new SyncRegistry());

    $this->property = Property::create(['name' => 'Auto', 'slug' => 'auto', 'is_active' => true]);

    $this->makeUnit = fn(string $name, string $type): Unit => Unit::create([
        'property_id' => $this->property->id,
        'name' => $name,
        'slug' => str($name)->slug()->value(),
        'is_active' => true,
        'options' => ['sources' => [['type' => $type, 'enabled' => true]]],
    ]);

    $this->arriving = fn(string $externalId, string $guest): NormalizedBooking => new NormalizedBooking(
        externalId: $externalId,
        checkIn: '2027-03-04',
        checkOut: '2027-03-08',
        guestName: $guest,
        status: 'confirmed',
    );
});

it('syncs the units configured sources, with the legacy ical table empty', function () {
    // The regression this whole cleanup started from: the periodic job read `ical_sources`, which
    // holds nothing since the sources moved into unit.options.sources, and reported success.
    expect(IcalSource::count())->toBe(0);

    $called = [];
    app(SyncRegistry::class)->register(makeAutoSyncConnector(
        'auto-source',
        [
            ($this->arriving)('auto-1', 'Gudule Lapointe'),
        ],
        $called,
    ));

    ($this->makeUnit)('Le petit paradis', 'auto-source');

    (new SyncBookingsJob())->handle(app(SyncRunner::class));

    expect($called)->toBe(['Le petit paradis']);
    expect(Booking::where('guest_name', 'Gudule Lapointe')->exists())->toBeTrue();
});

it('walks every unit when nothing is targeted', function () {
    $called = [];
    app(SyncRegistry::class)->register(makeAutoSyncConnector('auto-source', [], $called));

    ($this->makeUnit)('Moon', 'auto-source');
    ($this->makeUnit)('Sun', 'auto-source');

    (new SyncBookingsJob())->handle(app(SyncRunner::class));

    expect($called)->toBe(['Moon', 'Sun']);
});

it('resyncs only the booking own unit from the calendar', function () {
    $called = [];
    app(SyncRegistry::class)->register(makeAutoSyncConnector(
        'auto-source',
        [
            ($this->arriving)('auto-2', 'Alphonse Allais'),
        ],
        $called,
    ));

    $moon = ($this->makeUnit)('Moon', 'auto-source');
    ($this->makeUnit)('Sun', 'auto-source');

    $booking = Booking::create([
        'property_id' => $this->property->id,
        'unit_id' => $moon->id,
        'guest_name' => 'On Moon',
        'status' => 'confirmed',
        'check_in' => '2027-03-04',
        'check_out' => '2027-03-08',
    ]);

    $admin = User::create([
        'name' => 'Admin',
        'email' => 'admin@auto.local',
        'password' => bcrypt('password'),
        'is_admin' => true,
    ]);

    $this->actingAs($admin)->post(route('booking.resync', $booking->id))->assertSuccessful();

    // Targeting kept exactly as it was: the booking's own unit, and no other.
    expect($called)->toBe(['Moon']);
});
