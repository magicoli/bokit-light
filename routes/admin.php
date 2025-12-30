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

        // Resource routes - auto-registered by models with AdminResourceTrait
        \App\Models\Booking::registerAdminRoutes();
    });
