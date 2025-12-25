<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\RatesController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UpdateController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Auth;
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

// Service Worker (always accessible for PWA)
Route::get("/sw.js", function () {
    return response()
        ->view("sw")
        ->header("Content-Type", "application/javascript")
        ->header("Service-Worker-Allowed", "/");
})->name("sw");

// Check if installation is complete - single source of truth
$isInstalled = Options::get("install.complete", false);

// Only setup app routes if installation is complete
if ($isInstalled) {
    // Determine auth middleware based on options
    $authMethod = Options::get("auth.method", "none");
    $authMiddleware = match ($authMethod) {
        "wordpress" => "auth.wordpress",
        "laravel" => "auth.laravel",
        default => "auth.none",
    };

    // Login/Logout routes
    if ($authMethod === "wordpress") {
        Route::post("/login", function () {
            // Handled by WordPressAuth middleware
            return redirect("/calendar");
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
                "username" => "required|string",
                "password" => "required",
            ]);

            // Permet l'utilisation de username ou email
            $loginField = filter_var(
                $credentials["username"],
                FILTER_VALIDATE_EMAIL,
            )
                ? "email"
                : "name";

            $authCredentials = [
                $loginField => $credentials["username"],
                "password" => $credentials["password"],
            ];

            $remember = $request->boolean("remember");

            if (Auth::attempt($authCredentials, $remember)) {
                $request->session()->regenerate();

                // Set remember me cookie duration: 7 days (10080 minutes)
                // The RenewRememberToken middleware will renew this on each visit
                if ($remember) {
                    $user = Auth::user();
                    $minutes = 10080; // 7 days
                    $recaller = Auth::guard()->getRecallerName();
                    $token = $user->getRememberToken();
                    $value = $user->id . "|" . $token . "|" . $user->password;

                    cookie()->queue(
                        $recaller,
                        encrypt($value),
                        $minutes,
                        config("session.path"),
                        config("session.domain"),
                        config("session.secure"),
                        config("session.http_only", true),
                        false,
                        config("session.same_site"),
                    );
                }

                return redirect()->intended("/calendar");
            }

            return back()->withErrors([
                "username" =>
                    "The provided credentials do not match our records.",
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
        Route::get("/calendar", [CalendarController::class, "index"])->name(
            "calendar",
        );
        Route::get("/booking/{id}", [
            CalendarController::class,
            "booking",
        ])->name("booking.show");

        // Properties list (specific route, must be before catch-all)
        Route::get("/properties", [PropertyController::class, "index"])->name(
            "properties",
        );

        // User settings
        Route::get("/settings", [UserController::class, "settings"])->name(
            "user.settings",
        );

        // Admin settings (TODO: add admin-only middleware)
        Route::get("/admin/settings", [
            AdminController::class,
            "settings",
        ])->name("admin.settings");

        Route::post("/admin/settings", [
            AdminController::class,
            "saveSettings",
        ])->name("admin.settings.save");

        // Rates management
        Route::get("/rates", [RatesController::class, "index"])->name("rates");
        Route::post("/rates", [RatesController::class, "store"])->name(
            "rates.store",
        );
        Route::put("/rates/{rate}", [RatesController::class, "update"])->name(
            "rates.update",
        );
        Route::delete("/rates/{rate}", [
            RatesController::class,
            "destroy",
        ])->name("rates.destroy");

        // Rates calculator
        Route::get("/rates/calculator", [
            RatesController::class,
            "calculator",
        ])->name("rates.calculator");
        Route::post("/rates/calculate", [
            RatesController::class,
            "calculate",
        ])->name("rates.calculate");

        // API for parent rates
        Route::get("/api/parent-rates/{propertyId}", [
            RatesController::class,
            "parentRates",
        ]);

        // Units (edit/update)
        Route::get("/{property:slug}/{unit:slug}/edit", [
            UnitController::class,
            "edit",
        ])->name("units.edit");
        Route::put("/{property:slug}/{unit:slug}", [
            UnitController::class,
            "update",
        ])->name("units.update");
    });

    // Public pages (no auth required - MUST be last as they are catch-all routes)
    Route::get("/{property:slug}/{unit:slug}", [
        UnitController::class,
        "show",
    ])->name("units.show");

    Route::get("/{property:slug}", [PropertyController::class, "show"])->name(
        "property.show",
    );
} else {
    // If not installed, redirect everything to install
    Route::get("/{any}", function () {
        return redirect("/install");
    })->where("any", ".*");
}
