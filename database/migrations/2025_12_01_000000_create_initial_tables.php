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
        // Create properties table
        if (!Schema::hasTable("properties")) {
            Schema::create("properties", function (Blueprint $table) {
                $table->id();
                $table->string("name");
                $table->string("address")->nullable();
                $table->string("city")->nullable();
                $table->string("state")->nullable();
                $table->string("zip")->nullable();
                $table->string("country")->nullable();
                $table->string("phone")->nullable();
                $table->string("email")->nullable();
                $table->text("notes")->nullable();
                $table->json("settings")->nullable();
                $table->timestamps();
            });
        }

        // Create units table
        if (!Schema::hasTable("units")) {
            Schema::create("units", function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger("property_id");
                $table->string("name");
                $table->string("slug");
                // $table->integer("capacity");
                // $table->string("color")->nullable();
                $table->string("description")->nullable();
                $table->boolean("is_active")->default(true);
                $table->json("settings")->nullable();
                $table->timestamps();

                $table
                    ->foreign("property_id")
                    ->references("id")
                    ->on("properties");
            });
        }

        // Create users table
        if (!Schema::hasTable("users")) {
            Schema::create("users", function (Blueprint $table) {
                $table->id();
                $table->string("name");
                $table->string("email")->unique();
                $table->timestamp("email_verified_at")->nullable();
                $table->string("password");
                $table->rememberToken();
                $table->timestamps();
            });
        }

        // Create property_user table
        if (!Schema::hasTable("property_user")) {
            Schema::create("property_user", function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger("property_id");
                $table->unsignedBigInteger("user_id");
                $table->string("role")->default("user");
                $table->timestamps();

                $table
                    ->foreign("property_id")
                    ->references("id")
                    ->on("properties");
                $table->foreign("user_id")->references("id")->on("users");
            });
        }

        // Create ical_sources table
        if (!Schema::hasTable("ical_sources")) {
            Schema::create("ical_sources", function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger("unit_id");
                $table->string("type");
                $table->string("url");
                $table->boolean("sync_enabled")->default(true);
                $table->timestamps();

                $table->foreign("unit_id")->references("id")->on("units");
            });
        }

        // Create bookings table
        if (!Schema::hasTable("bookings")) {
            Schema::create("bookings", function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger("unit_id");
                $table->unsignedBigInteger("property_id")->nullable();
                $table->string("uid")->nullable();
                $table->string("source_name")->nullable();
                $table->string("status")->default("undefined");
                $table->string("guest_name");
                $table->date("check_in");
                $table->date("check_out");
                $table->integer("adults")->nullable();
                $table->integer("children")->nullable();
                $table->decimal("price", 10, 2)->nullable();
                $table->decimal("commission", 10, 2)->nullable();
                $table->text("notes")->nullable();
                $table->boolean("is_manual")->default(false);
                $table->unsignedBigInteger("group_id")->nullable();
                $table->json("raw_data")->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign("unit_id")->references("id")->on("units");
            });
        }

        // Create cache table
        if (!Schema::hasTable("cache")) {
            Schema::create("cache", function (Blueprint $table) {
                $table->string("key")->primary();
                $table->text("value");
                $table->integer("expiration");
            });
        }

        // Create cache_locks table
        if (!Schema::hasTable("cache_locks")) {
            Schema::create("cache_locks", function (Blueprint $table) {
                $table->string("key")->primary();
                $table->string("owner");
                $table->integer("expiration");
            });
        }

        // Create sessions table
        if (!Schema::hasTable("sessions")) {
            Schema::create("sessions", function (Blueprint $table) {
                $table->string("id")->primary();
                $table->unsignedBigInteger("user_id")->nullable();
                $table->string("ip_address", 45)->nullable();
                $table->text("user_agent")->nullable();
                $table->text("payload");
                $table->integer("last_activity");
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists("sessions");
        Schema::dropIfExists("cache_locks");
        Schema::dropIfExists("cache");
        Schema::dropIfExists("bookings");
        Schema::dropIfExists("ical_sources");
        Schema::dropIfExists("property_user");
        Schema::dropIfExists("users");
        Schema::dropIfExists("units");
        Schema::dropIfExists("properties");
    }
};
