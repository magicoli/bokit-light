<?php

use App\Models\Booking;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Beds24\Services\Beds24Connector;

uses(RefreshDatabase::class);

describe('Beds24 push', function () {
    beforeEach(function () {
        $this->property = Property::create([
            'name' => 'PushP',
            'slug' => 'push-p',
            'is_active' => true,
            'options' => ['beds24_refresh_token' => 'refresh-secret'],
        ]);
        $this->unit = Unit::create([
            'property_id' => $this->property->id,
            'name' => 'Sun',
            'slug' => 'sun',
            'is_active' => true,
        ]);
        $this->unit->setRelation('property', $this->property);
    });

    it('creates a booking through the V2 API with the canonical status mapped', function () {
        Http::fake([
            'api.beds24.com/v2/authentication/token' => Http::response(['token' => 'auth-token', 'expiresIn' => 86400]),
            'api.beds24.com/v2/bookings' => Http::response([['success' => true, 'new' => ['id' => 987654]]], 201),
        ]);

        $booking = Booking::create([
            'property_id' => $this->property->id,
            'unit_id' => $this->unit->id,
            'guest_name' => 'Gudule Lapointe',
            'status' => 'option',
            'check_in' => '2027-03-01',
            'check_out' => '2027-03-08',
            'adults' => 2,
            'children' => 1,
            'price' => 980,
            'notes' => 'Arrivée tardive',
            'is_manual' => true,
            'metadata' => ['email' => 'gudule@example.com', 'phone' => '+590690000000'],
        ]);

        $externalId = (new Beds24Connector)->pushBooking($this->unit, ['room_id' => 42], $booking, null);

        expect($externalId)->toBe('987654');

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), '/v2/bookings')) {
                return false;
            }

            $payload = $request->data()[0] ?? [];

            return $request->hasHeader('token', 'auth-token')
                && ! isset($payload['id'])
                && $payload['roomId'] === 42
                && $payload['status'] === 'request'
                && $payload['arrival'] === '2027-03-01'
                && $payload['departure'] === '2027-03-08'
                && $payload['firstName'] === 'Gudule'
                && $payload['lastName'] === 'Lapointe'
                && $payload['numAdult'] === 2
                && $payload['numChild'] === 1
                && $payload['email'] === 'gudule@example.com'
                && $payload['price'] === 980.0
                && $payload['comments'] === 'Arrivée tardive'
                && $payload['refererEditable'] === 'bokit';
        });
    });

    it('updates an existing external booking, cancellation included', function () {
        Http::fake([
            'api.beds24.com/v2/authentication/token' => Http::response(['token' => 'auth-token']),
            'api.beds24.com/v2/bookings' => Http::response([['success' => true, 'modified' => ['id' => 555]]], 201),
        ]);

        $booking = Booking::create([
            'property_id' => $this->property->id,
            'unit_id' => $this->unit->id,
            'guest_name' => 'Gudule Lapointe',
            'status' => 'cancelled',
            'check_in' => '2027-03-01',
            'check_out' => '2027-03-08',
            'is_manual' => true,
        ]);

        $externalId = (new Beds24Connector)->pushBooking($this->unit, ['room_id' => 42], $booking, '555');

        expect($externalId)->toBe('555');

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/v2/bookings')
            && ($request->data()[0]['id'] ?? null) === 555
            && ($request->data()[0]['status'] ?? null) === 'cancelled');
    });

    it('throws when the API rejects the booking', function () {
        Http::fake([
            'api.beds24.com/v2/authentication/token' => Http::response(['token' => 'auth-token']),
            'api.beds24.com/v2/bookings' => Http::response([['success' => false, 'errors' => [['message' => 'roomId invalid']]]], 201),
        ]);

        $booking = Booking::create([
            'property_id' => $this->property->id,
            'unit_id' => $this->unit->id,
            'guest_name' => 'Gudule Lapointe',
            'status' => 'confirmed',
            'check_in' => '2027-03-01',
            'check_out' => '2027-03-08',
            'is_manual' => true,
        ]);

        (new Beds24Connector)->pushBooking($this->unit, ['room_id' => 42], $booking, null);
    })->throws(RuntimeException::class);
});
