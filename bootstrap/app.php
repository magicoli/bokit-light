<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'wp.auth' => \App\Http\Middleware\WordPressAuth::class,
        ]);
        
        // Check installation FIRST (before anything else)
        $middleware->append(\App\Http\Middleware\CheckInstalled::class);
        
        // Auto-sync middleware (WordPress-style pseudo-cron for calendar sync)
        $middleware->append(\App\Http\Middleware\AutoSync::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
