<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;

/**
 * AdminMenuService
 *
 * Single source of truth for ALL admin menu items.
 * Returns unified array with dashboard, settings, and dynamic resources.
 */
class AdminMenuService
{
    protected array $menuItems = [];
    protected bool $initialized = false;

    /**
     * Get all menu items (unified structure)
     */
    public function getMenuItems(): array
    {
        if (!$this->initialized) {
            $this->buildMenu();
        }

        // Sort by order (10 = default, gaps for future insertion)
        usort($this->menuItems, fn($a, $b) => $a["order"] <=> $b["order"]);

        return $this->menuItems;
    }

    /**
     * Get resources only (for backwards compatibility)
     */
    public function getResources(): array
    {
        if (!$this->initialized) {
            $this->buildMenu();
        }

        return array_filter(
            $this->menuItems,
            fn($item) => $item["type"] === "resource",
        );
    }

    /**
     * Build complete menu structure
     */
    protected function buildMenu(): void
    {
        $this->initialized = true;

        // Core admin items (always present)
        $this->addCoreItems();

        // Discover dynamic resources from models
        $this->discoverResources();
    }

    /**
     * Add core admin items (dashboard, settings)
     * Check auth HERE so it happens at menu generation, not at route load
     */
    protected function addCoreItems(): void
    {
        // Dashboard - accessible to all admin middleware users
        $this->menuItems[] = [
            "type" => "core",
            "route" => "admin.dashboard",
            "title_key" => "admin.dashboard",
            "icon" => "dashboard",
            "parent" => null,
            "order" => 1,
        ];

        // Settings - admin only - check at MENU BUILD time
        // Use optional() to avoid errors if auth not ready yet
        $user = optional(auth()->user());
        if ($user && $user->is_admin) {
            $this->menuItems[] = [
                "type" => "core",
                "route" => "admin.settings",
                "title_key" => "admin.general_settings",
                "icon" => "settings-sliders",
                "parent" => null,
                "order" => 5,
            ];
        }
    }

    /**
     * Discover all models with AdminResourceTrait
     */
    protected function discoverResources(): void
    {
        $modelsPath = app_path("Models");

        if (!is_dir($modelsPath)) {
            return;
        }

        $files = File::files($modelsPath);

        foreach ($files as $file) {
            $className = "App\\Models\\" . $file->getFilenameWithoutExtension();

            if (class_exists($className)) {
                $uses = class_uses_recursive($className);

                if (in_array("App\\Traits\\AdminResourceTrait", $uses)) {
                    try {
                        $config = $className::adminMenuConfig();

                        // Check permissions
                        if ($config["admin_only"] ?? false) {
                            $user = optional(auth()->user());
                            if (!$user || !$user->is_admin) {
                                continue;
                            }
                        }

                        // Add parent resource menu
                        $resourceName = $config["resource_name"];
                        $parentItem = [
                            "type" => "resource",
                            "route" => "admin.{$resourceName}.list",
                            "title" => $config["label"],
                            "icon" => $config["icon"] ?? null,
                            "parent" => null,
                            "order" => $config["order"] ?? 10,
                            "resource_name" => $resourceName,
                            "model_class" => $config["model_class"],
                        ];

                        $this->menuItems[] = $parentItem;

                        // Add sub-menu items (list, add, settings)
                        foreach ($config["routes"] as $routeType) {
                            $routeName = "admin.{$resourceName}.{$routeType}";

                            $this->menuItems[] = [
                                "type" => "resource-sub",
                                "route" => $routeName,
                                "title_key" => "admin.{$routeType}",
                                "icon" => null,
                                "parent" => $resourceName,
                                "order" => $config["order"] ?? 10,
                            ];
                        }
                    } catch (\Exception $e) {
                        Log::error($e->getMessage());
                    }
                }
            }
        }
    }

    /**
     * Register routes for all resources
     */
    public function registerRoutes(): void
    {
        if (!$this->initialized) {
            $this->buildMenu();
        }

        foreach ($this->menuItems as $item) {
            if ($item["type"] === "resource") {
                $modelClass = $item["model_class"] ?? null;

                if (
                    $modelClass &&
                    method_exists($modelClass, "registerAdminRoutes")
                ) {
                    $modelClass::registerAdminRoutes();
                }
            }
        }
    }

    public function menuHtml()
    {
        $items = $this->getMenuItems();

        $html = $this->menuListHtml($items);

        return $html;
    }

    public function menuListHtml(array $items): string
    {
        $html = '<ul class="menu-list">';
        foreach ($items as $key => $item) {
            $html .= $this->menuItemHtml($item);
        }
        $html .= "</ul>";
        return $html;
    }

    public function menuItemHtml(array $item): string
    {
        // if (!Route::has($item['route'])) return;
        //
        try {
            $route = Route::has($item["route"]) ? route($item["route"]) : "#";
            $active = request()->routeIs($item["route"]) ? "page" : "false";
        } catch (Exception $e) {
            $route = "#";
            $active = "false";
        }

        // Get title
        $title = $item["title"] ?? __($item["title_key"] ?? "app.untitled");

        // Get children for this item
        $itemChildren = $children[$item["resource_name"] ?? null] ?? [];

        // Render icon using helper function
        $iconHtml = "";
        if ($icon = $item["icon"] ?? null) {
            $iconHtml = icon($icon);
        }

        $html = sprintf(
            '<li class="menu-item">
                <a href="%s" aria-current="%s">
                    <span class="icon">%s</span>
                    <span class="title">%s</span>
                </a>
                %s <!-- children -->
            </li>',
            $route,
            $active,
            $iconHtml,
            $title,
            empty($itemChildren) ? "" : $this->menuListHtml($itemChildren),
        );

        return $html;
    }
}
