<?php

use App\Contracts\SyncHandler;
use App\Models\Property;
use App\Models\Unit;
use App\Services\BookingSyncIcal;
use App\Services\IcalSyncHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('IcalSyncHandler', function () {
    it('implements SyncHandler', function () {
        expect(new IcalSyncHandler(mock(BookingSyncIcal::class)))->toBeInstanceOf(SyncHandler::class);
    });

    it('has source type ical', function () {
        expect((new IcalSyncHandler(mock(BookingSyncIcal::class)))->sourceType())->toBe('ical');
    });

    it('has the correct label', function () {
        expect((new IcalSyncHandler(mock(BookingSyncIcal::class)))->label())->toBe('iCal');
    });

    it('returns a failure result when no URL is configured', function () {
        $property = Property::create(['name' => 'IcalP', 'slug' => 'ical-p', 'is_active' => true]);
        $unit = Unit::create(['property_id' => $property->id, 'name' => 'IcalU', 'slug' => 'ical-u', 'is_active' => true]);
        $unit->setRelation('property', $property);

        $parser = mock(BookingSyncIcal::class);
        $parser->shouldNotReceive('syncSource');

        $result = (new IcalSyncHandler($parser))->syncSource($unit, ['type' => 'ical', 'url' => '', 'enabled' => true]);

        expect($result['success'])->toBeFalse()
            ->and($result['error'])->not->toBeEmpty();
    });
});
