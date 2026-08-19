<?php

use App\Models\Property;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * Runs the migration by hand on a database where it has already run, which is how a data
 * migration is exercised: seed the legacy state, replay it, check the outcome.
 */
function insertLegacySource(array $attributes): void
{
    // `name` is absent from a freshly migrated database — see the schema divergence between
    // create_initial_tables and the guarded migrations that followed it.
    if (! Schema::hasColumn('ical_sources', 'name')) {
        unset($attributes['name']);
    }

    DB::table('ical_sources')->insert($attributes);
}

function runIcalSourcesMigration(): void
{
    $migration = require database_path('migrations/2026_07_28_041500_migrate_ical_sources_to_unit_options.php');
    $migration->up();
}

describe('iCal Sources Migration', function () {
    test('moves a legacy ical source into the unit options', function () {
        $property = Property::create(['name' => 'P', 'slug' => 'p', 'is_active' => true]);
        $unit = Unit::create(['property_id' => $property->id, 'name' => 'Le lit d\'Oli', 'slug' => 'lit-oli', 'is_active' => true]);

        insertLegacySource([
            'unit_id' => $unit->id,
            'name' => 'lelitdoli.com',
            'type' => 'ical',
            'url' => 'https://lelitdoli.com/feed.ics',
            'sync_enabled' => true,
        ]);

        runIcalSourcesMigration();

        expect(DB::table('ical_sources')->count())->toBe(0);

        $sources = $unit->fresh()->options['sources'] ?? [];

        expect($sources)->toHaveCount(1);
        expect($sources[0]['type'])->toBe('ical');
        expect($sources[0]['url'])->toBe('https://lelitdoli.com/feed.ics');
        expect($sources[0]['enabled'])->toBeTrue();
    });

    test('keeps the sources already declared, and does not duplicate one', function () {
        $property = Property::create(['name' => 'P', 'slug' => 'p', 'is_active' => true]);
        $unit = Unit::create([
            'property_id' => $property->id,
            'name' => 'Moon',
            'slug' => 'moon',
            'is_active' => true,
            'options' => ['sources' => [
                ['type' => 'beds24', 'roomId' => '12345', 'enabled' => true],
                ['type' => 'ical', 'url' => 'https://api.beds24.com/feed.ics', 'enabled' => true],
            ]],
        ]);

        insertLegacySource([
            'unit_id' => $unit->id,
            'name' => 'api.beds24.com',
            'type' => 'ical',
            'url' => 'https://api.beds24.com/feed.ics',
            'sync_enabled' => true,
        ]);

        runIcalSourcesMigration();

        $sources = $unit->fresh()->options['sources'] ?? [];

        // The duplicate is dropped, the beds24 source and the source order are untouched.
        expect($sources)->toHaveCount(2);
        expect($sources[0]['type'])->toBe('beds24');
        expect(DB::table('ical_sources')->count())->toBe(0);
    });

    test('drops a beds24 feed the api already covers, and only that one', function () {
        $property = Property::create(['name' => 'P', 'slug' => 'p', 'is_active' => true]);

        $covered = Unit::create([
            'property_id' => $property->id,
            'name' => 'Moon',
            'slug' => 'moon',
            'is_active' => true,
            'options' => ['sources' => [
                ['type' => 'beds24', 'room_id' => 552313, 'enabled' => true],
                ['type' => 'ical', 'url' => 'https://api.beds24.com/ical/bookings.ics?roomid=552313&token=x', 'enabled' => true],
                ['type' => 'ical', 'url' => 'https://www.airbnb.fr/calendar/ical/1.ics', 'enabled' => true],
            ]],
        ]);

        // Same host, another room: an intentional cross-block, not a duplicate.
        $crossBlocking = Unit::create([
            'property_id' => $property->id,
            'name' => 'Sun',
            'slug' => 'sun',
            'is_active' => true,
            'options' => ['sources' => [
                ['type' => 'beds24', 'room_id' => 552316, 'enabled' => true],
                ['type' => 'ical', 'url' => 'https://api.beds24.com/ical/bookings.ics?roomid=999999&token=y', 'enabled' => true],
            ]],
        ]);

        // No API source at all: the feed is this unit's only link to Beds24.
        $feedOnly = Unit::create([
            'property_id' => $property->id,
            'name' => 'Le lit d\'Oli',
            'slug' => 'lit-oli-2',
            'is_active' => true,
            'options' => ['sources' => [
                ['type' => 'ical', 'url' => 'https://api.beds24.com/ical/bookings.ics?roomid=535462&token=z', 'enabled' => true],
            ]],
        ]);

        runIcalSourcesMigration();

        $kept = collect($covered->fresh()->options['sources']);
        expect($kept)->toHaveCount(2);
        expect($kept->pluck('type')->all())->toBe(['beds24', 'ical']);
        expect($kept->last()['url'])->toContain('airbnb');

        expect($crossBlocking->fresh()->options['sources'])->toHaveCount(2);
        expect($feedOnly->fresh()->options['sources'])->toHaveCount(1);
    });
});
