<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Disable auto-sync during tests by setting last sync to now
        // This prevents the ~90 second sync delay on each test request
        // To include sync in tests, use: php artisan test --env=testing-with-sync
        if (!env('TESTING_WITH_SYNC', false)) {
            Cache::put('last_auto_sync', time(), 7200);
        }
    }
}
