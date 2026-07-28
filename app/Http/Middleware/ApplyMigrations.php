<?php

namespace App\Http\Middleware;

use App\Support\Options;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ApplyMigrations
{
    /**
     * Routes that should bypass the update check
     */
    protected $except = ['update', 'update/*', 'install', 'install/*'];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip check if not installed
        if (!Options::get('install.complete', false)) {
            return $next($request);
        }

        // Skip check for excluded routes
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        // Check if migrations are pending
        if ($this->hasPendingMigrations()) {
            Log::notice('[AutoUpdate] Migrations are pending');
            // Run migrations automatically (silent update)
            $this->runMigrationsAutomatically();

            // } else {
            //     Log::debug("[AutoUpdate] No migrations pending");
        }

        return $next($request);
    }

    /**
     * Run migrations automatically with backup
     */
    protected function runMigrationsAutomatically(): void
    {
        try {
            Log::info('[AutoUpdate] Running migrations automatically');

            // 1. Backup database first
            $this->backupDatabase();

            // 2. Run migrations - capture output to prevent it from appearing in HTML
            ob_start();
            Artisan::call('migrate', ['--force' => true]);
            $output = ob_get_clean();

            Log::notice('[AutoUpdate] Migrations completed successfully', [
                'output' => $output,
            ]);

            // Log successful migration for admin
            Log::notice('[AutoUpdate] Database updated automatically', [
                'timestamp' => now()->timestamp,
                'output' => $output,
            ]);
        } catch (\Exception $e) {
            ob_end_clean(); // Clean buffer in case of error
            Log::error('[AutoUpdate] Migration failed: ' . $e->getMessage());

            // Store error for admin notification
            Options::set('admin.last_update_error', [
                'timestamp' => now()->timestamp,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Backup database before running migrations
     *
     * Delegates to the application's one backup procedure instead of copying the file by hand: the
     * same archive, destination and retention as the scheduled and pre-deploy backups, and one
     * dumper per database engine rather than two.
     */
    protected function backupDatabase(): void
    {
        try {
            // Complete, like the one a deploy takes: an update is not known in advance to touch
            // the database only. The work happens after the response, so its size costs the visitor
            // who triggered it nothing.
            $status = Artisan::call('backup:run');

            if ($status === 0) {
                Log::info('[AutoUpdate] Database backup created');
            } else {
                // Deliberately not fatal: the deploy aborts on a failed backup, but a live request
                // that finds migrations pending has no better option than to apply them.
                Log::warning('[AutoUpdate] Backup failed, migrating anyway');
            }
        } catch (\Exception $e) {
            Log::warning('[AutoUpdate] Backup failed: ' . $e->getMessage());

            // Don't stop migrations if backup fails
        }
    }

    /**
     * Check if the request should skip the update check
     */
    protected function shouldSkip(Request $request): bool
    {
        foreach ($this->except as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if there are pending migrations
     * This is fast - only checks if migration files exist that haven't been run
     */
    protected function hasPendingMigrations(): bool
    {
        try {
            // Get list of migration files
            $migrationFiles = $this->getMigrationFiles();

            // Get list of already run migrations from database
            $ranMigrations = DB::table('migrations')->pluck('migration')->toArray();

            // Check if any migration file hasn't been run
            foreach ($migrationFiles as $file) {
                $migrationName = $this->getMigrationName($file);
                if (!in_array($migrationName, $ranMigrations)) {
                    // Log::debug(
                    //     "[AutoUpdate] Migration pending: $migrationName",
                    // );
                    return true;

                    // } else {
                    //     Log::debug(
                    //         "[AutoUpdate] Migration already run: $migrationName",
                    //     );
                }
            }

            return false;
        } catch (\Exception $e) {
            // If migrations table doesn't exist or any error, assume we need to run migrations
            Log::warning('[AutoUpdate] Error checking migrations: ' . $e->getMessage());
            return true; // Force migration check if there's an error
        }
    }

    /**
     * Get all migration files
     */
    protected function getMigrationFiles(): array
    {
        $path = database_path('migrations');
        return File::glob($path . '/*.php');
    }

    /**
     * Get migration name from file path
     */
    protected function getMigrationName(string $path): string
    {
        return str_replace('.php', '', basename($path));
    }
}
