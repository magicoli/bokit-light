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
        // 2 => [
        //     "name" => "auth",
        //     "title" => "Authentication",
        //     "view" => "auth",
        // ],
        // 3 => [
        //     "name" => "admin",
        //     "title" => "First Administrator",
        //     "view" => "admin",
        // ],
        2 => [
            "name" => "setup",
            "title" => "Configure Properties & Units",
            "view" => "setup",
        ],
        3 => [
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
        // If installation is already complete, redirect to calendar
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

        return view("install", [
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
        // $authMethod = $request->input("auth_method", "none");

        // Save auth method
        $authMethod = "none";
        Options::set("auth.method", $authMethod);
        return true;
        // if ($authMethod === "wordpress") {
        //     // Save WordPress-specific settings
        //     $request->validate([
        //         "wp_site_url" => "required|url",
        //         "wp_required_role" => "required|string",
        //     ]);

        //     // Verify WordPress site is accessible and has Bokit plugin
        //     $wpUrl = $request->input("wp_site_url");
        //     try {
        //         // Check if the Bokit API endpoint exists
        //         $response = Http::timeout(5)->get(
        //             $wpUrl . "/wp-json/bokit/v1/status",
        //         );
        //         if (!$response->successful()) {
        //             throw new \Exception(
        //                 "The Bokit WordPress plugin does not appear to be installed or active on this site. Please install and activate the plugin first.",
        //             );
        //         }
        //     } catch (\Illuminate\Http\Client\ConnectionException $e) {
        //         throw new \Exception(
        //             "Could not connect to the website. Please check the URL and ensure the site is online.",
        //         );
        //     } catch (\Exception $e) {
        //         if (str_contains($e->getMessage(), "Bokit WordPress plugin")) {
        //             throw $e;
        //         }
        //         throw new \Exception(
        //             "Unable to verify the WordPress site. Please ensure the site is accessible and the Bokit plugin is installed.",
        //         );
        //     }

        //     Options::set("auth.wordpress.site_url", $wpUrl);
        //     Options::set(
        //         "auth.wordpress.required_role",
        //         $request->input("wp_required_role"),
        //     );

        //     // Next step will be admin login (step 3)
        //     return true;
        // } else {
        //     // No authentication = no admin user needed
        //     // Skip step 3 (admin login) - jump directly to step 4 (setup)
        //     Session::put("install_step", 4);
        //     session()->save(); // Force session save

        //     // Return false to signal we've handled the step transition ourselves
        //     return false;
        // }
    }

    /**
     * Process step 3: Create first admin user (WordPress only)
     */
    private function processAdmin(Request $request)
    {
        $authMethod = Options::get("auth.method");

        // if ($authMethod !== "wordpress") {
        //     throw new \Exception(
        //         "This step is only for WordPress authentication",
        //     );
        // }

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
     * Mark installation as complete and redirect to calendar
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

    /**
     * Create storage structure (now handled by AppServiceProvider)
     * @deprecated This method is no longer needed as storage structure is created by AppServiceProvider
     */
    private function createStorageStructure()
    {
        // Storage structure is now created by AppServiceProvider
        // This method is kept for backward compatibility but does nothing
    }

    /**
     * Create database tables (now handled by migrations)
     * @deprecated This method is no longer needed as tables are created by migrations
     */
    private function createTables()
    {
        // Tables are now created by migrations
        // This method is kept for backward compatibility but does nothing
    }
}
