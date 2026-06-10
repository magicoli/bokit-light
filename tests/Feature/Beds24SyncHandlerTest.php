<?php

use App\Contracts\SyncHandler;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Beds24\Services\Beds24SyncHandler;

uses(RefreshDatabase::class);

describe('Beds24SyncHandler', function () {
    it('implements SyncHandler', function () {
        expect(new Beds24SyncHandler)->toBeInstanceOf(SyncHandler::class);
    });

    it('has source type beds24', function () {
        expect((new Beds24SyncHandler)->sourceType())->toBe('beds24');
    });

    it('has the correct label', function () {
        expect((new Beds24SyncHandler)->label())->toBe('Beds24 API');
    });

    it('returns a failure result when no room_id is configured', function () {
        $property = Property::create(['name' => 'B24P', 'slug' => 'b24-p', 'is_active' => true]);
        $unit = Unit::create(['property_id' => $property->id, 'name' => 'B24U', 'slug' => 'b24-u', 'is_active' => true]);
        $unit->setRelation('property', $property);

        $result = (new Beds24SyncHandler)->syncSource($unit, ['type' => 'beds24', 'enabled' => true]);

        expect($result['success'])->toBeFalse()
            ->and($result['error'])->toContain('room_id');
    });
});
