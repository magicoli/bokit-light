<?php

use App\Models\Booking;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

describe('Legacy iCal amounts sanitization', function () {
    uses(RefreshDatabase::class);

    test('sanitizes legacy text amounts left by old iCal imports', function () {
        $property = Property::create(['name' => 'P', 'slug' => 'p', 'is_active' => true]);
        $unit = Unit::create(['property_id' => $property->id, 'name' => 'U', 'slug' => 'u', 'is_active' => true]);

        // Raw insert bypassing Eloquent casts, like the legacy importer did.
        $garbageId = DB::table('bookings')->insertGetId([
            'property_id' => $property->id,
            'unit_id' => $unit->id,
            'guest_name' => 'Christophe Pesquet',
            'status' => 'confirmed',
            'check_in' => '2026-02-07',
            'check_out' => '2026-02-14',
            'price' => '849,60 EUR/254,88 EUR/594,72 EUR',
        ]);
        $cleanId = DB::table('bookings')->insertGetId([
            'property_id' => $property->id,
            'unit_id' => $unit->id,
            'guest_name' => 'Clean Guest',
            'status' => 'confirmed',
            'check_in' => '2026-03-01',
            'check_out' => '2026-03-08',
            'price' => 500,
        ]);

        $migration = require database_path('migrations/2026_06_11_044420_sanitize_legacy_text_amounts.php');
        $migration->up();

        $garbage = Booking::find($garbageId);
        expect((float) $garbage->getRawOriginal('price'))->toBe(849.60)
            ->and($garbage->getMetadata('paid'))->toBe(254.88);

        // Updating the booking no longer crashes on the decimal cast.
        $garbage->update(['notes' => 'touched']);
        expect($garbage->fresh()->notes)->toBe('touched');

        expect((float) Booking::find($cleanId)->getRawOriginal('price'))->toBe(500.0);
    });
});
