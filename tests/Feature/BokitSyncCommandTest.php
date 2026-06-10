<?php

use App\Contracts\SourceConnector;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Unit;
use App\Services\SyncRegistry;
use App\Support\NormalizedBooking;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeCommandConnector(string $type, array $bookings, ?array &$calledUnits = null): SourceConnector
{
    return new class($type, $bookings, $calledUnits) implements SourceConnector
    {
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
    };
}

describe('bokit:sync', function () {
    beforeEach(function () {
        // Isolate each test with a fresh empty registry.
        app()->instance(SyncRegistry::class, new SyncRegistry);
    });

    it('warns when no handlers are registered', function () {
        $this->artisan('bokit:sync')
            ->expectsOutput('No sync handlers registered.')
            ->assertExitCode(0);
    });

    it('syncs each matching unit source through the engine', function () {
        $called = [];
        app(SyncRegistry::class)->register(makeCommandConnector('test-source', [
            new NormalizedBooking(
                externalId: 'x-1',
                checkIn: '2027-01-08',
                checkOut: '2027-01-11',
                guestName: 'Gudule Lapointe',
                status: 'confirmed',
            ),
        ], $called));

        $property = Property::create(['name' => 'BokitP1', 'slug' => 'bokit-p1', 'is_active' => true]);
        Unit::create([
            'property_id' => $property->id,
            'name' => 'TestUnit',
            'slug' => 'bokit-test-unit',
            'is_active' => true,
            'options' => ['sources' => [['type' => 'test-source', 'enabled' => true]]],
        ]);

        $this->artisan('bokit:sync')->assertExitCode(0);

        expect($called)->toBe(['TestUnit'])
            ->and(Booking::count())->toBe(1)
            ->and(Booking::first()->guest_name)->toBe('Gudule Lapointe');
    });

    it('does not persist anything in dry-run mode', function () {
        app(SyncRegistry::class)->register(makeCommandConnector('dry-test', [
            new NormalizedBooking(
                externalId: 'x-1',
                checkIn: '2027-01-08',
                checkOut: '2027-01-11',
                guestName: 'Gudule Lapointe',
                status: 'confirmed',
            ),
        ]));

        $property = Property::create(['name' => 'BokitP2', 'slug' => 'bokit-p2', 'is_active' => true]);
        Unit::create([
            'property_id' => $property->id,
            'name' => 'U2',
            'slug' => 'bokit-u2',
            'is_active' => true,
            'options' => ['sources' => [['type' => 'dry-test', 'enabled' => true]]],
        ]);

        $this->artisan('bokit:sync', ['--dry-run' => true])->assertExitCode(0);

        expect(Booking::count())->toBe(0);
    });

    it('skips unit sources whose type has no registered handler', function () {
        $property = Property::create(['name' => 'BokitP3', 'slug' => 'bokit-p3', 'is_active' => true]);
        Unit::create([
            'property_id' => $property->id,
            'name' => 'U3',
            'slug' => 'bokit-u3',
            'is_active' => true,
            'options' => ['sources' => [['type' => 'unregistered', 'enabled' => true]]],
        ]);

        // No connector registered — should complete without error
        $this->artisan('bokit:sync')
            ->assertExitCode(0);
    });
});
