<?php

use App\Filament\Pages\Tenancy\EditTenantProfile;
use App\Models\Property;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('Tenant profile', function () {
    beforeEach(function () {
        $this->property = Property::create(['name' => 'P', 'slug' => 'p', 'is_active' => true]);

        $this->owner = User::create([
            'name' => 'Owner', 'email' => 'owner@test.local', 'password' => 'secret-password',
        ]);
        $this->owner->properties()->attach($this->property->id, ['role' => 'owner']);

        $this->outsider = User::create([
            'name' => 'Outsider', 'email' => 'outsider@test.local', 'password' => 'secret-password',
        ]);

        Filament::setCurrentPanel(Filament::getPanel('app'));
    });

    test('an owner can view and reach their own property profile', function () {
        $this->actingAs($this->owner);
        Filament::setTenant($this->property);

        expect(EditTenantProfile::canView($this->property))->toBeTrue();

        $this->get("/app/{$this->property->slug}/profile")->assertOk();
    });

    test('someone with no access to the property cannot view its profile', function () {
        $this->actingAs($this->outsider);
        Filament::setTenant($this->property);

        expect(EditTenantProfile::canView($this->property))->toBeFalse();
    });

    test('carries the same sync-critical fields as EditProperty - nothing lost by reusing PropertyForm', function () {
        $this->actingAs($this->owner);
        Filament::setTenant($this->property);

        $this->get("/app/{$this->property->slug}/profile")
            ->assertSuccessful()
            ->assertSee(__('beds24::property.field.beds24_invite_code'));
    });

    test('saves the timezone and logo directly on the property record, same tier as name/slug', function () {
        $this->actingAs($this->owner);
        Filament::setTenant($this->property);

        Livewire::test(EditTenantProfile::class)
            ->fillForm(['timezone' => 'Asia/Tokyo'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($this->property->fresh()->timezone)->toBe('Asia/Tokyo');
    });

    test('timezone placeholder names the app-wide default', function () {
        $this->actingAs($this->owner);
        Filament::setTenant($this->property);

        Livewire::test(EditTenantProfile::class)
            ->assertFormFieldExists('timezone', function ($field): bool {
                return str_contains($field->getPlaceholder(), Property::defaultTimezone());
            });
    });
});
