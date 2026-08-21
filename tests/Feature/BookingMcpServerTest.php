<?php

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->property = Property::create(['name' => 'Test Property', 'slug' => 'test-property', 'is_active' => true]);
});

/**
 * Exercises the real HTTP route, not just the server class in isolation — this is what caught
 * the actual bug once: /mcp/{property}/bookings was registered AFTER web.php's catch-all
 * '/{property:slug}/{unit:slug}' route, which matched first and silently swallowed every request
 * to it (a 419 CSRF mismatch from that catch-all's own 'web' middleware, not a 401 from the MCP
 * route at all). Fixed by loading routes/mcp.php before web.php in bootstrap/app.php.
 */
it('rejects an unauthenticated MCP request', function (): void {
    $this->postJson("/mcp/{$this->property->slug}/bookings", [
        'jsonrpc' => '2.0',
        'method' => 'initialize',
        'id' => 1,
    ])->assertUnauthorized();
});

it('rejects a user with no access to the property', function (): void {
    $outsider = User::create([
        'name' => 'Outsider',
        'email' => 'outsider@test.local',
        'password' => 'secret-password',
    ]);

    Sanctum::actingAs($outsider);

    $this->postJson("/mcp/{$this->property->slug}/bookings", [
        'jsonrpc' => '2.0',
        'method' => 'initialize',
        'id' => 1,
    ])->assertForbidden();
});

it('completes the MCP initialize handshake for an authenticated staff member', function (): void {
    $staff = User::create([
        'name' => 'Concierge',
        'email' => 'concierge@test.local',
        'password' => 'secret-password',
    ]);
    $staff->properties()->attach($this->property->id, ['role' => 'manager']);

    Sanctum::actingAs($staff);

    $this->postJson("/mcp/{$this->property->slug}/bookings", [
        'jsonrpc' => '2.0',
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-06-18',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ],
        'id' => 1,
    ])
        ->assertSuccessful()
        ->assertJsonPath('result.serverInfo.name', 'Bokit Booking MCP');
});
