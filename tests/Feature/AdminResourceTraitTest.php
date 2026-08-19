<?php

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

describe('Admin resource trait', function () {
    uses(RefreshDatabase::class);

    beforeEach(function () {
        $this->admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.local',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);
    });

    test('registers the classic admin routes', function () {
        expect(Route::has('admin.bookings.index'))->toBeTrue()
            ->and(Route::has('admin.bookings.list'))->toBeTrue()
            ->and(Route::has('admin.bookings.create'))->toBeTrue()
            ->and(Route::has('admin.bookings.settings'))->toBeTrue();
    });

    test('lets an admin reach the bookings list', function () {
        $this->actingAs($this->admin)->get(route('admin.bookings.list'))
            ->assertOk()
            ->assertViewIs('admin.resource.list')
            ->assertViewHas('resource', 'bookings');
    });

    test('lets an admin reach the create page', function () {
        $this->actingAs($this->admin)->get(route('admin.bookings.create'))
            ->assertOk()
            ->assertViewIs('admin.resource.create');
    });

    test('lets an admin reach the settings page', function () {
        $this->actingAs($this->admin)->get(route('admin.bookings.settings'))
            ->assertOk()
            ->assertViewIs('admin.resource.settings');
    });

    test('redirects a guest from the admin routes to login', function () {
        $this->get(route('admin.bookings.list'))
            ->assertRedirect(route('filament.main.auth.login'));
    });

    test('derives the resource name from the model', function () {
        expect(Booking::getResourceName())->toBe('bookings');
    });

    test('builds the admin menu config', function () {
        $menu = Booking::adminMenuConfig();

        expect($menu)->toHaveKeys(['label', 'children', 'url'])
            ->and($menu['children'])->toBeArray()->not->toBeEmpty();
    });
});
