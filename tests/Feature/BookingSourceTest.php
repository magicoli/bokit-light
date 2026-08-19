<?php

use App\Models\Booking;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('BookingSource display label', function () {
    beforeEach(function () {
        $property = Property::create(['name' => 'BsP', 'slug' => 'bs-p', 'is_active' => true]);
        $unit = Unit::create(['property_id' => $property->id, 'name' => 'BsU', 'slug' => 'bs-u', 'is_active' => true]);
        $this->booking = Booking::create([
            'unit_id' => $unit->id,
            'property_id' => $property->id,
            'uid' => 'bs-1',
            'check_in' => '2027-01-01',
            'check_out' => '2027-01-05',
            'guest_name' => 'Guest Test',
            'status' => 'confirmed',
        ]);
    });

    test('shows the connector label with a check mark for the origin', function () {
        $source = $this->booking->sources()->create([
            'source_type' => 'beds24',
            'source_key' => 'beds24',
            'external_id' => '123',
            'is_origin' => true,
        ]);

        expect($source->display_label)->toBe('✓ Beds24 API');
    });

    test('appends the feed name for keyed sources without a check when not origin', function () {
        $source = $this->booking->sources()->create([
            'source_type' => 'ical',
            'source_key' => 'ical:beds24.com',
            'external_id' => 'uid-x@beds24.com',
            'is_origin' => false,
        ]);

        expect($source->display_label)->toBe('iCal beds24.com');
    });

    test('marks placeholder origins as not connected', function () {
        $source = $this->booking->sources()->create([
            'source_type' => 'ical',
            'source_key' => 'ical',
            'external_id' => 'uid-y@gites-mosaiques.com',
            'is_origin' => true,
            'is_placeholder' => true,
        ]);

        expect($source->display_label)->toBe('✓ iCal ('.__('booking.source.not_connected').')');
    });

    test('exposes the Beds24 edit page URL for API sources', function () {
        $source = $this->booking->sources()->create([
            'source_type' => 'beds24',
            'source_key' => 'beds24',
            'external_id' => '66036992',
            'is_origin' => true,
        ]);

        expect($source->external_url)->toBe('https://beds24.com/control2.php?ajax=bookedit&id=66036992');
    });

    test('has no external URL for iCal sources', function () {
        $source = $this->booking->sources()->create([
            'source_type' => 'ical',
            'source_key' => 'ical:beds24.com',
            'external_id' => 'uid-x@beds24.com',
            'is_origin' => false,
        ]);

        expect($source->external_url)->toBeNull();
    });

    test('falls back to the raw type when no connector is registered', function () {
        $source = $this->booking->sources()->create([
            'source_type' => 'unknown-source',
            'source_key' => 'unknown-source',
            'external_id' => '42',
            'is_origin' => false,
        ]);

        expect($source->display_label)->toBe('unknown-source');
    });
});
