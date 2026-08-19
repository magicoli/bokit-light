<?php

use App\Http\Middleware\RedirectIfAuthenticated;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

describe('Login redirect', function () {
    uses(RefreshDatabase::class);

    // Authenticating is each panel's own Livewire form now, not a POST /login this app owns. What
    // stays bokit's to decide is where a user lands afterwards — homeUrl() — and the guest
    // middleware that sends an already signed-in visitor there.

    test('sends admins to the app panel', function () {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => 'secret-password',
            'is_admin' => true,
        ]);

        expect($admin->homeUrl())->toBe('/app');
    });

    test('sends property owners to the app panel', function () {
        $property = Property::create([
            'name' => 'Test Property',
            'slug' => 'test-property',
            'is_active' => true,
        ]);
        $owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@test.local',
            'password' => 'secret-password',
            'is_admin' => false,
        ]);
        $owner->properties()->attach($property->id, ['role' => 'owner']);

        expect($owner->homeUrl())->toBe('/app');
    });

    test('sends users without panel access to the dashboard', function () {
        $basic = User::create([
            'name' => 'Basic',
            'email' => 'basic@test.local',
            'password' => 'secret-password',
            'is_admin' => false,
        ]);

        expect($basic->homeUrl())->toBe('/dashboard');
    });

    test('redirects an already authenticated visitor to their home page', function () {
        // The 'guest' alias is RedirectIfAuthenticated, which sends a signed-in user to homeUrl().
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => 'secret-password',
            'is_admin' => true,
        ]);

        $this->actingAs($admin);

        $response = app(RedirectIfAuthenticated::class)->handle(request(), fn () => response('next'));

        expect($response)->toBeInstanceOf(\Illuminate\Http\RedirectResponse::class)
            ->and($response->getTargetUrl())->toContain('/app');
    });
});
