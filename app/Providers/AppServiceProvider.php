<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register AdminMenuService as singleton
        $this->app->singleton(\App\Services\AdminMenuService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->ensureConfigIsLoaded();
        $this->createStorageStructure();
        $this->registerGates();
        $this->registerObservers();
    }

    /**
     * Register model observers
     */
    private function registerObservers(): void
    {
        \App\Models\Booking::observe(\App\Observers\BookingObserver::class);
    }

    /**
     * Register authorization gates
     */
    private function registerGates(): void
    {
        // Admin gate - access to admin area
        // Super admins have full access, property managers have limited access
        Gate::define("admin", function ($user) {
            if (!$user) {
                return false;
            }

            // Super admins have full access
            if ($user->isAdmin() || $user->hasRole("manager")) {
                return true;
            }

            // Property managers have access to admin area (but some sections may be restricted)
            return $user->hasRole("property_manager");
        });

        // Manage resource gate - admin or owner
        Gate::define("manage-resource", function ($user, $resource) {
            if (!$user) {
                return false;
            }

            // Admins can manage everything
            if ($user->isAdmin()) {
                return true;
            }

            // Owner can manage their own resources
            if (method_exists($resource, "isOwnedBy")) {
                return $resource->isOwnedBy($user);
            }

            // Fallback to owner_id check
            return isset($resource->owner_id) &&
                $resource->owner_id === $user->id;
        });

        // Manage gate - check if user can manage a model class or instance
        // This is for GLOBAL management rights - only admins and managers
        Gate::define("manage", function ($user, $modelClass = null) {
            if (!$user) {
                return false;
            }

            // Super admins can manage everything
            if ($user->isAdmin()) {
                return true;
            }

            // Convert short class names to full class names
            if (is_string($modelClass) && !class_exists($modelClass)) {
                $shortName = ucfirst($modelClass);
                $fullClass = "App\\Models\\{$shortName}";

                if (class_exists($fullClass)) {
                    $modelClass = $fullClass;
                } else {
                    return false;
                }
                // Global managers can manage everything
                if ($user->hasRole("manager")) {
                    return true;
                }
            }

            // Property managers do NOT have global manage rights
            return false;
        });

        // Property manager gate - check if user has property_manager role
        // This is a ROLE check, not a permission check
        // Ownership filtering happens in controllers/queries
        Gate::define("property_manager", function ($user) {
            if (!$user) {
                return false;
            }

            // Super admins always have access
            if ($user->isAdmin() || $user->hasRole("manager")) {
                return true;
            }

            // Check if user has property_manager role
            return $user->hasRole("property_manager");
        });

        // Booking manager gate - check if user has booking_manager role
        // This is a ROLE check, not a permission check
        // Ownership filtering happens in controllers/queries
        Gate::define("booking_manager", function ($user) {
            if (!$user) {
                return false;
            }

            // Super admins always have access
            if ($user->isAdmin() || $user->hasRole("manager")) {
                return true;
            }

            // Check if user has booking_manager role
            return $user->hasRole("booking_manager");
        });
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

        // Create directories
        foreach ($directories as $dir) {
            if (!empty($dir) && !is_dir($dir)) {
                try {
                    mkdir($dir, 0755, true);
                    Log::notice("Created directory {$dir}");
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
                    Log::notice("Created file {$file}");
                } catch (\Exception $e) {
                    Log::error(
                        "Failed to create file {$file}: {$e->getMessage()}",
                    );
                }
            }
        }
    }
}
