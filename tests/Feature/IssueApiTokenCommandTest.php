<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('issues a personal access token for the given user', function (): void {
    $user = User::create([
        'name' => 'Concierge',
        'email' => 'concierge@test.local',
        'password' => 'secret-password',
    ]);

    $this->artisan('bokit:issue-api-token', ['email' => 'concierge@test.local', 'name' => 'mcp'])
        ->assertSuccessful();

    expect($user->fresh()->tokens()->where('name', 'mcp')->exists())->toBeTrue();
});

it('fails cleanly for an email with no matching user', function (): void {
    $this->artisan('bokit:issue-api-token', ['email' => 'nobody@test.local'])
        ->assertFailed();
});
