<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Support\Options;
use App\Models\User;
use App\Models\Property;
use App\Models\Unit;
use App\Models\IcalSource;

class InstallController extends Controller
{
    private array $steps = [
        1 => [
            "name" => "welcome",
            "title" => "Welcome",
            "view" => "welcome",
        ],
        2 => [
            "name" => "auth",
            "title" => "Authentication",
            "view" => "auth",
        ],
        3 => [
            "name" => "admin",
            "title" => "First Administrator",
            "view" => "admin",
        ],
        4 => [
            "name" => "setup",
            "title" => "Configure Properties & Units",
            "view" => "setup",
        ],
        5 => [
            "name" => "complete",
            "title" => "Installation Complete",
            "view" => "complete",
            "no_process" => true,
        ],
    ];

    /**
     * Display the current installation step
     */
    public function index()
    {
        // If installation is already complete, redirect to dashboard
        if (Options::get("install.complete", false)) {
            return redirect("/");
        }

        // Get current step from session (default to 1)
        $currentStepNumber = Session::get("install_step", 1);
        $step = $this->steps[$currentStepNumber] ?? null;

        if (!$step) {
            return redirect("/");
        }

        // If we're on the complete step, clear the session
        if ($step["name"] === "complete") {
            Session::forget("install_step");
        }

        return view("install.index", [
            "step" => $step,
            "stepNumber" => $currentStepNumber,
            "totalSteps" => count($this->steps),
        ]);
    }

