<?php

use App\Models\Booking;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = User::create([
        'name' => 'Admin',
        'email' => 'admin@test.local',
        'password' => 'secret-password',
        'is_admin' => true,
    ]);

    $this->property = Property::create(['name' => 'Moon', 'slug' => 'moon', 'is_active' => true]);
    $this->unit = Unit::create([
        'property_id' => $this->property->id,
        'name' => 'Moon Unit',
        'slug' => 'moon-unit',
        'is_active' => true,
    ]);
});

function callGetBooking(int $bookingId, ?string $slug = null): TestResponse
{
    $slug ??= test()->property->slug;

    return test()->postJson("/mcp/{$slug}/bookings", [
        'jsonrpc' => '2.0',
        'method' => 'tools/call',
        'params' => ['name' => 'get_booking', 'arguments' => ['booking_id' => $bookingId]],
        'id' => 1,
    ]);
}

it('returns full detail for a booking, computed by the shared toDetailPayload()', function (): void {
    Sanctum::actingAs($this->admin);

    $booking = Booking::create([
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'guest_name' => 'Jean Dupont',
        'status' => 'confirmed',
        'check_in' => '2026-09-01',
        'check_out' => '2026-09-08',
        'price' => 1400,
        'metadata' => ['invoice_payment_total' => 500, 'deposit' => 420],
    ]);

    $response = callGetBooking($booking->id);
    $response->assertSuccessful();

    $data = json_decode($response->json('result.content.0.text'), true);

    // Same values Booking::toDetailPayload() computes for the calendar's own booking modal
    // (CalendarBookingEndpointTest) — this tool calls the exact same method, not a copy.
    expect($data['guest_name'])->toBe('Jean Dupont')
        ->and($data['price'])->toBe(1400)
        ->and($data['deposit'])->toBe(420)
        ->and($data['paid'])->toBe(500)
        ->and($data['balance'])->toBe(900)
        ->and($data['unit']['name'])->toBe('Moon Unit')
        ->and($data['property']['name'])->toBe('Moon');
});

it('errors cleanly for an unknown booking id', function (): void {
    Sanctum::actingAs($this->admin);

    callGetBooking(999999)
        ->assertSuccessful()
        ->assertJsonPath('result.isError', true);
});

it('denies access to a booking that belongs to a different property', function (): void {
    $otherProperty = Property::create(['name' => 'Sun', 'slug' => 'sun', 'is_active' => true]);
    $otherUnit = Unit::create([
        'property_id' => $otherProperty->id,
        'name' => 'Sun Unit',
        'slug' => 'sun-unit',
        'is_active' => true,
    ]);
    $otherBooking = Booking::create([
        'property_id' => $otherProperty->id,
        'unit_id' => $otherUnit->id,
        'guest_name' => 'Someone Else',
        'status' => 'confirmed',
        'check_in' => '2026-09-01',
        'check_out' => '2026-09-08',
    ]);

    Sanctum::actingAs($this->admin);

    // Admin CAN reach /mcp/sun/bookings directly, but not this property's booking through
    // /mcp/moon/bookings — the property scoping is enforced by the tool itself, independent of
    // whether the caller happens to have cross-property access some other way.
    callGetBooking($otherBooking->id, 'moon')
        ->assertSuccessful()
        ->assertJsonPath('result.isError', true);
});

it('denies access to a booking for a staff member with no access to this property', function (): void {
    $staff = User::create([
        'name' => 'Staff',
        'email' => 'staff@test.local',
        'password' => 'secret-password',
    ]);
    $otherProperty = Property::create(['name' => 'Sun', 'slug' => 'sun', 'is_active' => true]);
    $staff->properties()->attach($otherProperty->id, ['role' => 'manager']);

    Sanctum::actingAs($staff);

    $booking = Booking::create([
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'guest_name' => 'Jean Dupont',
        'status' => 'confirmed',
        'check_in' => '2026-09-01',
        'check_out' => '2026-09-08',
    ]);

    callGetBooking($booking->id)->assertForbidden();
});
