<?php

namespace App\Services;

/**
 * AdminRegistry - Central registry for admin interface items
 * 
 * DRAFT - Not yet fully implemented
 * 
 * Inspired by WordPress add_menu_page(), this registry allows ANY class or script
 * to add items to the admin menu and register corresponding routes.
 * 
 * USE CASES:
 * 1. Models with AdminResourceTrait (auto-registered)
 * 2. Custom pages tied to models (e.g., Bookings Calendar, Categories)
 * 3. Standalone admin pages (Health/Status, Dashboard, External Bridges)
 * 
 * USAGE EXAMPLES:
 * 
 * // Auto-register a model (called by AdminResourceTrait)
 * AdminRegistry::registerModel(Booking::class);
 * 
 * // Register a custom page
 * AdminRegistry::registerPage([
 *     'id' => 'bookings-calendar',
 *     'label' => 'Calendar',
 *     'parent' => 'bookings',  // optional - makes this a child menu item
 *     'icon' => 'calendar',
 *     'order' => 20,
 *     'permission' => fn() => user_can('manage', Booking::class),
 *     'view' => 'admin.bookings.calendar',
 *     'controller' => [BookingController::class, 'calendar'], // optional
 *     'route_path' => '/bookings/calendar',
 *     'route_name' => 'bookings.calendar',
 *     'route_methods' => ['GET'],
 * ]);
 * 
 * // Register a standalone page (no parent)
 * AdminRegistry::registerPage([
 *     'id' => 'system-health',
 *     'label' => 'System Health',
 *     'icon' => 'heart-pulse',
 *     'order' => 999,
 *     'permission' => fn() => user_can('manage', User::class),
 *     'view' => 'admin.health',
 *     'route_path' => '/health',
 *     'route_name' => 'health',
 * ]);
 * 
 * FUTURE IMPLEMENTATION:
 * - routes/admin.php will call AdminRegistry::registerRoutes()
 * - AdminMenuService will call AdminRegistry::all() to build menu
 * - AppServiceProvider::boot() can register custom pages
 * - Plugins can register their own pages via service providers
 */
class AdminRegistry
{
    /**
     * Registered models with AdminResourceTrait
     */
    protected static array $models = [];

    /**
     * Registered custom pages
     */
    protected static array $pages = [];

    /**
     * Register a model with AdminResourceTrait
     * Called automatically by the trait
     */
    public static function registerModel(string $modelClass): void
    {
        if (!in_array($modelClass, static::$models)) {
            static::$models[] = $modelClass;
        }
    }

    /**
     * Register a custom admin page
     * 
     * @param array $config Page configuration with keys:
     *   - id: Unique identifier
     *   - label: Display label
     *   - parent: Optional parent menu ID
     *   - icon: Optional icon name
     *   - order: Sort order (default: 100)
     *   - permission: Closure or callback to check permission
     *   - view: View name
     *   - controller: Optional controller method [Class, 'method']
     *   - route_path: URL path (relative to /admin)
     *   - route_name: Route name (relative to admin.)
     *   - route_methods: HTTP methods (default: ['GET'])
     */
    public static function registerPage(array $config): void
    {
        // Validate required fields
        $required = ['id', 'label', 'route_path', 'route_name'];
        foreach ($required as $field) {
            if (!isset($config[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        // Set defaults
        $config = array_merge([
            'parent' => null,
            'icon' => null,
            'order' => 100,
            'permission' => fn() => true,
            'view' => null,
            'controller' => null,
            'route_methods' => ['GET'],
        ], $config);

        static::$pages[$config['id']] = $config;
    }

    /**
     * Get all registered models
     */
    public static function models(): array
    {
        return static::$models;
    }

    /**
     * Get all registered custom pages
     */
    public static function pages(): array
    {
        return static::$pages;
    }

    /**
     * Get all items (models + pages) for menu building
     */
    public static function all(): array
    {
        $items = [];

        // Add models
        foreach (static::$models as $modelClass) {
            if (method_exists($modelClass, 'adminMenuConfig')) {
                $items[] = $modelClass::adminMenuConfig();
            }
        }

        // Add custom pages
        foreach (static::$pages as $page) {
            $items[] = static::pageToMenuItem($page);
        }

        return $items;
    }

    /**
     * Convert page config to menu item format
     */
    protected static function pageToMenuItem(array $page): array
    {
        return [
            'label' => $page['label'],
            'url' => route('admin.' . $page['route_name']),
            'icon' => $page['icon'],
            'order' => $page['order'],
            'resource_name' => $page['id'],
            'parent' => $page['parent'],
            'children' => [],
        ];
    }

    /**
     * Register routes for all items
     * To be called from routes/admin.php inside the Route group
     */
    public static function registerRoutes(): void
    {
        // Register model routes
        foreach (static::$models as $modelClass) {
            if (method_exists($modelClass, 'registerAdminRoutes')) {
                $modelClass::registerAdminRoutes();
            }
        }

        // Register custom page routes
        foreach (static::$pages as $page) {
            static::registerPageRoute($page);
        }
    }

    /**
     * Register a single page route
     */
    protected static function registerPageRoute(array $page): void
    {
        $methods = $page['route_methods'];
        $path = $page['route_path'];
        $name = $page['route_name'];

        // Build route action
        if ($page['controller']) {
            // Use controller method
            $action = $page['controller'];
        } elseif ($page['view']) {
            // Use view directly
            $action = function () use ($page) {
                return view($page['view']);
            };
        } else {
            throw new \RuntimeException("Page {$page['id']} must have either 'controller' or 'view'");
        }

        // Register route
        \Illuminate\Support\Facades\Route::match($methods, $path, $action)
            ->name($name);
    }

    /**
     * Auto-discover and register all models with AdminResourceTrait
     * Called during bootstrap
     */
    public static function discoverModels(): void
    {
        $modelsPath = app_path('Models');
        
        if (!is_dir($modelsPath)) {
            return;
        }

        $files = \Illuminate\Support\Facades\File::files($modelsPath);
        
        foreach ($files as $file) {
            $className = "App\\Models\\" . $file->getFilenameWithoutExtension();
            
            if (class_exists($className)) {
                $uses = class_uses_recursive($className);
                
                if (in_array("App\\Traits\\AdminResourceTrait", $uses)) {
                    static::registerModel($className);
                }
            }
        }
    }

    /**
     * Clear all registered items (useful for testing)
     */
    public static function clear(): void
    {
        static::$models = [];
        static::$pages = [];
    }
}
