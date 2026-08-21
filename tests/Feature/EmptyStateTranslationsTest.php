<?php

use App\Filament\Resources\Rates\Pages\ListRates;
use App\Filament\Resources\Units\Pages\ListUnits;
use App\Models\Property;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

/**
 * Every resource list shows a real, grammatically correct empty-state translation
 * (booking.empty_state, property.empty_state, unit.empty_state, rate.empty_state,
 * user.empty_state) - not Filament's generic fallback and never a naive machine translation that
 * ignores a language's own grammar (Oli's example: "Aucun(e) bookings" instead of "Aucune
 * réservation"). PropertyResource/UserResource aren't exercised here with a genuinely empty list -
 * Property is the tenant itself (the admin's own list always includes at least the tenant
 * they're routed through) and User always includes the signed-in admin - both still declare
 * ->emptyStateHeading(__('...')) (confirmed by reading the source), and
 * TranslationCompletenessTest already guarantees every configured locale has the key.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::create([
        'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'secret-password',
        'is_admin' => true,
    ]);
    $this->property = Property::create(['name' => 'P', 'slug' => 'p', 'is_active' => true]);

    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($this->property);
});

test('units list shows the translated empty state when the property has no units', function () {
    Livewire::test(ListUnits::class)->assertSee(__('unit.empty_state'));
});

test('rates list shows the translated empty state when there are no rates', function () {
    Livewire::test(ListRates::class)->assertSee(__('rate.empty_state'));
});
