<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Add unit_type to units table if not exists
        if (!Schema::hasColumn("units", "unit_type")) {
            Schema::table("units", function (Blueprint $table) {
                $table->string("unit_type")->nullable()->after("property_id");
                $table->index("unit_type");
            });
        }

        if (!Schema::hasTable("rates")) {
            Schema::create("rates", function (Blueprint $table) {
                $table->id();
                $table->string("name");
                $table->string("slug")->unique();

            // Rate scope - one of these must be set
            $table
                ->foreignId("unit_id")
                ->nullable()
                ->constrained()
                ->onDelete("cascade");
            $table->string("unit_type")->nullable();
            $table
                ->foreignId("property_id")
                ->nullable()
                ->constrained()
                ->onDelete("cascade");

            // Rate configuration
            $table->decimal("base_rate", 10, 2);
            $table
                ->string("calculation_formula")
                ->default("booking_nights * rate");
            $table->boolean("is_active")->default(true);
            $table->string("priority")->default("normal");

            $table->json("settings")->nullable();
            $table->timestamps();

            // Ensure only one scope is set - validation in model instead

            $table->index(["is_active", "priority"]);
        });
        }

        if (!Schema::hasTable("rates_calculations")) {
            Schema::create("rates_calculations", function (Blueprint $table) {
            $table->id();
            $table->foreignId("booking_id")->constrained()->onDelete("cascade");
            $table->decimal("total_amount", 10, 2);
            $table->decimal("base_amount", 10, 2);
            $table->json("calculation_snapshot");
            $table->timestamps();

            $table->index("booking_id");
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists("rates_calculations");
        Schema::dropIfExists("rates");

        Schema::table("units", function (Blueprint $table) {
            $table->dropIndex(["unit_type"]);
            $table->dropColumn("unit_type");
        });
    }
};
