<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Ensure database directory exists
        $dbPath = storage_path('database/default');
        if (!is_dir($dbPath)) {
            mkdir($dbPath, 0755, true);
        }
    }
}
