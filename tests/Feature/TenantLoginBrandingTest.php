<?php

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Login branding follows the tenant a guest was trying to reach', function () {
    beforeEach(function () {
        $this->property = Property::create([
            'name' => 'Gîtes Mosaïques', 'slug' => 'mosaiques', 'is_active' => true,
        ]);
    });

    test('a guest bounced from a tenant URL sees that tenant name on the login screen', function () {
        // Real unauthenticated request through the actual middleware stack - this is what stores
        // session('url.intended'), not something the test sets up by hand.
        $this->get("/app/{$this->property->slug}")->assertRedirect('/login');

        $this->get('/login')->assertSuccessful()->assertSee('Gîtes Mosaïques');
    });

    test('a guest reaching /login directly (no intended tenant) sees the app name, not a stray tenant', function () {
        $this->get('/login')
            ->assertSuccessful()
            ->assertSee(config('app.name'))
            ->assertDontSee('Gîtes Mosaïques');
    });

    test('an authenticated user browsing another Main panel page never leaks tenant branding from a stale intended URL', function () {
        $this->get("/app/{$this->property->slug}");

        $user = User::create([
            'name' => 'U', 'email' => 'u@test.local', 'password' => 'secret-password',
        ]);
        $this->actingAs($user);

        $this->get('/')->assertSuccessful()->assertDontSee('Gîtes Mosaïques');
    });
});
