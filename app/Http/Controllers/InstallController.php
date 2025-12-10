<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class InstallController extends Controller
{
    public function index()
    {
        // If already installed, redirect to dashboard
        if ($this->isInstalled()) {
            return redirect("/");
        }

        return view("install.index");
    }

    public function install(Request $request)
    {
        try {
            // 1. Create storage structure
            $this->createStorageStructure();

            // 2. Create database file and tables
            DB::beginTransaction();
            $this->createTables();
            DB::commit();

            // 3. Run any pending migrations (future proofing)
            Artisan::call("migrate", ["--force" => true]);

            // 4. Import config if provided
            $configPath = storage_path("config/properties.json");
            if (file_exists($configPath)) {
                Artisan::call("bokit:import-config");
            }

            return response()->json([
                "success" => true,
                "message" => "Installation completed successfully!",
                "redirect" => url("/"),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(
                [
                    "success" => false,
                    "message" => "Installation failed: " . $e->getMessage(),
                ],
                500,
            );
        }
    }

    private function createStorageStructure()
    {
        // Create all necessary storage directories
        $directories = [
            "app/public",
            "framework/cache/data",
            "framework/sessions",
            "framework/testing",
            "framework/views",
            "logs",
            "database/default",
            "config",
        ];

        foreach ($directories as $dir) {
            $path = storage_path($dir);
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }

        // Create empty SQLite database file
        $dbFile = storage_path("database/default/database.sqlite");
        if (!file_exists($dbFile)) {
            touch($dbFile);
            chmod($dbFile, 0644);
        }
    }

    private function createTables()
    {
        // Create properties table
        if (!Schema::hasTable("properties")) {
            Schema::create("properties", function ($table) {
                $table->id();
                $table->string("name");
                $table->string("slug")->unique();
                $table->string("color")->default("#3B82F6");
                $table->integer("capacity")->nullable();
                $table->boolean("is_active")->default(true);
                $table->json("settings")->nullable();
                $table->timestamps();
            });
        }

        // Create ical_sources table
        if (!Schema::hasTable("ical_sources")) {
            Schema::create("ical_sources", function ($table) {
                $table->id();
                $table
                    ->foreignId("property_id")
                    ->constrained()
                    ->onDelete("cascade");
                $table->string("name");
                $table->text("url");
                $table->boolean("sync_enabled")->default(true);
                $table->timestamp("last_synced_at")->nullable();
                $table->string("last_sync_status")->nullable();
                $table->text("last_sync_error")->nullable();
                $table->timestamps();
            });
        }

        // Create bookings table
        if (!Schema::hasTable("bookings")) {
            Schema::create("bookings", function ($table) {
                $table->id();
                $table
                    ->foreignId("property_id")
                    ->constrained()
                    ->onDelete("cascade");
                $table->string("uid")->index();
                $table->string("source_name");
                $table->string("guest_name");
                $table->date("check_in");
                $table->date("check_out");
                $table->integer("adults")->nullable();
                $table->integer("children")->nullable();
                $table->decimal("price", 10, 2)->nullable();
                $table->decimal("commission", 10, 2)->nullable();
                $table->text("notes")->nullable();
                $table->boolean("is_manual")->default(false);
                $table->string("group_id")->nullable();
                $table->json("raw_data")->nullable();
                $table->timestamps();
                $table->timestamp("deleted_at")->nullable(); // Soft deletes

                $table->unique(["uid", "property_id"]);
            });
        }

        // Create cache table (for AutoSync)
        if (!Schema::hasTable("cache")) {
            Schema::create("cache", function ($table) {
                $table->string("key")->primary();
                $table->text("value");
                $table->integer("expiration");
            });
        }

        // Create cache_locks table
        if (!Schema::hasTable("cache_locks")) {
            Schema::create("cache_locks", function ($table) {
                $table->string("key")->primary();
                $table->string("owner");
                $table->integer("expiration");
            });
        }

        // Create sessions table
        if (!Schema::hasTable("sessions")) {
            Schema::create("sessions", function ($table) {
                $table->string("id")->primary();
                $table->foreignId("user_id")->nullable()->index();
                $table->string("ip_address", 45)->nullable();
                $table->text("user_agent")->nullable();
                $table->longText("payload");
                $table->integer("last_activity")->index();
            });
        }
    }

    private function isInstalled()
    {
        try {
            // Check if properties table exists with data
            return Schema::hasTable("properties");
        } catch (\Exception $e) {
            return false;
        }
    }
}
