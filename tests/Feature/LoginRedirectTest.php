<?php

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

describe('Login redirect', function () {
    uses(RefreshDatabase::class);

    test('sends admins to the admin panel', function () {
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

    test('sends property owners to the admin panel', function () {
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

    test('sends users without panel access to the dashboard', function () {
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

    test('redirects an already authenticated user to their home page', function () {
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
});
