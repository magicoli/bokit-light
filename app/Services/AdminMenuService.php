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
     */
    protected function addCoreItems(): void
    {
        // Dashboard - accessible to all admin middleware users
        $this->menuItems[] = [
            "label" => __("admin.dashboard"),
            "url" => Route::has("admin.dashboard")
                ? route("admin.dashboard")
                : null,
            "icon" => "dashboard",
            "order" => 1,
            "resource_name" => "dashboard",
            "children" => [],
        ];

        // Settings - admin only
        if (user_can("manage", \App\Models\User::class)) {
            $this->menuItems[] = [
                "label" => __("admin.general_settings"),
                "url" => Route::has("admin.settings")
                    ? route("admin.settings")
                    : null,
                "icon" => "settings-sliders",
                "order" => 5,
                "resource_name" => "settings",
                "children" => [],
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

                        // Check permissions with user_can()
                        if (!user_can("manage", $className)) {
                            continue;
                        }

                        // Add parent resource menu with children
                        $this->menuItems[] = $config;
                    } catch (\Exception $e) {
                        Log::error("AdminMenuService: " . $e->getMessage());
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
        // Get URL and determine classes
        $url = $item["url"] ?? null;
        $classes = ["menu-item"];

        if (!$url) {
            $classes[] = "disabled";
            $classes[] = "not-found";
            $url = "#";
        }

        // Check if current page
        $isCurrent = false;
        if ($url !== "#") {
            try {
                $currentUrl = request()->url();
                $isCurrent = $url === $currentUrl;
            } catch (\Exception $e) {
                // Ignore
            }
        }

        // Get title
        $title = $item["label"] ?? __("app.untitled");

        // Render icon only if present
        $iconHtml = "";
        if (!empty($item["icon"])) {
            $iconHtml = icon($item["icon"]);
        }

        // Render children recursively
        $childrenHtml = "";
        $hasChildren = false;
        if (!empty($item["children"])) {
            $hasChildren = true;
            $classes[] = "has-children";
            $childrenHtml = $this->menuListHtml($item["children"]);
        }

        /**
         * Memo: standard aria-current values:
         * - page
         * - step
         * - location (within an environment or context)
         * - date
         * - time
         * - true: current (not specifie)
         * - false: not current
         */
        $html = sprintf(
            '<li class="%s">
                <a href="%s" aria-current="%s">
                    %s
                    <span class="title">%s</span>
                </a>
                %s
            </li>',
            implode(" ", $classes),
            htmlspecialchars($url),
            $isCurrent ? ($hasChildren ? "location" : "page") : "false",
            $iconHtml ? '<span class="icon">' . $iconHtml . "</span>" : "",
            htmlspecialchars($title),
            $childrenHtml,
        );

        return $html;
    }
}
