<?php

use Illuminate\Support\Facades\Route;
use App\Services\AdminMenuService;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
|
| Admin routes are protected by 'admin' middleware.
| Resources using AdminResourceTrait register their routes dynamically.
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

        // General settings (admin only)
        Route::get("/settings", function () {
            return view("admin.settings");
        })->name("settings");

        // Save settings
        Route::post("/settings", [
            \App\Http\Controllers\AdminController::class,
            "saveSettings",
        ])->name("settings.save");

        // Dynamic resource routes will be registered by models using AdminResourceTrait
        // Called from AppServiceProvider after auth is available
    });
