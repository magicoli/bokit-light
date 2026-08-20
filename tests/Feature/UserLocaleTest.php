<?php

use App\Models\User;
use BezhanSalleh\LanguageSwitch\Events\LocaleChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The interface language must follow the account, not the browser: LanguageSwitch reads it back
 * through userPreferredLocale() (auth()->user()->locale) and AppServiceProvider persists every
 * change via the LocaleChanged listener. Both need users.locale to exist and be fillable — the
 * logic shipped without the column, so the choice was silently dropped and every session reverted
 * to English.
 */
it('stores the locale column as a real, fillable attribute', function (): void {
    $user = User::create([
        'name' => 'Léa',
        'email' => 'lea@test.local',
        'password' => 'secret-password',
        'locale' => 'fr',
    ]);

    expect($user->fresh()->locale)->toBe('fr');
});

it('persists the chosen locale to the signed-in user when the switch fires', function (): void {
    $user = User::create([
        'name' => 'Léa',
        'email' => 'lea@test.local',
        'password' => 'secret-password',
        'locale' => 'en',
    ]);

    $this->actingAs($user);
    event(new LocaleChanged('nl'));

    expect($user->fresh()->locale)->toBe('nl');
});
