<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InstallController;
use Illuminate\Support\Facades\Route;

// Installation routes (no middleware)
Route::get('/install', [InstallController::class, 'index'])->name('install');
Route::post('/install/run', [InstallController::class, 'install'])->name('install.run');

// Login route (no CheckInstalled middleware to avoid redirect loop)
Route::post('/login', function () {
    // Le middleware WordPressAuth gère l'authentification
    return redirect('/');
})->middleware('wp.auth');

Route::get('/logout', function () {
    session()->forget('wp_user');
    return redirect('/');
})->name('logout');

// App routes (require installation + auth)
Route::middleware(['wp.auth'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/booking/{id}', [DashboardController::class, 'booking'])->name('booking.show');
});
