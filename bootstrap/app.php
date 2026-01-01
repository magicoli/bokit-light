<?php

// Load initialization script FIRST (before Laravel boots)
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . "/../routes/web.php",
        commands: __DIR__ . "/../routes/console.php",
        health: "/up",
        then: function () {
            Route::middleware('web')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global middleware - always check if installed first
        $middleware->web(
            append: [
                \App\Http\Middleware\CheckInstalled::class,
                \App\Http\Middleware\ApplyMigrations::class,
                \App\Http\Middleware\SetLocale::class,
                \App\Http\Middleware\RenewRememberToken::class,
            ],
        );

        // Middleware aliases
        $middleware->alias([
            "auth.wordpress" => \App\Http\Middleware\WordPressAuth::class,
            "auth.laravel" => \App\Http\Middleware\LaravelAuth::class,
            "auth.none" => \App\Http\Middleware\NoAuth::class,
            "admin" => \App\Http\Middleware\AdminMiddleware::class,
            "can" => \App\Http\Middleware\CheckCapability::class,
        ]);

        // Auto-sync iCal sources on page loads
        $middleware->append(\App\Http\Middleware\AutoSync::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Prevent 403 redirects for authenticated users - show error page instead
        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) {
            // If user is authenticated, show 403 error page instead of redirecting to login
            if (auth()->check()) {
                return response()->view('errors.403', [
                    'exception' => $e
                ], 403);
            }
            
            // Otherwise let Laravel handle it (redirect to login)
            return null;
        });
    })
    ->create();
