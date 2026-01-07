<?php

namespace App\Traits;

use App\Traits\FormTrait;
use App\Traits\ListTrait;
// use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;

/**
 * AdminResourceTrait
 *
 * Provides standard admin interface for models:
 * - Auto-registration of routes (list, add, edit, settings)
 * - Menu configuration
 * - Owner-based scoping
 *
 * Usage:
 *   class Booking extends Model {
 *       use AdminResourceTrait;
 *   }
 */
trait AdminResourceTrait
{
    use FormTrait;
    use ListTrait;

    /**
     * Scope query to user's authorized records
     * 
     * Filters based on user role:
     * - Admin/manager: sees everything (no filter)
     * - property_manager: sees only records they own or have access to
     * 
     * Override this method in models that need custom filtering logic.
     * 
     * @param Builder $query
     * @param \App\Models\User|null $user User to filter for (defaults to current user)
     * @return Builder
     */
    public function scopeForUser(Builder $query, $user = null): Builder
    {
        $user = $user ?? auth()->user();
        
        // No user or admin/manager: no filtering
        if (!$user || $user->isAdmin() || $user->hasRole('manager')) {
            return $query;
        }
        
        // Property managers: filter by ownership
        if ($user->hasRole('property_manager')) {
            // Default: filter via property relationship
            // Models should override this if they have direct user ownership
            return $this->scopeForPropertyManager($query, $user);
        }
        
        // Other roles: no access by default
        return $query->whereRaw('1 = 0');
    }

    /**
     * Filter query for property_manager role
     * 
     * Default implementation filters via property.users relationship.
     * Override in specific models if needed.
     * 
     * @param Builder $query
     * @param \App\Models\User $user
     * @return Builder
     */
    protected function scopeForPropertyManager(Builder $query, $user): Builder
    {
        // For Property model: direct users relationship
        if ($this instanceof \App\Models\Property) {
            return $query->whereHas('users', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        }
        
        // For models with property relationship: filter via property.users
        if (method_exists($this, 'property')) {
            return $query->whereHas('property.users', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        }
        
        // No property relationship: no access
        return $query->whereRaw('1 = 0');
    }

    /**
     * Register admin routes for this resource
     * Called from routes/admin.php or service provider
     */
    public static function registerAdminRoutes(): void
    {
        $config = static::getConfig();
        $resourceName = Str::plural(strtolower($config['classBasename']));

        Route::get("/{$resourceName}", function () use ($resourceName) {
            return app(
                \App\Http\Controllers\AdminResourceController::class,
            )->index($resourceName);
        })->name("{$resourceName}.index");

        // List route
        Route::get("/{$resourceName}/list", function () use (
            $resourceName,
        ) {
            return app(
                \App\Http\Controllers\AdminResourceController::class,
            )->list($resourceName);
        })->name("{$resourceName}.list");

        // Add routes
        Route::get("/{$resourceName}/create", function () use (
            $resourceName,
        ) {
            return app(
                \App\Http\Controllers\AdminResourceController::class,
            )->create($resourceName);
        })->name("{$resourceName}.create");
        Route::post("/{$resourceName}", function () use ($resourceName) {
            return app(
                \App\Http\Controllers\AdminResourceController::class,
            )->store(request(), $resourceName);
        })->name("{$resourceName}.store");

        // Edit routes
        Route::get("/{$resourceName}/{id}/edit", function ($id) use (
            $resourceName,
        ) {
            return app(
                \App\Http\Controllers\AdminResourceController::class,
            )->edit($resourceName, $id);
        })->name("{$resourceName}.edit");
        Route::put("/{$resourceName}/{id}", function ($id) use (
            $resourceName,
        ) {
            return app(
                \App\Http\Controllers\AdminResourceController::class,
            )->update(request(), $resourceName, $id);
        })->name("{$resourceName}.update");
        Route::delete("/{$resourceName}/{id}", function ($id) use (
            $resourceName,
        ) {
            return app(
                \App\Http\Controllers\AdminResourceController::class,
            )->destroy($resourceName, $id);
        })->name("{$resourceName}.destroy");

        // Settings routes
        Route::get("/{$resourceName}/settings", function () use (
            $resourceName,
        ) {
            return app(
                \App\Http\Controllers\AdminResourceController::class,
            )->settings($resourceName);
        })->name("{$resourceName}.settings");
        Route::post("/{$resourceName}/settings", function () use (
            $resourceName,
        ) {
            return app(
                \App\Http\Controllers\AdminResourceController::class,
            )->saveSettings(request(), $resourceName);
        })->name("{$resourceName}.settings.save");

        // Show route - must be after specific routes
        Route::get("/{$resourceName}/{id}", function ($id) use (
            $resourceName,
        ) {
            return app(
                \App\Http\Controllers\AdminResourceController::class,
            )->show($resourceName, $id);
        })
            ->name("{$resourceName}.show")
            ->where("id", "[0-9]+");
    }

    /**
     * Get admin menu configuration with children
     */
    public static function adminMenuConfig(): array
    {
        $config = static::getConfig();
        $resourceName = Str::plural(strtolower($config['classBasename']));

        // Build children menu items
        $children = [
            [
                "label" => __("admin.list"),
                "url" => Route::has("admin.{$resourceName}.list") ? route("admin.{$resourceName}.list") : null,
                "icon" => null,
                "resource_name" => "{$resourceName}.list",
            ],
            [
                "label" => __("admin.add"),
                "url" => Route::has("admin.{$resourceName}.create") ? route("admin.{$resourceName}.create") : null,
                "icon" => null,
                "resource_name" => "{$resourceName}.add",
            ],
            [
                "label" => __("admin.settings"),
                "url" => Route::has("admin.{$resourceName}.settings") ? route("admin.{$resourceName}.settings") : null,
                "icon" => null,
                "resource_name" => "{$resourceName}.settings",
            ],
        ];

        // Parent gets same URL as first child (list)
        $parentUrl = $children[0]["url"] ?? null;

        return [
            "model_class" => static::class,
            "label" => __("admin.{$resourceName}"),
            "icon" => null,
            "url" => $parentUrl,
            "order" => 100,
            "resource_name" => $resourceName,
            "children" => $children,
            "capability" => $config['capability'],
        ];
    }
}
