<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use App\Support\Options;

class CheckUpdates
{
    /**
     * Routes that should bypass the update check
     */
    protected $except = ["update", "update/*", "install", "install/*"];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip check if not installed
        if (!Options::get("install.complete", false)) {
            return $next($request);
        }

        // Skip check for excluded routes
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        // Check if migrations are pending
        if ($this->hasPendingMigrations()) {
            // In local environment, redirect to /update page for manual confirmation
            if (config("app.env") === "local") {
                return redirect("/update");
            }

            // In production, run migrations automatically (silent update)
            $this->runMigrationsAutomatically();
        }

        return $next($request);
    }

    /**
     * Run migrations automatically with backup
     */
    protected function runMigrationsAutomatically(): void
    {
        try {
            Log::info("[AutoUpdate] Running migrations automatically");

            // 1. Backup database first
            $this->backupDatabase();

            // 2. Run migrations - capture output to prevent it from appearing in HTML
            ob_start();
            Artisan::call("migrate", ["--force" => true]);
            $output = ob_get_clean();

            Log::info("[AutoUpdate] Migrations completed successfully", [
                "output" => $output,
            ]);

            // Store notification for admin
            Options::set("admin.last_update", [
                "timestamp" => now()->timestamp,
                "message" => "Database updated automatically",
            ]);
        } catch (\Exception $e) {
            ob_end_clean(); // Clean buffer in case of error
            Log::error("[AutoUpdate] Migration failed: " . $e->getMessage());

            // Store error for admin notification
            Options::set("admin.last_update_error", [
                "timestamp" => now()->timestamp,
                "error" => $e->getMessage(),
            ]);
        }
    }

    /**
     * Backup database before running migrations
     */
    protected function backupDatabase(): void
    {
        try {
            $backupDir = storage_path("backups");

            // Create backup directory if it doesn't exist
            if (!File::exists($backupDir)) {
                File::makeDirectory($backupDir, 0755, true);
            }

            // Generate backup filename with timestamp
            $timestamp = now()->format("Y-m-d_His");
            $backupFile = $backupDir . "/backup_before_migration_{$timestamp}";

            // Get database config
            $database = config("database.default");
            $connection = config("database.connections.{$database}");

            // SQLite backup
            if ($database === "sqlite") {
                $dbPath = $connection["database"];
                File::copy($dbPath, $backupFile . ".sqlite");
                Log::info(
                    "[AutoUpdate] Database backup created: {$backupFile}.sqlite",
                );
            }
            // MySQL backup
            elseif ($database === "mysql") {
                $command = sprintf(
                    "mysqldump -h%s -u%s -p%s %s > %s 2>&1",
                    escapeshellarg($connection["host"]),
                    escapeshellarg($connection["username"]),
                    escapeshellarg($connection["password"]),
                    escapeshellarg($connection["database"]),
                    escapeshellarg($backupFile . ".sql"),
                );
                exec($command, $output, $returnCode);
                if ($returnCode === 0) {
                    Log::info(
                        "[AutoUpdate] Database backup created: {$backupFile}.sql",
                    );
                }
            }

            // Keep only last 10 backups
            $this->cleanOldBackups($backupDir);
        } catch (\Exception $e) {
            Log::warning("[AutoUpdate] Backup failed: " . $e->getMessage());
            // Don't stop migrations if backup fails
        }
    }

    /**
     * Clean old backups, keep only last 10
     */
    protected function cleanOldBackups(string $backupDir): void
    {
        $backups = File::glob($backupDir . "/backup_*");

        if (count($backups) > 10) {
            // Sort by date (filename contains timestamp)
            usort($backups, function ($a, $b) {
                return filemtime($a) - filemtime($b);
            });

            // Delete oldest backups
            $toDelete = array_slice($backups, 0, count($backups) - 10);
            foreach ($toDelete as $file) {
                File::delete($file);
            }
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
            $ranMigrations = DB::table("migrations")
                ->pluck("migration")
                ->toArray();

            // Check if any migration file hasn't been run
            foreach ($migrationFiles as $file) {
                $migrationName = $this->getMigrationName($file);
                if (!in_array($migrationName, $ranMigrations)) {
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            // If migrations table doesn't exist or any error, assume we need to run migrations
            Log::warning(
                "[AutoUpdate] Error checking migrations: " . $e->getMessage(),
            );
            return true; // Force migration check if there's an error
        }
    }

    /**
     * Get all migration files
     */
    protected function getMigrationFiles(): array
    {
        $path = database_path("migrations");
        return File::glob($path . "/*.php");
    }

    /**
     * Get migration name from file path
     */
    protected function getMigrationName(string $path): string
    {
        return str_replace(".php", "", basename($path));
    }
}
