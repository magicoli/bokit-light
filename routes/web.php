<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\UpdateController;
use App\Support\Options;
use Illuminate\Support\Facades\Route;

// Installation routes (always accessible during installation)
Route::get("/install", [InstallController::class, "index"])->name("install");
Route::post("/install", [InstallController::class, "process"])->name(
    "install.process",
);
Route::post("/install/complete", [InstallController::class, "complete"])->name(
    "install.complete",
);

// Update routes (always accessible when installed)
Route::get("/update", [UpdateController::class, "index"])->name("update");
Route::post("/update/execute", [UpdateController::class, "execute"])->name(
    "update.execute",
);

// Check if installation is complete - single source of truth
$isInstalled = Options::get("install.complete", false);

// Only setup app routes if installation is complete
if ($isInstalled) {
    // Determine auth middleware based on options
    $authMethod = Options::get("auth.method", "none");
    $authMiddleware =
        $authMethod === "wordpress" ? "auth.wordpress" : "auth.none";

    // Login/Logout routes (only for WordPress auth)
    if ($authMethod === "wordpress") {
        Route::post("/login", function () {
            // Handled by WordPressAuth middleware
            return redirect("/");
        })->middleware($authMiddleware);

        Route::get("/logout", function () {
            session()->forget("wp_user");
            return redirect("/");
        })->name("logout");
    }

    // App routes (protected by auth)
    Route::middleware([$authMiddleware])->group(function () {
        Route::get("/", [DashboardController::class, "index"])->name(
            "dashboard",
        );
        Route::get("/booking/{id}", [
            DashboardController::class,
            "booking",
        ])->name("booking.show");
    });
} else {
    // If not installed, redirect everything to install
    Route::get("/{any}", function () {
        return redirect("/install");
    })->where("any", ".*");
}
