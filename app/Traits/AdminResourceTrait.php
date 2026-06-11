<?php

namespace App\Traits;

// use Illuminate\Support\Facades\Log;
use App\Http\Controllers\AdminResourceController;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

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
     * @param  User|null  $user  User to filter for (defaults to current user)
     */
    public function scopeForUser(Builder $query, $user = null): Builder
    {
        $user = $user ?? auth()->user();

        // No user or admin/manager: no filtering
        if (! $user || $user->isAdmin() || $user->hasRole('manager')) {
            return $query;
        }

        // Everyone else sees the properties they are attached to
        // (property_user pivot) — nothing when not attached to any.
        return $this->scopeForPropertyManager($query, $user);
    }

    /**
     * Filter query for property_manager role
     *
     * Default implementation filters via property.users relationship.
     * Override in specific models if needed.
     *
     * @param  User  $user
     */
    protected function scopeForPropertyManager(Builder $query, $user): Builder
    {
        // For Property model: direct users relationship
        if ($this instanceof Property) {
            return $query->whereHas('users', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        }

        // For models with property relationship: filter via property.users
        if (method_exists($this, 'property')) {
            return $query->whereHas('property.users', function ($q) use (
                $user,
            ) {
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
                AdminResourceController::class,
            )->index($resourceName);
        })->name("{$resourceName}.index");

        // List route
        Route::get("/{$resourceName}/list", function () use ($resourceName) {
            return app(
                AdminResourceController::class,
            )->list($resourceName);
        })->name("{$resourceName}.list");

        // Add routes
        Route::get("/{$resourceName}/create", function () use ($resourceName) {
            return app(
                AdminResourceController::class,
            )->create($resourceName);
        })->name("{$resourceName}.create");
        Route::post("/{$resourceName}", function () use ($resourceName) {
            return app(
                AdminResourceController::class,
            )->store(request(), $resourceName);
        })->name("{$resourceName}.store");

        // Edit routes
        Route::get("/{$resourceName}/{id}/edit", function ($id) use (
            $resourceName,
        ) {
            return app(
                AdminResourceController::class,
            )->edit($resourceName, $id);
        })->name("{$resourceName}.edit");
        Route::post("/{$resourceName}/{id}", function ($id) use ($resourceName) {
            return app(
                AdminResourceController::class,
            )->update(request(), $resourceName, $id);
        })->name("{$resourceName}.update");
        Route::delete("/{$resourceName}/{id}", function ($id) use (
            $resourceName,
        ) {
            return app(
                AdminResourceController::class,
            )->destroy($resourceName, $id);
        })->name("{$resourceName}.destroy");

        // Settings routes
        Route::get("/{$resourceName}/settings", function () use (
            $resourceName,
        ) {
            return app(
                AdminResourceController::class,
            )->settings($resourceName);
        })->name("{$resourceName}.settings");
        Route::post("/{$resourceName}/settings", function () use (
            $resourceName,
        ) {
            return app(
                AdminResourceController::class,
            )->saveSettings(request(), $resourceName);
        })->name("{$resourceName}.settings.save");

        // Show route - must be after specific routes
        Route::get("/{$resourceName}/{id}", function ($id) use ($resourceName) {
            return app(
                AdminResourceController::class,
            )->show($resourceName, $id);
        })
            ->name("{$resourceName}.show")
            ->where('id', '[0-9]+');
    }

    /**
     * Get admin menu configuration with children
     */
    public static function adminMenuConfig(): array
    {
        $config = static::getConfig();
        $resourceName = $config['resource_name'] ?? null;
        if ($resourceName) {
            // Build children menu items
            $children = [
                [
                    'label' => __('admin.list'),
                    'url' => Route::has("admin.{$resourceName}.list")
                        ? route("admin.{$resourceName}.list")
                        : null,
                    'icon' => null,
                    'resource_name' => "{$resourceName}.list",
                ],
                [
                    'label' => __('admin.add'),
                    'url' => Route::has("admin.{$resourceName}.create")
                        ? route("admin.{$resourceName}.create")
                        : null,
                    'icon' => null,
                    'resource_name' => "{$resourceName}.add",
                ],
                [
                    'label' => __('admin.settings'),
                    'url' => Route::has("admin.{$resourceName}.settings")
                        ? route("admin.{$resourceName}.settings")
                        : null,
                    'icon' => null,
                    'resource_name' => "{$resourceName}.settings",
                ],
            ];
            // Parent gets same URL as first child (list)
            $parentUrl = $children[0]['url'] ?? null;
        }

        return [
            'model_class' => static::class,
            'label' => $config['menu']['label'] ?? ($config['title'] ?? null),
            'icon' => $config['menu']['icon'] ?? null,
            'parent' => $config['menu']['parent'] ?? null,
            'url' => $parentUrl ?? null,
            'order' => $config['menu']['order'] ?? 100,
            'resource_name' => $resourceName,
            'children' => $children,
            'capability' => $config['capability'],
        ];
    }
}