    /**
     * Process the submitted step
     */
    public function process(Request $request)
    {
        $currentStepNumber = Session::get("install_step", 1);
        $step = $this->steps[$currentStepNumber] ?? null;

        if (!$step) {
            return response()->json(
                ["success" => false, "message" => "Invalid step"],
                400,
            );
        }

        // Skip processing if it's a display-only step
        if (!empty($step["no_process"])) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "This step cannot be processed",
                ],
                400,
            );
        }

        try {
            // Call the appropriate processing method
            $methodName = "process" . ucfirst($step["name"]);

            if (!method_exists($this, $methodName)) {
                throw new \Exception(
                    "Processing method {$methodName} not found",
                );
            }

            $result = $this->$methodName($request);

            // If method returned false, it handled the transition itself
            if ($result === false) {
                return response()->json([
                    "success" => true,
                    "next_step" => Session::get("install_step"),
                ]);
            }

            // Move to next step
            $nextStep = $currentStepNumber + 1;

            if (isset($this->steps[$nextStep])) {
                Session::put("install_step", $nextStep);
                return response()->json([
                    "success" => true,
                    "next_step" => $nextStep,
                ]);
            } else {
                // Should not happen with proper step configuration
                Session::forget("install_step");
                return response()->json([
                    "success" => true,
                    "complete" => true,
                    "redirect" => url("/"),
                ]);
            }
        } catch (\Exception $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Process step 1: Welcome (create database structure)
     */
    private function processWelcome(Request $request)
    {
        // Create storage structure
        $this->createStorageStructure();

        // Create database tables
        DB::beginTransaction();
        $this->createTables();
        DB::commit();

        // Run any pending migrations
        Artisan::call("migrate", ["--force" => true]);

        return true;
    }

    /**
     * Process step 2: Authentication configuration
     */
    private function processAuth(Request $request)
    {
        $authMethod = $request->input("auth_method", "none");

        // Save auth method
        Options::set("auth.method", $authMethod);

        if ($authMethod === "wordpress") {
            // Save WordPress-specific settings
            $request->validate([
                "wp_site_url" => "required|url",
                "wp_required_role" => "required|string",
            ]);

            // Verify WordPress site is accessible and has Bokit plugin
            $wpUrl = $request->input("wp_site_url");
            try {
                // Check if the Bokit API endpoint exists
                $response = Http::timeout(5)->get(
                    $wpUrl . "/wp-json/bokit/v1/status",
                );
                if (!$response->successful()) {
                    throw new \Exception(
                        "The Bokit WordPress plugin does not appear to be installed or active on this site. Please install and activate the plugin first.",
                    );
                }
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                throw new \Exception(
                    "Could not connect to the website. Please check the URL and ensure the site is online.",
                );
            } catch (\Exception $e) {
                if (str_contains($e->getMessage(), "Bokit WordPress plugin")) {
                    throw $e;
                }
                throw new \Exception(
                    "Unable to verify the WordPress site. Please ensure the site is accessible and the Bokit plugin is installed.",
                );
            }

            Options::set("auth.wordpress.site_url", $wpUrl);
            Options::set(
                "auth.wordpress.required_role",
                $request->input("wp_required_role"),
            );

            // Next step will be admin login (step 3)
            return true;
        } else {
            // No authentication = no admin user needed
            // Skip step 3 (admin login) - jump directly to step 4 (setup)
            Session::put("install_step", 4);
            session()->save(); // Force session save

            // Return false to signal we've handled the step transition ourselves
            return false;
        }
    }

    /**
     * Process step 3: Create first admin user (WordPress only)
     */
    private function processAdmin(Request $request)
    {
        $authMethod = Options::get("auth.method");

        if ($authMethod !== "wordpress") {
            throw new \Exception(
                "This step is only for WordPress authentication",
            );
        }

        // WordPress authentication
        $request->validate([
            "username" => "required|string",
            "password" => "required|string",
        ]);

        $wpUrl = Options::get("auth.wordpress.site_url");
        $requiredRole = Options::get(
            "auth.wordpress.required_role",
            "administrator",
        );

        // Authenticate via WordPress
        $response = Http::post($wpUrl . "/wp-json/bokit/v1/auth", [
            "username" => $request->input("username"),
            "password" => $request->input("password"),
        ]);

        if (!$response->successful()) {
            $responseJson = json_decode($response->body(), true);
            $errorMessage = trim(
                strip_tags(
                    $responseJson["message"] ?? __("Authentication failed."),
                ),
            );
            throw new \Exception($errorMessage);
        }

        $wpUser = $response->json();

        // Check if user has required role
        if (
            !in_array($requiredRole, $wpUser["roles"] ?? []) &&
            !in_array("administrator", $wpUser["roles"] ?? [])
        ) {
            throw new \Exception(
                "Access denied. Your WordPress account does not have the required role to become administrator.",
            );
        }

        // Create admin user
        $user = User::create([
            "name" => $wpUser["name"],
            "email" => $wpUser["email"] ?? "",
            "auth_provider" => "wordpress",
            "auth_provider_id" => $wpUser["id"],
            "is_admin" => true,
        ]);

        // Store in session
        Session::put("wp_user", [
            "id" => $wpUser["id"],
            "name" => $wpUser["name"],
            "email" => $wpUser["email"] ?? "",
            "roles" => $wpUser["roles"],
        ]);

        Session::put("user_id", $user->id);

        return true;
    }

    /**
     * Process step 4: Setup properties, units and iCal sources
     */
    private function processSetup(Request $request)
    {
        $request->validate([
            "properties" => "required|array|min:1",
            "properties.*.name" => "required|string|max:255",
            "properties.*.slug" => "nullable|string|max:255",
            "properties.*.url" => "nullable|url|max:500",
            "properties.*.units" => "required|array|min:1",
            "properties.*.units.*.name" => "required|string|max:255",
            "properties.*.units.*.slug" => "nullable|string|max:255",
            "properties.*.units.*.ical_sources" => "required|array|min:1",
            "properties.*.units.*.ical_sources.*.type" =>
                "required|string|in:ical",
            "properties.*.units.*.ical_sources.*.url" => "required|url",
        ]);

        $properties = $request->input("properties");

        DB::beginTransaction();

        try {
            foreach ($properties as $propertyData) {
                $propertyName = $propertyData["name"];
                $propertySlug = !empty($propertyData["slug"])
                    ? Str::slug($propertyData["slug"])
                    : Str::slug($propertyName);

                // Check property slug uniqueness
                if (Property::where("slug", $propertySlug)->exists()) {
                    throw new \Exception(
                        "The property slug '{$propertySlug}' is already used. Please choose a different one.",
                    );
                }

                // Create property
                $property = Property::create([
                    "name" => $propertyName,
                    "slug" => $propertySlug,
                    "settings" => [
                        "url" => $propertyData["url"] ?? null,
                    ],
                ]);

                // Process units for this property
                $unitSlugs = [];
                foreach ($propertyData["units"] as $unitData) {
                    $unitName = $unitData["name"];
                    $unitSlug = !empty($unitData["slug"])
                        ? Str::slug($unitData["slug"])
                        : Str::slug($unitName);

                    // Check unit slug uniqueness within this property
                    if (in_array($unitSlug, $unitSlugs)) {
                        throw new \Exception(
                            "Duplicate unit slug '{$unitSlug}' in property '{$propertyName}'. Each unit must have a unique slug within its property.",
                        );
                    }

                    if (
                        Unit::where("property_id", $property->id)
                            ->where("slug", $unitSlug)
                            ->exists()
                    ) {
                        throw new \Exception(
                            "The unit slug '{$unitSlug}' is already used in property '{$propertyName}'.",
                        );
                    }

                    $unitSlugs[] = $unitSlug;

                    // Create unit
                    $unit = Unit::create([
                        "property_id" => $property->id,
                        "name" => $unitName,
                        "slug" => $unitSlug,
                        "is_active" => true,
                    ]);

                    // Create iCal sources for this unit
                    foreach ($unitData["ical_sources"] as $sourceData) {
                        // Generate source name from URL hostname
                        $sourceName =
                            parse_url($sourceData["url"], PHP_URL_HOST) ??
                            "External Calendar";

                        IcalSource::create([
                            "unit_id" => $unit->id,
                            "name" => $sourceName,
                            "url" => $sourceData["url"],
                            "sync_enabled" => true,
                        ]);
                    }
                }
            }

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Mark installation as complete and redirect to dashboard
     */
    public function complete(Request $request)
    {
        // Mark installation as complete
        Options::set("install.complete", true);
        
        // Set default sync interval (1 hour = 3600 seconds)
        Options::set("sync.interval", 3600);

        return response()->json([
            "success" => true,
            "redirect" => url("/"),
        ]);
    }

    private function createStorageStructure()
    {
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

        $dbFile = storage_path("database/default/database.sqlite");
        if (!file_exists($dbFile)) {
            touch($dbFile);
            chmod($dbFile, 0644);
        }
    }

    private function createTables()
    {
        // Create properties table (organizations/clients)
        if (!Schema::hasTable("properties")) {
            Schema::create("properties", function ($table) {
                $table->id();
                $table->string("name");
                $table->string("slug")->unique();
                $table->json("settings")->nullable();
                $table->timestamps();
            });
        }

        // Create units table (rental units)
        if (!Schema::hasTable("units")) {
            Schema::create("units", function ($table) {
                $table->id();
                $table
                    ->foreignId("property_id")
                    ->constrained()
                    ->onDelete("cascade");
                $table->string("name");
                $table->string("slug");
                $table->string("color")->default("#3B82F6");
                $table->integer("capacity")->nullable();
                $table->boolean("is_active")->default(true);
                $table->json("settings")->nullable();
                $table->timestamps();

                $table->unique(["property_id", "slug"]);
            });
        }

        // Create users table
        if (!Schema::hasTable("users")) {
            Schema::create("users", function ($table) {
                $table->id();
                $table->string("name");
                $table->string("email")->unique();
                $table->string("auth_provider");
                $table->string("auth_provider_id")->nullable();
                $table->boolean("is_admin")->default(false);
                $table->timestamps();
            });
        }

        // Create property_user pivot table
        if (!Schema::hasTable("property_user")) {
            Schema::create("property_user", function ($table) {
                $table->id();
                $table
                    ->foreignId("property_id")
                    ->constrained()
                    ->onDelete("cascade");
                $table
                    ->foreignId("user_id")
                    ->constrained()
                    ->onDelete("cascade");
                $table->string("role")->default("manager");
                $table->timestamps();

                $table->unique(["property_id", "user_id"]);
            });
        }

        // Create ical_sources table
        if (!Schema::hasTable("ical_sources")) {
            Schema::create("ical_sources", function ($table) {
                $table->id();
                $table
                    ->foreignId("unit_id")
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
                    ->foreignId("unit_id")
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
                $table->timestamp("deleted_at")->nullable();

                $table->unique(["uid", "unit_id"]);
            });
        }

        // Create cache table
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
        
        // Note: We don't need 'jobs' or 'failed_jobs' tables because we use
        // dispatchAfterResponse() which executes jobs synchronously after the
        // HTTP response is sent, without using a database queue.
    }
}
