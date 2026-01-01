<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Backfill property_id in bookings from their unit's property_id
        // This is for performance - avoids expensive joins on large table
        DB::statement('
            UPDATE bookings 
            SET property_id = (
                SELECT units.property_id 
                FROM units 
                WHERE units.id = bookings.unit_id
            )
            WHERE property_id IS NULL 
            AND unit_id IS NOT NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to reverse - property_id is still valid data
    }
};
