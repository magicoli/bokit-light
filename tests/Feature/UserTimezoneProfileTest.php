<?php

use App\Filament\Profile\TimezoneForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('User timezone profile field', function () {
    beforeEach(function () {
        $this->user = User::create([
            'name' => 'U', 'email' => 'u@test.local', 'password' => 'secret-password',
        ]);
    });

    test('shows the timezone field on the edit-profile page', function () {
        $this->actingAs($this->user);

        $this->get('/edit-profile')
            ->assertSuccessful()
            ->assertSee(__('user.field.timezone'));
    });

    test('saves the timezone directly on the user record, same tier as locale', function () {
        $this->actingAs($this->user);

        Livewire::test(TimezoneForm::class)
            ->fillForm(['timezone' => 'Indian/Reunion'])
            ->call('updateTimezone')
            ->assertHasNoFormErrors();

        expect($this->user->fresh()->timezone)->toBe('Indian/Reunion');
    });

    test('placeholder names the app-wide default', function () {
        $this->actingAs($this->user);

        Livewire::test(TimezoneForm::class)
            ->assertFormFieldExists('timezone', function ($field): bool {
                return str_contains($field->getPlaceholder(), User::defaultTimezone());
            });
    });
});
