<?php

use App\Sync\Contracts\PushableConnector;
use App\Sync\Contracts\SourceConnector;
use App\Models\Booking;
use App\Models\BookingSource;
use App\Models\Property;
use App\Models\Unit;
use App\Sync\SyncEngine;
use App\Sync\SyncRegistry;
use App\Support\NormalizedBooking;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Pushable connector recording push calls and returning sequential ids.
 */
function makePushConnector(): SourceConnector&PushableConnector
{
    return new class implements PushableConnector, SourceConnector
    {
        public array $pushes = [];

        private int $nextId = 9000;

        public function sourceType(): string
        {
            return 'beds24';
        }

        public function label(): string
        {
            return 'Beds24 API';
        }

        public function displayLabel(array $sourceConfig): string
        {
            return 'Beds24 API';
        }

        public function sourceKey(Unit $unit, array $sourceConfig): string
        {
            return 'beds24';
        }

        public function fetchBookings(Unit $unit, array $sourceConfig): array
        {
            return [];
        }

        public function externalBookingUrl(BookingSource $source): ?string
        {
            return null;
        }

        public function pushBooking(Unit $unit, array $sourceConfig, Booking $booking, ?string $externalId): string
        {
            $this->pushes[] = ['booking_id' => $booking->id, 'external_id' => $externalId, 'status' => $booking->status];

            return $externalId ?? (string) $this->nextId++;
        }
    };
}

