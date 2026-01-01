<?php

namespace App\Traits;

use App\Traits\FormTrait;
use App\Traits\ListTrait;
use Illuminate\Support\Facades\Log;
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
    use FormTrait;
    use ListTrait;

    protected static $config = [];

    static function init()
    {
        self::$config = [
            "label" => __("admin." . static::getResourceName()),
            "icon" => null,
            "routes" => ["list", "show", "add", "edit", "settings"],
            "order" => 100,
            "capability" => "manage",
        ];
    }

    /**
     * Register admin routes for this resource
     * Called from routes/admin.php or service provider
     */
    public static function registerAdminRoutes(): void
    {
        $config = static::adminConfig();
        $resourceName = $config["resource_name"] ?? static::getResourceName();

        # We should avoid creating routes for resources the user cannot manage
        # But it does not work as expected, user auth is not yet initialized
        // if (!user_can($config["capability"], $config["model_class"] ?? null)) {
        //     return;
        // }

        // Index/List - main resource route
        Route::get("/{$resourceName}", function () use ($resourceName) {
            return app(
                \App\Http\Controllers\AdminResourceController::class,
            )->index($resourceName);
        })->name("{$resourceName}.index");

        // Explicit list route (same as index for now)
        if (in_array("list", $config["routes"] ?? [])) {
            Route::get("/{$resourceName}/list", function () use (
                $resourceName,
            ) {
                return app(
                    \App\Http\Controllers\AdminResourceController::class,
                )->list($resourceName);
            })->name("{$resourceName}.list");
        }

        // Add
        if (in_array("add", $config["routes"] ?? [])) {
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
        }

        // Edit
        if (in_array("edit", $config["routes"] ?? [])) {
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
        }

        // Settings
        if (in_array("settings", $config["routes"] ?? [])) {
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
        }

        // Show - Display single object with tabs/actions
        // Must be after /create, /list, /settings to avoid conflicts
        if (
            in_array("show", $config["routes"] ?? []) ||
            in_array("edit", $config["routes"] ?? [])
        ) {
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

        // Custom routes
        if (isset($config["custom_routes"])) {
            $config["custom_routes"]($resourceName);
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
            $routeName = "admin.{$resourceName}.list";
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
            "label" => $config["label"] ?? __("admin.{$resourceName}"),
            "icon" => $config["icon"] ?? null,
            "url" => $parentUrl,
            "order" => $config["order"] ?? 100,
            "resource_name" => $resourceName,
            "children" => $children,
            "capability" => $config["capability"] ?? "manage",
        ];
    }

    /**
     * Get resource name (plural, lowercase)
     */
    public static function getResourceName(): string
    {
        $className = class_basename(static::class);
        return strtolower(str($className)->plural());
    }

    /**
     * Admin configuration - override in model
     */
    public static function adminConfig(): array
    {
        self::init();
        return static::$config;
    }
}
