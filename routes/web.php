<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InstallController;
use App\Support\Options;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

// Installation routes (always accessible, no middleware)
Route::get('/install', [InstallController::class, 'index'])->name('install');
Route::post('/install', [InstallController::class, 'process'])->name('install.process');

// Check if installation is complete
$isInstalled = false;
try {
    $authMethod = Options::get('auth.method');
    $isInstalled = Schema::hasTable('units') && Options::has('auth.method');
    
    // For WordPress auth, also check that an admin user exists
    if ($isInstalled && $authMethod === 'wordpress') {
        $isInstalled = $isInstalled && \App\Models\User::where('is_admin', true)->exists();
    }
} catch (\Exception $e) {
    // Not installed
}

// Only setup auth routes if installation is complete
if ($isInstalled) {
    // Determine auth middleware based on options
    $authMethod = Options::get('auth.method', 'none');
    $authMiddleware = $authMethod === 'wordpress' ? 'auth.wordpress' : 'auth.none';

    // Login/Logout routes (only for WordPress auth)
    if ($authMethod === 'wordpress') {
        Route::post('/login', function () {
            // Handled by WordPressAuth middleware
            return redirect('/');
        })->middleware($authMiddleware);

        Route::get('/logout', function () {
            session()->forget('wp_user');
            return redirect('/');
        })->name('logout');
    }

    // App routes (protected by auth)
    Route::middleware([$authMiddleware])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/booking/{id}', [DashboardController::class, 'booking'])->name('booking.show');
    });
} else {
    // If not installed, redirect everything to install
    Route::get('/{any}', function () {
        return redirect('/install');
    })->where('any', '.*');
}