describe('SyncEngine push', function () {
    beforeEach(function () {
        $this->property = Property::create(['name' => 'PushP', 'slug' => 'push-p', 'is_active' => true]);
        $this->unit = Unit::create([
            'property_id' => $this->property->id,
            'name' => 'PushU',
            'slug' => 'push-u',
            'is_active' => true,
        ]);
        $this->unit->setRelation('property', $this->property);
        $this->engine = new SyncEngine;

        $this->makeManual = fn (array $attributes = []): Booking => Booking::create(array_merge([
            'property_id' => $this->property->id,
            'unit_id' => $this->unit->id,
            'guest_name' => 'Gudule Lapointe',
            'status' => 'confirmed',
            'check_in' => now()->addDays(30)->format('Y-m-d'),
            'check_out' => now()->addDays(37)->format('Y-m-d'),
            'is_manual' => true,
        ], $attributes));
    });

    test('pushes a manual booking and records the pair for echo suppression', function () {
        $booking = ($this->makeManual)();
        $connector = makePushConnector();

        $stats = $this->engine->pushBookings($this->unit, [], $connector);

        expect($stats['created'])->toBe(1)
            ->and($connector->pushes)->toHaveCount(1)
            ->and($connector->pushes[0]['external_id'])->toBeNull();

        $reference = $booking->sources()->first();
        expect($reference->source_key)->toBe('beds24')
            ->and($reference->external_id)->toBe('9000')
            ->and($reference->is_origin)->toBeFalse()
            ->and($reference->pushed_at)->not->toBeNull();

        // Second run: nothing changed, nothing pushed.
        $stats = $this->engine->pushBookings($this->unit, [], $connector);
        expect($stats['created'])->toBe(0)
            ->and($stats['updated'])->toBe(0)
            ->and($connector->pushes)->toHaveCount(1);
    });

    test('pushes an update when the booking changed since the last push', function () {
        $booking = ($this->makeManual)();
        $connector = makePushConnector();
        $this->engine->pushBookings($this->unit, [], $connector);

        // Backdate the push marker, then modify the booking.
        $booking->sources()->first()->update(['pushed_at' => now()->subHour()]);
        $booking->update(['guest_name' => 'Gudule Lapointe-Tremblay']);

        $stats = $this->engine->pushBookings($this->unit, [], $connector);

        expect($stats['updated'])->toBe(1)
            ->and($connector->pushes)->toHaveCount(2)
            ->and($connector->pushes[1]['external_id'])->toBe('9000');
    });

    test('propagates the cancellation of a pushed booking', function () {
        $booking = ($this->makeManual)();
        $connector = makePushConnector();
        $this->engine->pushBookings($this->unit, [], $connector);

        $booking->sources()->first()->update(['pushed_at' => now()->subHour()]);
        $booking->update(['status' => 'cancelled']);

        $stats = $this->engine->pushBookings($this->unit, [], $connector);

        expect($stats['updated'])->toBe(1)
            ->and($connector->pushes[1]['status'])->toBe('cancelled');
    });

    test('never pushes cancelled bookings that were never pushed', function () {
        ($this->makeManual)(['status' => 'cancelled']);
        $connector = makePushConnector();

        $stats = $this->engine->pushBookings($this->unit, [], $connector);

        expect($stats['created'])->toBe(0)
            ->and($connector->pushes)->toBeEmpty();
    });

    test('ignores non-manual and past bookings', function () {
        ($this->makeManual)(['is_manual' => false]);
        ($this->makeManual)([
            'check_in' => now()->subDays(20)->format('Y-m-d'),
            'check_out' => now()->subDays(13)->format('Y-m-d'),
        ]);
        $connector = makePushConnector();

        $stats = $this->engine->pushBookings($this->unit, [], $connector);

        expect($stats['created'])->toBe(0)
            ->and($connector->pushes)->toBeEmpty();
    });

    test('counts without pushing in dry-run mode', function () {
        ($this->makeManual)();
        $connector = makePushConnector();

        $stats = $this->engine->pushBookings($this->unit, [], $connector, dryRun: true);

        expect($stats['created'])->toBe(1)
            ->and($connector->pushes)->toBeEmpty()
            ->and(BookingSource::count())->toBe(0);
    });

    test('pushes a non-protected booking on save to the unit writable sources', function () {
        $connector = makePushConnector();
        app(SyncRegistry::class)->register($connector);
        $this->unit->update(['options' => ['sources' => [['type' => 'beds24', 'enabled' => true]]]]);

        $booking = ($this->makeManual)(['source_name' => 'direct']);
        $stats = $this->engine->pushBooking($booking);

        expect($stats['pushed'])->toBe(1)
            ->and($connector->pushes)->toHaveCount(1)
            ->and($booking->sources()->where('source_key', 'beds24')->first()->pushed_at)->not->toBeNull();
    });

    test('never pushes a protected airbnb booking on save', function () {
        $connector = makePushConnector();
        app(SyncRegistry::class)->register($connector);
        $this->unit->update(['options' => ['sources' => [['type' => 'beds24', 'enabled' => true]]]]);

        $booking = ($this->makeManual)(['source_name' => 'airbnb']);
        $stats = $this->engine->pushBooking($booking);

        expect($stats['pushed'])->toBe(0)
            ->and($connector->pushes)->toBeEmpty();
    });

    test('skips read-only sources on save', function () {
        $connector = makePushConnector();
        app(SyncRegistry::class)->register($connector);
        $this->unit->update(['options' => ['sources' => [['type' => 'beds24', 'enabled' => true, 'readonly' => true]]]]);

        $booking = ($this->makeManual)(['source_name' => 'direct']);
        $stats = $this->engine->pushBooking($booking);

        expect($stats['pushed'])->toBe(0)
            ->and($connector->pushes)->toBeEmpty();
    });

    test('a pulled echo of a pushed booking never duplicates nor overwrites', function () {
        $booking = ($this->makeManual)();
        $pushConnector = makePushConnector();
        $this->engine->pushBookings($this->unit, [], $pushConnector);

        // Beds24 reports our own booking back on the next pull.
        $echo = new class(new NormalizedBooking(externalId: '9000', checkIn: $booking->check_in->format('Y-m-d'), checkOut: $booking->check_out->format('Y-m-d'), guestName: 'Gudule MANGLED BY BEDS24', status: 'confirmed', claimsOrigin: true)) implements SourceConnector
        {
            public function __construct(private NormalizedBooking $booking) {}

            public function sourceType(): string
            {
                return 'beds24';
            }

            public function label(): string
            {
                return 'Beds24 API';
            }

            public function displayLabel(array $sourceConfig): string
            {
                return 'Beds24 API';
            }

            public function sourceKey(Unit $unit, array $sourceConfig): string
            {
                return 'beds24';
            }

            public function fetchBookings(Unit $unit, array $sourceConfig): array
            {
                return [$this->booking];
            }

            public function externalBookingUrl(BookingSource $source): ?string
            {
                return null;
            }
        };
        $stats = $this->engine->sync($this->unit, [], $echo);

        $booking->refresh();
        expect($stats['new'])->toBe(0)
            ->and(Booking::count())->toBe(1)
            ->and($booking->guest_name)->toBe('Gudule Lapointe')
            ->and($booking->sources()->where('is_origin', true)->count())->toBe(0);
    });
});
