<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn("bookings", "status")) {
            Schema::table("bookings", function (Blueprint $table) {
                $table
                    ->string("status")
                    ->default("confirmed")
                    ->after("source_name");
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table("bookings", function (Blueprint $table) {
            $table->dropColumn("status");
        });
    }
};
