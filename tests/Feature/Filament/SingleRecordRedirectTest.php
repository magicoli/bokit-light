<?php

use App\Filament\Resources\Properties\PropertyResource;
use App\Filament\Resources\Units\UnitResource;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

describe('Single record redirect', function () {

    uses(RefreshDatabase::class);

    beforeEach(function () {
        $this->property = Property::create([
            'name' => 'Solo Property',
            'slug' => 'solo-property',
            'is_active' => true,
        ]);
        $this->unit = Unit::create([
            'property_id' => $this->property->id,
            'name' => 'Solo Unit',
            'slug' => 'solo-unit',
            'is_active' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@test.local',
            'password' => bcrypt('password'),
            'is_admin' => false,
        ]);
        $this->owner->properties()->attach($this->property->id, ['role' => 'owner']);

        Filament::setCurrentPanel(Filament::getPanel('app'));
        PropertyResource::skipAuthorization();
        UnitResource::skipAuthorization();
    });

    afterEach(function () {
        PropertyResource::skipAuthorization(false);
        UnitResource::skipAuthorization(false);
    });

    test('sends an owner with a single property straight to its page', function () {
        $this->actingAs($this->owner);

        $this->get("/app/{$this->property->slug}/properties")
            ->assertRedirect(PropertyResource::getUrl('view', ['record' => $this->property], tenant: $this->property));
    });

    test('sends an owner with a single unit straight to its page', function () {
        $this->actingAs($this->owner);

        $this->get("/app/{$this->property->slug}/units")
            ->assertRedirect(UnitResource::getUrl('view', ['record' => $this->unit], tenant: $this->property));
    });

    test('keeps the list for owners with several records', function () {
        $second = Property::create([
            'name' => 'Second Property',
            'slug' => 'second-property',
            'is_active' => true,
        ]);
        Unit::create([
            'property_id' => $second->id,
            'name' => 'Second Unit',
            'slug' => 'second-unit',
            'is_active' => true,
        ]);
        $this->owner->properties()->attach($second->id, ['role' => 'owner']);

        // Units are tenant-scoped: a second unit within the CURRENT tenant is what keeps its
        // list from redirecting, regardless of how many other properties the owner also has.
        Unit::create([
            'property_id' => $this->property->id,
            'name' => 'Second Unit Same Property',
            'slug' => 'second-unit-same-property',
            'is_active' => true,
        ]);

        $this->actingAs($this->owner);

        $this->get("/app/{$this->property->slug}/properties")->assertSuccessful();
        $this->get("/app/{$this->property->slug}/units")->assertSuccessful();
    });

    test('renders the property edit page with the module sections', function () {
        $admin = User::create([
            'name' => 'Admin2',
            'email' => 'admin2@test.local',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);

        $this->actingAs($admin)
            ->get("/app/{$this->property->slug}/properties/{$this->property->id}/edit")
            ->assertSuccessful()
            ->assertSee(__('beds24::property.field.beds24_invite_code'));
    });

    test('keeps the list for admins even with a single record', function () {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);

        $this->actingAs($admin);

        $this->get("/app/{$this->property->slug}/properties")->assertSuccessful();
        $this->get("/app/{$this->property->slug}/units")->assertSuccessful();
    });
});
