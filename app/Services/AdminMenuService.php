<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

/**
 * AdminMenuService
 * 
 * Collects all models with AdminResourceTrait and provides menu configuration.
 * 
 * Models register themselves by having the trait.
 * No hardcoding in views - single source of truth.
 */
class AdminMenuService
{
    protected array $resources = [];
    protected bool $initialized = false;
    
    /**
     * Get all registered admin resources
     */
    public function getResources(): array
    {
        if (!$this->initialized) {
            $this->discoverResources();
        }
        
        // Sort by order (primary sort key, with gaps for future insertion)
        usort($this->resources, fn($a, $b) => $a['order'] <=> $b['order']);
        
        return $this->resources;
    }
    
    /**
     * Discover all models with AdminResourceTrait
     */
    protected function discoverResources(): void
    {
        $this->initialized = true;
        
        // Scan app/Models directory
        $modelsPath = app_path('Models');
        
        if (!is_dir($modelsPath)) {
            return;
        }
        
        $files = File::files($modelsPath);
        
        foreach ($files as $file) {
            $className = 'App\\Models\\' . $file->getFilenameWithoutExtension();
            
            // Check if class exists and uses AdminResourceTrait
            if (class_exists($className)) {
                $uses = class_uses_recursive($className);
                
                if (in_array('App\\Traits\\AdminResourceTrait', $uses)) {
                    try {
                        $config = $className::adminMenuConfig();
                        
                        // Filter by permissions
                        if ($config['admin_only'] ?? false) {
                            if (!auth()->check() || !auth()->user()->is_admin) {
                                continue;
                            }
                        }
                        
                        $this->resources[] = $config;
                    } catch (\Exception $e) {
                        // Skip models with errors
                        logger()->warning("Failed to load admin config for {$className}: {$e->getMessage()}");
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
            $this->discoverResources();
        }
        
        foreach ($this->resources as $resource) {
            $modelClass = $resource['model_class'] ?? null;
            
            if ($modelClass && method_exists($modelClass, 'registerAdminRoutes')) {
                $modelClass::registerAdminRoutes();
            }
        }
    }
}
