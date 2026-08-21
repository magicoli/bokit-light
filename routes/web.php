<?php

use App\Filament\Pages\Calendar;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\RatesController;
use App\Http\Controllers\UpdateController;
use App\Support\Options;
use Illuminate\Support\Facades\Route;

// Installation routes (always accessible during installation)
Route::get('/install-legacy', [InstallController::class, 'index'])->name('install');
Route::post('/install-legacy', [InstallController::class, 'process'])->name('install.process');
Route::post('/install-legacy/complete', [InstallController::class, 'complete'])->name('install.complete');

// Update routes (always accessible when installed)
Route::get('/update', [UpdateController::class, 'index'])->name('update');
Route::post('/update/execute', [UpdateController::class, 'execute'])->name('update.execute');

// Service Worker (always accessible for PWA)
Route::get('/sw.js', function () {
    return response()
        ->view('sw')
        ->header('Content-Type', 'application/javascript')
        ->header('Service-Worker-Allowed', '/');
})->name('sw');

// Check if installation is complete - single source of truth
$isInstalled = Options::get('install.complete', false);

// Only setup app routes if installation is complete
if ($isInstalled) {
    // Determine auth middleware based on options
    $authMethod = Options::get('auth.method', 'none');
    $authMiddleware = match ($authMethod) {
        'wordpress' => 'auth.wordpress',
        'laravel' => 'auth.laravel',
        default => 'auth.none',
    };

    // Login/Logout routes
    if ($authMethod === 'wordpress') {
        Route::post('/login', function () {
            // Handled by WordPressAuth middleware
            return redirect('/calendar');
        })->middleware($authMiddleware);

        Route::get('/logout', function () {
            session()->forget('wp_user');

            return redirect('/');
        })->name('logout');
    } elseif ($authMethod === 'laravel') {
        // Login and logout are the panels' own routes now. Declared here, at the root, they
        // replaced the main panel's — Laravel indexes routes by method and URI, so the later
        // declaration wins and takes the earlier one's NAME down with it. That is how
        // filament.main.auth.logout came to be undefined while filament.app.auth.logout existed.
    }

    // '/' is deliberately NOT declared here: App\Filament\Main\Pages\Home returns '/' from
    // getRoutePath(), and the main panel — whose path is '' — registers that route itself, with the
    // panel's own middleware. Declaring it by hand would shadow the panel.

    // App routes (protected by auth)
    Route::middleware([$authMiddleware])->group(function () {
        // The calendar is now a standard Filament page (App\Filament\Pages\Calendar, panel 'app');
        // this keeps old bookmarks/links to /calendar working, query string included.
        Route::get('/calendar', fn () => redirect(
            Calendar::getUrl(request()->query(), panel: 'app'),
        ))->name('calendar');
        Route::get('/booking/{id}', [
            CalendarController::class,
            'booking',
        ])->name('booking.show');
        Route::post('/booking/{id}/resync', [
            CalendarController::class,
            'resync',
        ])->name('booking.resync');

        // Rates calculator — the one piece of the rates front-end with no Filament equivalent
        // yet. Everything else (properties, units, users, rates CRUD, the generic legacy-admin
        // system) has been replaced by Filament resources/panels and removed.
        Route::get('/rates/calculator', [
            RatesController::class,
            'calculator',
        ])->name('rates.calculator');
        Route::post('/rates/calculate', [
            RatesController::class,
            'calculate',
        ])->name('rates.calculate');

        // API for parent rates
        Route::get('/api/parent-rates/{propertyId}', [
            RatesController::class,
            'parentRates',
        ]);
    });
} else {
    // If not installed, redirect everything to install
    Route::get('/{any}', function () {
        return redirect('/install');
    })->where('any', '.*');
}
