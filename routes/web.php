<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\UpdateController;
use App\Http\Controllers\WelcomeController;
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
            return redirect("/");
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
                return redirect()->intended('/');
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

    // Welcome page (public, no auth required)
    Route::get("/welcome", [WelcomeController::class, "index"])->name(
        "welcome",
    );

    // Root route - redirects based on authentication
    Route::get("/", function () {
        return auth()->check() 
            ? redirect()->route("dashboard")
            : redirect()->route("welcome");
    })->name("root");

    // App routes (protected by auth)
    Route::middleware([$authMiddleware])->group(function () {
        Route::get("/dashboard", [DashboardController::class, "index"])->name(
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
