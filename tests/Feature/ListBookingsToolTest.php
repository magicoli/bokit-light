<?php

use App\Models\Booking;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Magicoli\AssistantMcpEngine\Models\Assistant;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->assistant = Assistant::forceCreate(['name' => 'Test Tenant', 'slug' => 'test-tenant']);

    $this->admin = User::create([
        'name' => 'Admin',
        'email' => 'admin@test.local',
        'password' => 'secret-password',
        'is_admin' => true,
    ]);
    $this->assistant->forceFill(['owner_id' => $this->admin->id])->save();

    $this->property = Property::create([
        'assistant_id' => $this->assistant->id,
        'name' => 'Moon',
        'slug' => 'moon',
        'is_active' => true,
    ]);

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
    $slug = test()->assistant->slug;

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

it('lists bookings scoped to the authenticated tenant', function (): void {
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
        ->and($bookings[0]['price'])->toBe(1400)
        ->and($bookings[0]['property'])->toBe('Moon');
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

it('keeps property and guest_name as separate filters', function (): void {
    Sanctum::actingAs($this->admin);

    Booking::create([
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'guest_name' => 'Moon Traveler',
        'status' => 'confirmed',
        'check_in' => '2026-09-01',
        'check_out' => '2026-09-08',
    ]);

    // Property named "Moon" must not match a guest_name search for "Moon".
    expect(bookingResults(callListBookings(['guest_name' => 'Moon'])))->toHaveCount(1);
    expect(bookingResults(callListBookings(['property' => 'Moon'])))->toHaveCount(1);
    expect(bookingResults(callListBookings(['property' => 'Nonexistent'])))->toHaveCount(0);
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

it('hides bookings from a sibling property in the same tenant the user has no access to', function (): void {
    $otherProperty = Property::create([
        'assistant_id' => $this->assistant->id,
        'name' => 'Sun',
        'slug' => 'sun',
        'is_active' => true,
    ]);
    $otherUnit = Unit::create([
        'property_id' => $otherProperty->id,
        'name' => 'Sun Unit',
        'slug' => 'sun-unit',
        'is_active' => true,
    ]);

    Booking::create([
        'property_id' => $otherProperty->id,
        'unit_id' => $otherUnit->id,
        'guest_name' => 'Sun Guest',
        'status' => 'confirmed',
        'check_in' => '2026-09-01',
        'check_out' => '2026-09-08',
    ]);

    // A staff member attached only to "Moon" — not admin, not the tenant owner.
    $staff = User::create([
        'name' => 'Staff',
        'email' => 'staff@test.local',
        'password' => 'secret-password',
    ]);
    $staff->properties()->attach($this->property->id, ['role' => 'manager']);

    Sanctum::actingAs($staff);

    expect(bookingResults(callListBookings()))->toHaveCount(0);
});

it('rejects a request for another tenant\'s slug entirely', function (): void {
    Assistant::forceCreate(['name' => 'Other Tenant', 'slug' => 'other-tenant']);

    // Neither this tenant's owner nor attached to any of its properties — and not is_admin
    // (bokit's own site-wide flag, a deliberate cross-tenant bypass, tested separately).
    $staff = User::create([
        'name' => 'Staff',
        'email' => 'staff-isolation@test.local',
        'password' => 'secret-password',
    ]);
    $staff->properties()->attach($this->property->id, ['role' => 'manager']);

    Sanctum::actingAs($staff);

    test()->postJson('/mcp/other-tenant/bookings', [
        'jsonrpc' => '2.0',
        'method' => 'tools/call',
        'params' => ['name' => 'list_bookings', 'arguments' => []],
        'id' => 1,
    ])->assertForbidden();
});

it('lets a site-wide admin (is_admin) into any tenant, including one they neither own nor are attached to', function (): void {
    $other = Assistant::forceCreate(['name' => 'Other Tenant', 'slug' => 'other-tenant']);
    $otherProperty = Property::create([
        'assistant_id' => $other->id,
        'name' => 'Sun',
        'slug' => 'sun',
        'is_active' => true,
    ]);
    Unit::create([
        'property_id' => $otherProperty->id,
        'name' => 'Sun Unit',
        'slug' => 'sun-unit',
        'is_active' => true,
    ]);

    // $this->admin is neither other-tenant's owner nor attached to Sun — only is_admin.
    Sanctum::actingAs($this->admin);

    test()->postJson('/mcp/other-tenant/bookings', [
        'jsonrpc' => '2.0',
        'method' => 'tools/call',
        'params' => ['name' => 'list_bookings', 'arguments' => []],
        'id' => 1,
    ])->assertSuccessful();
});
