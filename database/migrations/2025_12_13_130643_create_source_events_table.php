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
        Schema::table("bookings", function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'property_id')) {
                $table
                    ->unsignedBigInteger("property_id")
                    ->nullable()
                    ->after("unit_id");
                $table->index(["property_id"]);
            }
        });

        // Step 2: Create source_events table for mapping multiple sources to bookings
        if (!Schema::hasTable("source_events")) {
            Schema::create("source_events", function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("booking_id");
            $table->string("source_type", 20); // ical, api, manual, etc.
            $table->unsignedBigInteger("source_id"); // ID of the external source
            $table->string("source_event_id"); // Event ID from the external source
            $table->unsignedBigInteger("property_id"); // Property ID
            $table->timestamps();

            // Foreign key constraint
            $table
                ->foreign("booking_id")
                ->references("id")
                ->on("bookings")
                ->onDelete("cascade");

            // Composite unique index for fast lookups and to prevent duplicates
            $table->unique([
                "source_type",
                "source_id",
                "source_event_id",
                "property_id",
            ]);

            // Individual indexes for flexible querying
            $table->index(["booking_id"]);
            $table->index(["source_type", "source_id"]);
            $table->index(["property_id", "source_type"]);
        });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists("source_events");

        Schema::table("bookings", function (Blueprint $table) {
            $table->dropColumn("property_id");
        });
    }
};
