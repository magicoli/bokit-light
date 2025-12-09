<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::post('/login', function () {
    // Le middleware WordPressAuth gère l'authentification
    return redirect('/');
})->middleware('wp.auth');

Route::get('/logout', function () {
    session()->forget('wp_user');
    return redirect('/');
})->name('logout');

Route::middleware(['wp.auth'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/booking/{booking}', [DashboardController::class, 'booking'])->name('booking.show');
});
