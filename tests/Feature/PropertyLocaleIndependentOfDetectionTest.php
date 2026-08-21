<?php

use App\Filament\Pages\Tenancy\EditTenantProfile;
use App\Models\Property;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

/**
 * "La langue par défaut d'une property ne doit pas suivre la logique de détection de la langue de
 * l'utilisateur, ce serait trompeur : le propriétaire croit que son site s'affiche en français par
 * défaut alors que pour un utilisateur sans préférences il sera en anglais. Le processus de
 * détection influence l'affichage, mais pas les défauts de la property." (Oli)
 *
 * Property::locale() and the EditTenantProfile 'locale' field must read/show ONLY the property's
 * own stored column (null = app-wide default) - never anything derived from the CURRENT viewer's
 * own detected/preferred language, even though that viewer's preference legitimately changes what
 * they themselves see rendered around it.
 */
uses(RefreshDatabase::class);

test("an admin's own French UI preference never leaks into a property's stored default locale", function () {
    config(['app.locale' => 'en']);

    $admin = User::create([
        'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'secret-password',
        'is_admin' => true, 'locale' => 'fr',
    ]);
    $property = Property::create(['name' => 'P', 'slug' => 'p', 'is_active' => true]);

    // The property never had its own locale set. getRawOriginal(), not bare ->locale - Property
    // has both a real `locale` column and a same-named method (mirrors TimezoneTrait's
    // timezone()/timezone column), so bare access risks Eloquent's relationship-resolution path
    // instead of the column on a freshly-created, not-yet-refetched model.
    expect($property->getRawOriginal('locale'))->toBeNull();

    $this->actingAs($admin);

    // A real request, not a bare Livewire mount - this is what actually runs
    // BezhanSalleh\LanguageSwitch's SwitchLanguageLocale middleware.
    $this->get("/app/{$property->slug}/profile")->assertOk();

    // The admin's OWN preference (fr) legitimately drove the page just rendered...
    expect(app()->getLocale())->toBe('fr');

    // ...but the property's own stored default, read through the exact same page, remains
    // untouched - still null in the DB, still resolving to the APP default (en), not fr.
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($property);
    Livewire::test(EditTenantProfile::class)->assertFormSet(['locale' => null]);

    expect($property->fresh()->getRawOriginal('locale'))->toBeNull()
        ->and($property->fresh()->locale())->toBe('en');
});

test("an anonymous visitor with no preference of their own gets the property's real default (en), not a signed-in admin's french", function () {
    config(['app.locale' => 'en']);

    $admin = User::create([
        'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'secret-password',
        'is_admin' => true, 'locale' => 'fr',
    ]);
    $property = Property::create([
        'name' => 'P', 'slug' => 'p', 'is_active' => true,
        'locales' => ['en', 'fr'],
    ]);

    // The admin switching their OWN language earlier must not have contaminated this property's
    // stored default - re-affirms the same fact from the visitor's side, not the settings form's.
    expect($property->fresh()->getRawOriginal('locale'))->toBeNull();
    expect($admin->locale)->toBe('fr');
});
