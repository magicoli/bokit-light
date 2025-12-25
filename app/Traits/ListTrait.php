<?php

namespace App\Traits;

use App\Support\DataList;
use Illuminate\Database\Eloquent\Collection;

trait ListTrait
{
    /**
     * Get a list instance for this model collection
     */
    public static function list(?Collection $items = null, ?string $routePrefix = null): DataList
    {
        // Create an empty model instance for DataList
        $instance = new static();
        
        // Create DataList with the model instance
        $list = new DataList($instance);
        
        // Set items (default to all if not provided)
        $list->items($items ?? static::all());
        
        // Set route prefix if provided
        if ($routePrefix) {
            $list->routePrefix($routePrefix);
        }
        
        return $list;
    }
}
