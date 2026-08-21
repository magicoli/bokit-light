<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the rates calculator page renders for a signed-in user', function () {
    $user = User::create([
        'name' => 'U',
        'email' => 'u@test.local',
        'password' => 'secret-password',
    ]);

    $this->actingAs($user)
        ->get(route('rates.calculator'))
        ->assertSuccessful()
        ->assertSee('rate-widget', escape: false);
});
