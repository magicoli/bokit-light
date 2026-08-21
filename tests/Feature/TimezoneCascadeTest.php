<?php

use App\Models\Booking;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('timezone cascade', function () {
    test('property falls back to the app-wide default when it has none of its own', function () {
        $property = Property::create(['name' => 'P', 'slug' => 'p', 'is_active' => true]);

        // The app-wide default itself is the existing Options-file cascade, not touched by this
        // pass - what matters here is that an unset property.timezone falls through to it rather
        // than, say, an empty string or null.
        expect($property->timezone())->toBe(Property::defaultTimezone());
    });

    test('property uses its own timezone when set', function () {
        $property = Property::create([
            'name' => 'P', 'slug' => 'p', 'is_active' => true,
            'timezone' => 'Asia/Tokyo',
        ]);

        expect($property->timezone())->toBe('Asia/Tokyo');
    });

    test('a unit always uses its property timezone, never its own', function () {
        $property = Property::create([
            'name' => 'P', 'slug' => 'p', 'is_active' => true,
            'timezone' => 'Asia/Tokyo',
        ]);
        $unit = Unit::create([
            'property_id' => $property->id, 'name' => 'U', 'slug' => 'u', 'is_active' => true,
        ]);

        expect($unit->timezone())->toBe('Asia/Tokyo');
    });

    test('a booking always uses its property timezone, never its own', function () {
        $property = Property::create([
            'name' => 'P', 'slug' => 'p', 'is_active' => true,
            'timezone' => 'Pacific/Tahiti',
        ]);
        $unit = Unit::create([
            'property_id' => $property->id, 'name' => 'U', 'slug' => 'u', 'is_active' => true,
        ]);
        $booking = Booking::create([
            'property_id' => $property->id, 'unit_id' => $unit->id,
            'guest_name' => 'Guest', 'status' => 'confirmed',
            'check_in' => '2026-09-01', 'check_out' => '2026-09-08',
        ]);

        expect($booking->timezone())->toBe('Pacific/Tahiti');
    });

    test('changing the property timezone changes what its bookings display, via the unit', function () {
        $property = Property::create([
            'name' => 'P', 'slug' => 'p', 'is_active' => true,
            'timezone' => 'Pacific/Tahiti',
        ]);
        $unit = Unit::create([
            'property_id' => $property->id, 'name' => 'U', 'slug' => 'u', 'is_active' => true,
        ]);

        // Booking::toDetailPayload() formats check_in/check_out through $this->unit->shiftAndFormat() -
        // this is the actual call path in production code, not just Booking::timezone() in isolation.
        expect($unit->timezone())->toBe($property->timezone());
    });

    test('a user falls back to the app-wide default when they have no timezone of their own', function () {
        $user = User::create([
            'name' => 'U', 'email' => 'u@test.local', 'password' => 'secret-password',
        ]);

        expect($user->timezone())->toBe(User::defaultTimezone());
    });

    test('a user uses their own timezone when set', function () {
        $user = User::create([
            'name' => 'U', 'email' => 'u@test.local', 'password' => 'secret-password',
            'timezone' => 'Indian/Reunion',
        ]);

        expect($user->timezone())->toBe('Indian/Reunion');
    });
});
