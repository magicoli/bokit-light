<?php

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('sends admins to the admin panel after login', function () {
    User::create([
        'name' => 'Admin',
        'email' => 'admin@test.local',
        'password' => 'secret-password',
        'is_admin' => true,
    ]);

    $this->post('/login', [
        'username' => 'admin@test.local',
        'password' => 'secret-password',
    ])->assertRedirect('/admin');
});

it('sends property owners to the admin panel after login', function () {
    $property = Property::create([
        'name' => 'Test Property',
        'slug' => 'test-property',
        'is_active' => true,
    ]);
    $owner = User::create([
        'name' => 'Owner',
        'email' => 'owner@test.local',
        'password' => 'secret-password',
        'is_admin' => false,
    ]);
    $owner->properties()->attach($property->id, ['role' => 'owner']);

    $this->post('/login', [
        'username' => 'owner@test.local',
        'password' => 'secret-password',
    ])->assertRedirect('/admin');
});

it('sends users without panel access to the dashboard after login', function () {
    User::create([
        'name' => 'Basic',
        'email' => 'basic@test.local',
        'password' => 'secret-password',
        'is_admin' => false,
    ]);

    $this->post('/login', [
        'username' => 'basic@test.local',
        'password' => 'secret-password',
    ])->assertRedirect('/dashboard');
});

it('redirects an already authenticated user to their home page', function () {
    $admin = User::create([
        'name' => 'Admin',
        'email' => 'admin@test.local',
        'password' => 'secret-password',
        'is_admin' => true,
    ]);

    $this->actingAs($admin)
        ->get('/login')
        ->assertRedirect('/admin');
});
