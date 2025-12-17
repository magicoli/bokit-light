<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\UpdateController;
use App\Http\Controllers\UserController;
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
    $authMiddleware = match($authMethod) {
        "wordpress" => "auth.wordpress",
        "laravel" => "auth.laravel",
        default => "auth.none"
    };

    // Login/Logout routes
    if ($authMethod === "wordpress") {
        Route::post("/login", function () {
            // Handled by WordPressAuth middleware
            return redirect("/dashboard");
        })->middleware($authMiddleware);

        Route::get("/logout", function () {
            session()->forget("wp_user");
            return redirect("/");
        })->name("logout");
    } elseif ($authMethod === "laravel") {
        Route::get("/login", function () {
            return view("auth.login");
        })->name("login");

        Route::post("/login", function (\Illuminate\Http\Request $request) {
            $credentials = $request->validate([
                'username' => 'required|string',
                'password' => 'required',
            ]);

            // Permet l'utilisation de username ou email
            $loginField = filter_var($credentials['username'], FILTER_VALIDATE_EMAIL) 
                ? 'email' 
                : 'name';

            $authCredentials = [
                $loginField => $credentials['username'],
                'password' => $credentials['password'],
            ];

            if (Auth::attempt($authCredentials)) {
                $request->session()->regenerate();
                return redirect()->intended('/dashboard');
            }

            return back()->withErrors([
                'username' => 'The provided credentials do not match our records.',
            ]);
        });

        Route::post("/logout", function () {
            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();
            return redirect("/");
        })->name("logout");
    }

    // Home / About page (public, no auth required)
    Route::get("/", [AboutController::class, "index"])->name("home");
    Route::get("/about", [AboutController::class, "index"])->name("about");

    // Locale switcher (public)
    Route::get("/locale/{locale}", [LocaleController::class, "change"])->name(
        "locale.change",
    );

    // App routes (protected by auth)
    Route::middleware([$authMiddleware])->group(function () {
        Route::get("/dashboard", [DashboardController::class, "index"])->name(
            "dashboard",
        );
        Route::get("/booking/{id}", [
            DashboardController::class,
            "booking",
        ])->name("booking.show");
        
        // Properties
        Route::get("/properties", [PropertyController::class, "index"])->name(
            "properties.index",
        );
        
        // User settings
        Route::get("/settings", [UserController::class, "settings"])->name(
            "user.settings",
        );
        
        // Admin settings (TODO: add admin-only middleware)
        Route::get("/admin/settings", [AdminController::class, "settings"])->name(
            "admin.settings",
        );
    });
} else {
    // If not installed, redirect everything to install
    Route::get("/{any}", function () {
        return redirect("/install");
    })->where("any", ".*");
}
