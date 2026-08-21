<?php

use App\Filament\Pages\GeneralSettings;
use App\Models\Property;
use App\Models\User;
use App\Support\Options;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('General settings', function () {
    beforeEach(function () {
        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'secret-password',
            'is_admin' => true,
        ]);
        $this->owner = User::create([
            'name' => 'Owner', 'email' => 'owner@test.local', 'password' => 'secret-password',
        ]);
        $this->property = Property::create(['name' => 'P', 'slug' => 'p', 'is_active' => true]);
        $this->owner->properties()->attach($this->property->id, ['role' => 'owner']);

        Filament::setCurrentPanel(Filament::getPanel('app'));
    });

    test('reaches the page for an admin', function () {
        $this->actingAs($this->admin);
        Filament::setTenant($this->property);

        expect(GeneralSettings::canAccess())->toBeTrue();

        $this->get("/app/{$this->property->slug}/general-settings")->assertOk();
    });

    test('is out of reach for a non-admin', function () {
        $this->actingAs($this->owner);
        Filament::setTenant($this->property);

        expect(GeneralSettings::canAccess())->toBeFalse();

        $this->get("/app/{$this->property->slug}/general-settings")->assertForbidden();
    });

    test('saves the display timezone through the Options cascade', function () {
        $this->actingAs($this->admin);
        Filament::setTenant($this->property);

        // Options writes straight to disk (App\Support\Options), not scoped by RefreshDatabase -
        // route registration already happened against the real storage/options/*.json (needed for
        // install.complete etc.), so it's safe to redirect the path for the rest of this test:
        // only its own Options::get()/set() calls are affected, not what already decided which
        // routes exist.
        config(['options.path' => sys_get_temp_dir().'/bokit-test-options-'.uniqid()]);

        Livewire::test(GeneralSettings::class)
            ->fillForm(['timezone' => 'Asia/Tokyo'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect(Options::get('timezone'))->toBe('Asia/Tokyo');
    });

    test('placeholder names the value used when left empty', function () {
        $this->actingAs($this->admin);
        Filament::setTenant($this->property);

        Livewire::test(GeneralSettings::class)
            ->assertFormFieldExists('timezone', function ($field): bool {
                return str_contains($field->getPlaceholder(), (string) config('app.timezone'));
            });
    });
});
