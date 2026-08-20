<?php

// Load initialization script FIRST (before Laravel boots)
use App\Backup\Http\Middleware\AutoBackup;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\ApplyMigrations;
use App\Http\Middleware\CheckCapability;
use App\Http\Middleware\CheckInstalled;
use App\Http\Middleware\LaravelAuth;
use App\Http\Middleware\NoAuth;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\RenewRememberToken;
use App\Http\Middleware\WordPressAuth;
use App\Sync\Http\Middleware\AutoSync;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    // Both paths, always: passing any path at all replaces the default, so naming only the sync
    // one would quietly unregister every other command in the app.
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
        __DIR__.'/../app/Sync/Console/Commands',
        __DIR__.'/../app/Backup/Console/Commands',
    ])
    ->withRouting(commands: __DIR__.'/../routes/console.php', health: '/up', then: function () {
        // Load admin routes FIRST (before web.php catch-all routes)
        Route::middleware('web')->group(base_path('routes/admin.php'));

        // Then load web routes (includes catch-all for properties)
        Route::middleware('web')->group(base_path('routes/web.php'));
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // Where an anonymous visitor is sent when something requires a session. Laravel aims at a
        // route named 'login', which only exists when auth.method is 'laravel' — anywhere else,
        // and on any panel of its own, the redirect died with "Route [login] not defined" and a
        // 500 in place of a login screen.
        $middleware->redirectGuestsTo(fn (): string => match (true) {
            Route::has('login') => route('login'),
            Route::has('filament.main.auth.login') => route('filament.main.auth.login'),
            Route::has('filament.app.auth.login') => route('filament.app.auth.login'),
            // The front page, asked of the panel that serves it rather than assumed to be the
            // site root — which it stops being the day that panel takes a path.
            Route::has('filament.main.pages.home') => route('filament.main.pages.home'),
            default => url('/'),
        });

        // Global middleware - always check if installed first
        $middleware->web(append: [
            CheckInstalled::class,
            ApplyMigrations::class,
            RenewRememberToken::class,
        ]);

        // Middleware aliases
        $middleware->alias([
            'auth.wordpress' => WordPressAuth::class,
            'auth.laravel' => LaravelAuth::class,
            'auth.none' => NoAuth::class,
            'admin' => AdminMiddleware::class,
            'can' => CheckCapability::class,
            'guest' => RedirectIfAuthenticated::class,
        ]);

        // Auto-sync iCal sources on page loads
        $middleware->append(AutoSync::class);

        // Same idea for the backups: taken while the site is used, no system task needed
        $middleware->append(AutoBackup::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Every logged exception says WHICH request produced it. Without this, an error that only
        // happens in production can only be guessed at: the log names the view and the line, never
        // the URL that reached them.
        //
        // The `request` binding is absent, not just null, when an exception is reported before the
        // HTTP kernel has bound it — e.g. during `composer post-autoload-dump`'s package:discover.
        // `request()` would then throw "Target class [request] does not exist" from inside the
        // reporter, replacing the real error with a misleading one, so guard on the binding itself.
        $exceptions->context(fn (): array => (
            app()->bound('request') && ($request = request()) instanceof Request
                ? [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'referer' => $request->header('referer'),
                ]
                : []
        ));

        // Prevent 403 redirects for authenticated users - show error page instead
        $exceptions->render(function (AuthorizationException $e, $request) {
            // If user is authenticated, show 403 error page instead of redirecting to login
            if (auth()->check()) {
                return response()->view(
                    'errors.403',
                    [
                        'exception' => $e,
                    ],
                    403,
                );
            }

            // Otherwise let Laravel handle it (redirect to login)
            return null;
        });
    })
    ->create();
