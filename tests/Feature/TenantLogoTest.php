<?php

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

describe('Tenant logo', function () {
    beforeEach(function () {
        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'secret-password',
            'is_admin' => true,
        ]);
    });

    test('a property with its own logo shows it instead of the app-wide brand logo', function () {
        Storage::fake('public');
        Storage::disk('public')->put('property-logos/mosaiques.svg', 'fake-svg-contents');

        $property = Property::create([
            'name' => 'P', 'slug' => 'p', 'is_active' => true,
            'logo' => 'property-logos/mosaiques.svg',
        ]);

        $this->actingAs($this->admin);

        $response = $this->get("/app/{$property->slug}");

        $response->assertSuccessful()
            ->assertSee(Storage::disk('public')->url('property-logos/mosaiques.svg'), escape: false);
    });

    test('a property with no logo of its own shows no logo at all in the App panel - no app-wide fallback', function () {
        $property = Property::create(['name' => 'P', 'slug' => 'p', 'is_active' => true]);

        $this->actingAs($this->admin);

        $response = $this->get("/app/{$property->slug}");

        $response->assertSuccessful();
        expect(config('app.logo'))->not->toBeNull();
        $response->assertDontSee(config('app.logo'), escape: false);
    });

    test('the Main panel (no tenant) always shows the app-wide logo', function () {
        $this->actingAs($this->admin);

        $response = $this->get('/');

        $response->assertSuccessful();
        expect(config('app.logo'))->not->toBeNull();
        $response->assertSee(config('app.logo'), escape: false);
    });
});
