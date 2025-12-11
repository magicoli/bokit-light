<?php

// Load initialization script FIRST (before Laravel boots)
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . "/../routes/web.php",
        commands: __DIR__ . "/../routes/console.php",
        health: "/up",
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global middleware - always check if installed first
        $middleware->web(append: [
            \App\Http\Middleware\CheckInstalled::class,
        ]);

        // Middleware aliases
        $middleware->alias([
            "auth.wordpress" => \App\Http\Middleware\WordPressAuth::class,
            "auth.none" => \App\Http\Middleware\NoAuth::class,
        ]);

        // Auto-sync iCal sources on page loads
        $middleware->append(\App\Http\Middleware\AutoSync::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
