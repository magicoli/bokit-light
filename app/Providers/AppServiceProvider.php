<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

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
        $this->ensureConfigIsLoaded();
        $this->createStorageStructure();
    }

    /**
     * Ensure configuration is loaded before creating storage structure
     */
    private function ensureConfigIsLoaded(): void
    {
        // Set view compiled path if not already set
        $viewCompiledPath = storage_path("framework/views");
        if (
            !Config::has("view.compiled") ||
            empty(Config::get("view.compiled"))
        ) {
            Config::set("view.compiled", $viewCompiledPath);
        }

        // Ensure the view compiler uses the correct path by setting it in the container
        $this->app->bind("blade.compiler", function ($app) use (
            $viewCompiledPath,
        ) {
            $compiler = new \Illuminate\View\Compilers\BladeCompiler(
                $app["files"],
                $viewCompiledPath,
            );
            return $compiler;
        });
    }

    /**
     * Create the storage directory structure
     */
    private function createStorageStructure(): void
    {
        // Get paths from configuration
        $directories = [
            Config::get("filesystems.disks.public.root"),
            Config::get("filesystems.disks.local.root"),
            Config::get("cache.stores.file.path"),
            Config::get("session.files"),
            dirname(Config::get("logging.channels.single.path")),
            dirname(Config::get("database.connections.sqlite.database")),
            Config::get("options.path"),
        ];

        Log::debug(
            "Creating storage structure with directories: " .
                implode(", ", $directories),
        );

        // Create directories
        foreach ($directories as $dir) {
            if (!empty($dir) && !is_dir($dir)) {
                try {
                    mkdir($dir, 0755, true);
                    Log::notice("Created missing directory: {$dir}");
                } catch (\Exception $e) {
                    Log::error(
                        "Failed to create directory {$dir}: {$e->getMessage()}",
                    );
                }
            }
        }

        // Create files
        $files = [
            Config::get("logging.channels.single.path"),
            Config::get("database.connections.sqlite.database"),
        ];

        foreach ($files as $file) {
            if (!file_exists($file)) {
                try {
                    touch($file);
                    chmod($file, 0644);
                    Log::notice("Created missing file: {$file}");
                } catch (\Exception $e) {
                    Log::error(
                        "Failed to create file {$file}: {$e->getMessage()}",
                    );
                }
            }
        }
    }
}
