<?php

use App\Models\Property;
use App\Models\User;
use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Property locale/locales', function () {
    test('falls back to the app-wide default locale when it has none of its own', function () {
        $property = Property::create(['name' => 'P', 'slug' => 'p', 'is_active' => true]);

        expect($property->locale())->toBe(config('app.locale'));
    });

    test('uses its own default locale when set', function () {
        $property = Property::create([
            'name' => 'P', 'slug' => 'p', 'is_active' => true, 'locale' => 'de',
        ]);

        expect($property->locale())->toBe('de');
    });

    test('offers every app-wide locale when it has no subset of its own', function () {
        $property = Property::create(['name' => 'P', 'slug' => 'p', 'is_active' => true]);

        expect($property->availableLocales())->toBe(config('app.locales'));
    });

    test('offers only its own enabled subset when configured (Gîtes Mosaïques: en/fr/de)', function () {
        $property = Property::create([
            'name' => 'P', 'slug' => 'p', 'is_active' => true,
            'locales' => ['en', 'fr', 'de'],
        ]);

        expect($property->availableLocales())->toBe(['en', 'fr', 'de'])
            ->and($property->availableLocales())->not->toContain('ja');
    });
});

describe('LanguageSwitch follows the current tenant', function () {
    beforeEach(function () {
        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'secret-password',
            'is_admin' => true,
        ]);
        $this->property = Property::create([
            'name' => 'Gîtes Mosaïques', 'slug' => 'mosaiques', 'is_active' => true,
            'locales' => ['en', 'fr', 'de'],
        ]);

        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($this->property);
    });

    test('the switcher only offers the tenant own enabled subset inside the App panel', function () {
        expect(LanguageSwitch::make()->getLocales())->toBe(['en', 'fr', 'de']);
    });

    test('the switcher offers every app-wide locale outside a tenant panel', function () {
        Filament::setCurrentPanel(Filament::getPanel('main'));
        Filament::setTenant(null);

        expect(LanguageSwitch::make()->getLocales())->toBe(config('app.locales'));
    });
});
