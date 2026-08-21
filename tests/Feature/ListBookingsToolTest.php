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

/**
 * Calls list_bookings over the real MCP HTTP route (tools/call), same idiom as
 * BookingMcpServerTest — exercises the actual JSON-RPC contract, not just the tool in
 * isolation.
 */
function callListBookings(array $arguments = []): TestResponse
{
    $slug = test()->property->slug;

    return test()->postJson("/mcp/{$slug}/bookings", [
        'jsonrpc' => '2.0',
        'method' => 'tools/call',
        'params' => ['name' => 'list_bookings', 'arguments' => $arguments],
        'id' => 1,
    ]);
}

function bookingResults(TestResponse $response): array
{
    $text = $response->json('result.content.0.text');

    return json_decode($text, true)['bookings'];
}

it('lists bookings scoped to this property', function (): void {
    Sanctum::actingAs($this->admin);

    Booking::create([
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'guest_name' => 'Jean Dupont',
        'status' => 'confirmed',
        'check_in' => '2026-09-01',
        'check_out' => '2026-09-08',
        'price' => 1400,
    ]);

    $response = callListBookings();
    $response->assertSuccessful();

    $bookings = bookingResults($response);
    expect($bookings)->toHaveCount(1)
        ->and($bookings[0]['guest_name'])->toBe('Jean Dupont')
        ->and($bookings[0]['price'])->toBe(1400);
});

it('excludes cancelled bookings by default and includes them on request', function (): void {
    Sanctum::actingAs($this->admin);

    Booking::create([
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'guest_name' => 'Cancelled Guest',
        'status' => 'cancelled',
        'check_in' => '2026-09-01',
        'check_out' => '2026-09-08',
    ]);

    expect(bookingResults(callListBookings()))->toHaveCount(0);
    expect(bookingResults(callListBookings(['include_cancelled' => true])))->toHaveCount(1);
});

it('matches guest_name on any word, tolerant of a partial search', function (): void {
    Sanctum::actingAs($this->admin);

    Booking::create([
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'guest_name' => 'Atmosphère - Johan Lolot',
        'status' => 'confirmed',
        'check_in' => '2026-09-01',
        'check_out' => '2026-09-08',
    ]);
    Booking::create([
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'guest_name' => 'Someone Else',
        'status' => 'confirmed',
        'check_in' => '2026-09-10',
        'check_out' => '2026-09-15',
    ]);

    expect(bookingResults(callListBookings(['guest_name' => 'Lolot'])))->toHaveCount(1);
});

it('aggregates a group reservation into one entry with summed price', function (): void {
    Sanctum::actingAs($this->admin);

    $unit2 = Unit::create([
        'property_id' => $this->property->id,
        'name' => 'Second Unit',
        'slug' => 'second-unit',
        'is_active' => true,
    ]);

    Booking::create([
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'guest_name' => 'Groupe Kervella',
        'status' => 'confirmed',
        'check_in' => '2026-10-01',
        'check_out' => '2026-10-08',
        'price' => 3000,
        'group_id' => 555,
    ]);
    Booking::create([
        'property_id' => $this->property->id,
        'unit_id' => $unit2->id,
        'guest_name' => 'Groupe Kervella',
        'status' => 'confirmed',
        'check_in' => '2026-10-02',
        'check_out' => '2026-10-09',
        'price' => 1200,
        'group_id' => 555,
    ]);

    $bookings = bookingResults(callListBookings());

    expect($bookings)->toHaveCount(1)
        ->and($bookings[0]['is_group'])->toBeTrue()
        ->and($bookings[0]['group_size'])->toBe(2)
        ->and($bookings[0]['price'])->toBe(4200)
        ->and($bookings[0]['check_in'])->toBe('2026-10-01')
        ->and($bookings[0]['check_out'])->toBe('2026-10-09')
        ->and($bookings[0]['unit'])->toBe(['Moon Unit', 'Second Unit']);
});

it('sees nothing for a staff member with no access to this property', function (): void {
    $staff = User::create([
        'name' => 'Staff',
        'email' => 'staff@test.local',
        'password' => 'secret-password',
    ]);
    // Attached to a DIFFERENT property, not this one.
    $otherProperty = Property::create(['name' => 'Sun', 'slug' => 'sun', 'is_active' => true]);
    $staff->properties()->attach($otherProperty->id, ['role' => 'manager']);

    Sanctum::actingAs($staff);

    Booking::create([
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'guest_name' => 'Jean Dupont',
        'status' => 'confirmed',
        'check_in' => '2026-09-01',
        'check_out' => '2026-09-08',
    ]);

    callListBookings()->assertForbidden();
});

it('lets a site-wide admin (is_admin) into any property, owned or not', function (): void {
    $otherProperty = Property::create(['name' => 'Sun', 'slug' => 'sun', 'is_active' => true]);
    Booking::create([
        'property_id' => $otherProperty->id,
        'unit_id' => Unit::create([
            'property_id' => $otherProperty->id,
            'name' => 'Sun Unit',
            'slug' => 'sun-unit',
            'is_active' => true,
        ])->id,
        'guest_name' => 'Admin Reachable',
        'status' => 'confirmed',
        'check_in' => '2026-09-01',
        'check_out' => '2026-09-08',
    ]);

    // $this->admin is not attached to Sun at all — only is_admin.
    Sanctum::actingAs($this->admin);

    test()->postJson('/mcp/sun/bookings', [
        'jsonrpc' => '2.0',
        'method' => 'tools/call',
        'params' => ['name' => 'list_bookings', 'arguments' => []],
        'id' => 1,
    ])->assertSuccessful();
});
