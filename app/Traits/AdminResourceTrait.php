<?php

namespace App\Traits;

use Illuminate\Support\Facades\Route;
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
 *
 *       public static function adminConfig(): array {
 *           return [
 *               'label' => 'Bookings',
 *               'icon' => '📅',
 *               'routes' => ['calendar', 'list', 'add', 'settings'],
 *           ];
 *       }
 *   }
 */
trait AdminResourceTrait
{
    /**
     * Register admin routes for this resource
     * Called from routes/admin.php or service provider
     */
    public static function registerAdminRoutes(): void
    {
        $config = static::adminConfig();
        $resourceName = $config["resource_name"] ?? static::getResourceName();
        $controllerClass = "App\Http\Controllers\AdminResourceController";

        // List
        if (in_array("list", $config["routes"] ?? [])) {
            Route::get("/{$resourceName}", [$controllerClass, "index"])->name(
                "{$resourceName}.index",
            );
        }

        // Add
        if (in_array("add", $config["routes"] ?? [])) {
            Route::get("/{$resourceName}/create", [
                $controllerClass,
                "create",
            ])->name("{$resourceName}.create");
            Route::post("/{$resourceName}", [$controllerClass, "store"])->name(
                "{$resourceName}.store",
            );
        }

        // Edit
        if (in_array("edit", $config["routes"] ?? [])) {
            Route::get("/{$resourceName}/{id}/edit", [
                $controllerClass,
                "edit",
            ])->name("{$resourceName}.edit");
            Route::put("/{$resourceName}/{id}", [
                $controllerClass,
                "update",
            ])->name("{$resourceName}.update");
            Route::delete("/{$resourceName}/{id}", [
                $controllerClass,
                "destroy",
            ])->name("{$resourceName}.destroy");
        }

        // Settings
        if (in_array("settings", $config["routes"] ?? [])) {
            Route::get("/{$resourceName}/settings", [
                $controllerClass,
                "settings",
            ])->name("{$resourceName}.settings");
            Route::post("/{$resourceName}/settings", [
                $controllerClass,
                "saveSettings",
            ])->name("{$resourceName}.settings.save");
        }

        // Custom routes
        if (isset($config["custom_routes"])) {
            $config["custom_routes"]($resourceName, $controllerClass);
        }
    }

    /**
     * Get admin menu configuration with children
     */
    public static function adminMenuConfig(): array
    {
        $config = static::adminConfig();
        $resourceName = $config["resource_name"] ?? static::getResourceName();
        $routes = $config["routes"] ?? ["list", "add", "settings"];

        // Build children menu items
        $children = [];

        if (in_array("list", $routes)) {
            $routeName = "admin.{$resourceName}.index";
            $children[] = [
                "label" => __("admin.list"),
                "url" => Route::has($routeName) ? route($routeName) : null,
                "icon" => null,
                "resource_name" => "{$resourceName}.list",
            ];
        }

        if (in_array("add", $routes)) {
            $routeName = "admin.{$resourceName}.create";
            $children[] = [
                "label" => __("admin.add"),
                "url" => Route::has($routeName) ? route($routeName) : null,
                "icon" => null,
                "resource_name" => "{$resourceName}.add",
            ];
        }

        if (in_array("settings", $routes)) {
            $routeName = "admin.{$resourceName}.settings";
            $children[] = [
                "label" => __("admin.settings"),
                "url" => Route::has($routeName) ? route($routeName) : null,
                "icon" => null,
                "resource_name" => "{$resourceName}.settings",
            ];
        }

        // Parent gets same URL as first child (typically list)
        $parentUrl = $children[0]["url"] ?? null;

        return [
            "model_class" => static::class,
            "label" => $config["label"] ?? ucfirst($resourceName),
            "icon" => $config["icon"] ?? null,
            "url" => $parentUrl,
            "order" => $config["order"] ?? 100,
            "resource_name" => $resourceName,
            "children" => $children,
        ];
    }

    /**
     * Scope query to current user's resources (unless admin)
     */
    public function scopeOwnedByCurrentUser(Builder $query): Builder
    {
        $user = auth()->user();

        if (!$user) {
            return $query->whereRaw("1 = 0"); // No results
        }

        if ($user->is_admin) {
            return $query; // Admins see everything
        }

        // Owner-based filtering
        return $query->where("owner_id", $user->id);
    }

    /**
     * Check if resource is owned by user
     */
    public function isOwnedBy($user): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->is_admin) {
            return true;
        }

        return $this->owner_id === $user->id;
    }

    /**
     * Get resource name (plural, lowercase)
     */
    protected static function getResourceName(): string
    {
        $className = class_basename(static::class);
        return strtolower(str($className)->plural());
    }

    /**
     * Admin configuration - override in model
     */
    public static function adminConfig(): array
    {
        return [
            "label" => ucfirst(static::getResourceName()),
            "icon" => null,
            "routes" => ["list", "add", "edit", "settings"],
            "order" => 100,
        ];
    }
}
