<?php

namespace Tests;

use App\Support\Options;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Options caches sections in a static in-memory array that RefreshDatabase knows nothing
        // about - without this, a value set in one test leaks into every test that runs after it
        // in the same process (see App\Support\Options::flushCache()).
        Options::flushCache();

        // blade.compiler is not bound as a singleton due to how Laravel 12 bootstraps
        // in test environments, which causes Livewire directives (registered on the
        // facade root during boot) to be invisible to subsequent app() calls.
        // Pin the facade root as a true singleton so all calls share the same instance.
        $this->app->instance('blade.compiler', Blade::getFacadeRoot());

        // Stub Vite so @vite and Vite::asset() resolve to nothing instead of reading the build
        // manifest. Tests need no real assets, and reading the manifest makes them flake whenever
        // the dev watcher (composer run dev) is mid-rebuild and has momentarily rewritten it.
        $this->withoutVite();

        // Disable auto-sync during tests by setting last sync to now
        // This prevents the ~90 second sync delay on each test request
        // To include sync in tests, use: php artisan test --env=testing-with-sync
        if (! env('TESTING_WITH_SYNC', false)) {
            Cache::put('last_auto_sync', time(), 7200);
        }
    }
}
