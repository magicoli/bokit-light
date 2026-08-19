<?php

use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Beds24\Services\Beds24V2ApiService;

describe('Beds24 Auth', function () {
    uses(RefreshDatabase::class);

    test('stores the permanent refresh token from the invite-code exchange, not the short-lived token', function () {
        Http::fake([
            'api.beds24.com/v2/authentication/setup' => Http::response([
                'token' => 'short-lived-auth-token',
                'expiresIn' => 3600,
                'refreshToken' => 'permanent-refresh-token',
            ]),
        ]);

        expect(Beds24V2ApiService::exchangeInviteCode('INVITE123'))
            ->toBe('permanent-refresh-token');

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/v2/authentication/setup')
            && $request->hasHeader('code', 'INVITE123'));
    });

    test('refreshes an auth token using the stored refresh token', function () {
        Http::fake([
            'api.beds24.com/v2/authentication/token' => Http::response(['token' => 'fresh-auth-token', 'expiresIn' => 3600]),
            'api.beds24.com/v2/bookings' => Http::response([['success' => true, 'new' => ['id' => 1]]], 201),
        ]);

        $property = Property::create([
            'name' => 'P',
            'slug' => 'p',
            'is_active' => true,
            'options' => ['beds24_refresh_token' => 'permanent-refresh-token'],
        ]);

        (new Beds24V2ApiService($property))->postBookings([['roomId' => 1]]);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/v2/authentication/token')
            && $request->hasHeader('refreshToken', 'permanent-refresh-token'));
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/v2/bookings')
            && $request->hasHeader('token', 'fresh-auth-token'));
    });
});
