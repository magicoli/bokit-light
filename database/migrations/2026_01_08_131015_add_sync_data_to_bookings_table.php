<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Add sync_data to replace raw_data
            $table->json('sync_data')->nullable()->after('raw_data');
            
            // Remove old raw_data column
            $table->dropColumn('raw_data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Restore raw_data only if it doesn't exist
            if (!Schema::hasColumn('bookings', 'raw_data')) {
                $table->json('raw_data')->nullable()->after('group_id');
            }
            
            // Remove sync_data
            $table->dropColumn('sync_data');
        });
    }
};
