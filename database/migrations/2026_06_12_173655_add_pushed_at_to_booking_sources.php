<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the booking's current state was last pushed to this source —
 * bidirectional sync only pushes again when the booking changed since.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_sources', function (Blueprint $table) {
            $table->timestamp('pushed_at')->nullable()->after('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('booking_sources', function (Blueprint $table) {
            $table->dropColumn('pushed_at');
        });
    }
};
