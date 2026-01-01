<?php

namespace App\Traits;

use App\Support\DataList;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

trait ListTrait
{
    protected static $columns = [];

    /**
     * Get a list instance for this model collection
     */
    public static function list(
        ?Collection $items = null,
        ?string $routePrefix = null,
    ): DataList {
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

    public static function listColumns(): array
    {
        $class = static::class;
        $classBasename = class_basename($class);
        $classSlug = Str::slug($classBasename);

        $fillable = new \ReflectionClass($class)->getDefaultProperties()[
            "fillable"
        ];
        if (empty($fillable)) {
            return [];
        }

        // Exclude columns prefixed with raw_ or suffixed with id or uid
        $keys = array_filter($fillable, function ($column) {
            return !str_starts_with($column, "raw_") &&
                !str_starts_with($column, "is_") &&
                !str_ends_with($column, "_id") &&
                !str_ends_with($column, "_uid") &&
                !in_array($column, [
                    "slug",
                    "id",
                    "uid",
                    "settings",
                    "created_at",
                    "updated_at",
                ]);
        });

        $columns = array_combine(
            $keys,
            array_map(function ($key) use ($classSlug) {
                return [
                    "label" => __("${classSlug}.column_${key}"),
                    "sortable" => true,
                ];
            }, $keys),
        );

        return $columns;
    }
}
