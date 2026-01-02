<?php

namespace App\Traits;

use Illuminate\Support\Str;

/**
 * Centralized model configuration trait
 * 
 * Reads model properties and provides them in a unified array.
 * Used by AdminResourceTrait, ListTrait, FormTrait.
 */
trait ModelConfigTrait
{
    /**
     * Cached configuration for this model
     */
    private static ?array $config = null;

    /**
     * Get complete model configuration from static properties
     * 
     * @return array Configuration with keys:
     *   - fillable: array
     *   - casts: array
     *   - searchable: array (default: ['name'])
     *   - sortable: array (default: all fillable)
     *   - filterable: array (default: ['status'])
     *   - capability: string (default: 'manage')
     *   - classSlug: string
     *   - classBasename: string
     */
    public static function getConfig(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $class = static::class;
        $reflection = new \ReflectionClass($class);
        $defaults = $reflection->getDefaultProperties();

        // Basic class info
        $classBasename = class_basename($class);
        $classSlug = Str::slug($classBasename);

        // Get properties
        $fillable = $defaults['fillable'] ?? [];
        $casts = $defaults['casts'] ?? [];
        $searchable = $defaults['searchable'] ?? ['name'];
        $sortable = $defaults['sortable'] ?? $fillable;
        $filterable = $defaults['filterable'] ?? ['status'];
        $capability = $defaults['capability'] ?? 'manage';

        // Validate against fillable
        $searchable = array_values(array_intersect($searchable, $fillable));
        $sortable = array_values(array_intersect($sortable, $fillable));
        $filterable = array_values(array_intersect($filterable, $fillable));

        self::$config = [
            'fillable' => $fillable,
            'casts' => $casts,
            'searchable' => $searchable,
            'sortable' => $sortable,
            'filterable' => $filterable,
            'capability' => $capability,
            'classSlug' => $classSlug,
            'classBasename' => $classBasename,
        ];

        return self::$config;
    }

    /**
     * Clear cached configuration (for testing)
     */
    public static function clearConfig(): void
    {
        self::$config = null;
    }
}
