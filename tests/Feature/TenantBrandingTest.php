<?php

use App\Models\Property;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Tenant branding', function () {
    beforeEach(function () {
        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'secret-password',
            'is_admin' => true,
        ]);
        $this->property = Property::create(['name' => 'Gîtes Mosaïques', 'slug' => 'mosaiques', 'is_active' => true]);

        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($this->property);
    });

    test('brand name is the current tenant, not the app name', function () {
        expect(Filament::getPanel('app')->getBrandName())->toBe('Gîtes Mosaïques')
            ->and(Filament::getPanel('app')->getBrandName())->not->toBe(config('app.name'));
    });

    test('the brand/logo link leads to the current tenant, not the public homepage or "/app"', function () {
        $homeUrl = Filament::getPanel('app')->getHomeUrl();

        expect($homeUrl)->toContain("/app/{$this->property->slug}")
            ->and($homeUrl)->not->toBe(url('/'))
            ->and(rtrim((string) parse_url($homeUrl, PHP_URL_PATH), '/'))->not->toBe('/app');
    });

    test('the Main panel keeps its own app-wide branding, untouched by tenancy', function () {
        Filament::setCurrentPanel(Filament::getPanel('main'));

        expect(Filament::getPanel('main')->getBrandName())->toBe(config('app.name'))
            ->and(Filament::getPanel('main')->getHomeUrl())->toBe('/');
    });
});
