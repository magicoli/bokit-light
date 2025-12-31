<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by bootstrap/app.php with web middleware.
|
*/

Route::middleware(["web", "admin"])
    ->prefix("admin")
    ->name("admin.")
    ->group(function () {
        // Dashboard
        Route::get("/", function () {
            return view("admin.dashboard");
        })->name("dashboard");

        // General settings
        Route::get("/settings", function () {
            return view("admin.settings");
        })->name("settings");

        Route::post("/settings", [
            \App\Http\Controllers\AdminController::class,
            "saveSettings",
        ])->name("settings.save");

        // Auto-discover and register routes for all models with AdminResourceTrait
        // TODO: Migrate to AdminRegistry::discoverModels() + AdminRegistry::registerRoutes()
        // This is a temporary implementation - see AdminRegistry for future architecture
        $modelsPath = app_path("Models");
        if (is_dir($modelsPath)) {
            $files = \Illuminate\Support\Facades\File::files($modelsPath);
            foreach ($files as $file) {
                $className = "App\\Models\\" . $file->getFilenameWithoutExtension();
                if (class_exists($className)) {
                    $uses = class_uses_recursive($className);
                    if (in_array("App\\Traits\\AdminResourceTrait", $uses)) {
                        $className::registerAdminRoutes();
                    }
                }
            }
        }
        
        // Future implementation (when AdminRegistry is fully active):
        // \App\Services\AdminRegistry::discoverModels();
        // \App\Services\AdminRegistry::registerRoutes();
    });
