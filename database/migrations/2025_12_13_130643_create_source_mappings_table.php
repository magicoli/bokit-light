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
        // Step 1: Add property_id to bookings table (useful for direct queries)
        // Only add if column doesn't exist
        if (!Schema::hasColumn("bookings", "property_id")) {
            Schema::table("bookings", function (Blueprint $table) {
                $table
                    ->unsignedBigInteger("property_id")
                    ->nullable()
                    ->after("unit_id");
                $table->index(["property_id"]);
            });
        }

        // Step 2: Create source_mappings table for mapping multiple sources to bookings
        if (!Schema::hasTable("source_mappings")) {
            Schema::create("source_mappings", function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("booking_id");
            $table->string("control_string"); // Control string for source event matching
            $table->timestamps();

            // Foreign key constraint
            $table
                ->foreign("booking_id")
                ->references("id")
                ->on("bookings")
                ->onDelete("cascade");

            // Unique index to prevent duplicate mappings
            $table->unique(["control_string"]);

            // Index for fast lookups
            $table->index(["booking_id"]);
            $table->index(["control_string"]);
        });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists("source_mappings");

        Schema::table("bookings", function (Blueprint $table) {
            $table->dropColumn("property_id");
        });
    }
};
