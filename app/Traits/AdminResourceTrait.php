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
        $resourceName = $config['resource_name'] ?? static::getResourceName();
        $controllerClass = $config['controller'] ?? static::getControllerClass();
        
        // List
        if (in_array('list', $config['routes'] ?? [])) {
            Route::get("/{$resourceName}", [$controllerClass, 'index'])
                ->name("{$resourceName}.index");
        }
        
        // Add
        if (in_array('add', $config['routes'] ?? [])) {
            Route::get("/{$resourceName}/create", [$controllerClass, 'create'])
                ->name("{$resourceName}.create");
            Route::post("/{$resourceName}", [$controllerClass, 'store'])
                ->name("{$resourceName}.store");
        }
        
        // Edit
        if (in_array('edit', $config['routes'] ?? [])) {
            Route::get("/{$resourceName}/{id}/edit", [$controllerClass, 'edit'])
                ->name("{$resourceName}.edit");
            Route::put("/{$resourceName}/{id}", [$controllerClass, 'update'])
                ->name("{$resourceName}.update");
            Route::delete("/{$resourceName}/{id}", [$controllerClass, 'destroy'])
                ->name("{$resourceName}.destroy");
        }
        
        // Settings
        if (in_array('settings', $config['routes'] ?? [])) {
            Route::get("/{$resourceName}/settings", [$controllerClass, 'settings'])
                ->name("{$resourceName}.settings");
            Route::post("/{$resourceName}/settings", [$controllerClass, 'saveSettings'])
                ->name("{$resourceName}.settings.save");
        }
        
        // Custom routes
        if (isset($config['custom_routes'])) {
            $config['custom_routes']($resourceName, $controllerClass);
        }
    }
    
    /**
     * Get admin menu configuration
     */
    public static function adminMenuConfig(): array
    {
        $config = static::adminConfig();
        $resourceName = $config['resource_name'] ?? static::getResourceName();
        
        return [
            'model_class' => static::class,
            'label' => $config['label'] ?? ucfirst($resourceName),
            'icon' => $config['icon'] ?? '📁',
            'order' => $config['order'] ?? 100,
            'admin_only' => $config['admin_only'] ?? false,
            'routes' => $config['routes'] ?? ['list', 'add', 'settings'],
            'resource_name' => $resourceName,
        ];
    }
    
    /**
     * Scope query to current user's resources (unless admin)
     */
    public function scopeOwnedByCurrentUser(Builder $query): Builder
    {
        $user = auth()->user();
        
        if (!$user) {
            return $query->whereRaw('1 = 0'); // No results
        }
        
        if ($user->is_admin) {
            return $query; // Admins see everything
        }
        
        // Owner-based filtering
        return $query->where('owner_id', $user->id);
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
     * Get controller class name
     */
    protected static function getControllerClass(): string
    {
        $className = class_basename(static::class);
        return "App\\Http\\Controllers\\{$className}Controller";
    }
    
    /**
     * Admin configuration - override in model
     */
    public static function adminConfig(): array
    {
        return [
            'label' => ucfirst(static::getResourceName()),
            'icon' => '📁',
            'routes' => ['list', 'add', 'edit', 'settings'],
            'order' => 100,
            'admin_only' => false,
        ];
    }
}
