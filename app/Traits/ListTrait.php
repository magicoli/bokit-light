<?php

namespace App\Traits;

use App\Support\DataList;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

trait ListTrait
{
    protected static $columns = [];
    protected static $searchable = ["name"];
    protected static $filterable = ["status"];
    protected static $sortable = ["id", "name", "status"];

    static function initControls()
    {
        // Make sure $searchable, $fitlerable and $sortable only  contain defined fillables
        $fillable = new \ReflectionClass(static::class)->getDefaultProperties()[
            "fillable"
        ];
        static::$searchable = array_intersect(static::$searchable, $fillable);
        static::$filterable = array_intersect(static::$filterable, $fillable);
        static::$sortable = array_intersect(static::$sortable, $fillable);
    }

    /**
     * Get a list instance for this model collection
     */
    public static function list(
        ?Collection $items = null,
        ?string $routePrefix = null,
    ): DataList {
        static::initControls();

        // Create an empty model instance for DataList
        $instance = new static();

        // Create DataList with the model instance
        $list = new DataList($instance);

        // If items provided, use them directly (no pagination/filters)
        if ($items !== null) {
            $list->items($items);
            if ($routePrefix) {
                $list->routePrefix($routePrefix);
            }
            return $list;
        }

        // Build query
        $query = static::forUser();

        // Apply search
        $search = request("search", "");
        if ($search) {
            $searchable = static::$searchable;
            if (!empty($searchable)) {
                $query->where(function ($q) use ($searchable, $search) {
                    foreach ($searchable as $col) {
                        $q->orWhere($col, "like", "%{$search}%");
                    }
                });
            }
        }

        // Apply filters
        $filterable = static::$filterable;
        foreach (array_keys($filterable) as $col) {
            $val = request("filter_{$col}");
            if ($val !== null && $val !== "") {
                $query->where($col, $val);
            }
        }

        // Apply sorting
        $sortCol = request("sort", "id");
        $sortDir = request("dir", "asc");
        if (in_array($sortDir, ["asc", "desc"])) {
            $sortable = static::$sortable;
            if (in_array($sortCol, $sortable)) {
                $query->orderBy($sortCol, $sortDir);
            }
        }

        // Paginate
        $perPage = min(100, max(10, (int) request("per_page", 25)));
        $paginator = $query->paginate($perPage)->withQueryString();

        // Set items and metadata
        $list
            ->items(collect($paginator->items()))
            ->setPaginator($paginator)
            ->setSearch($search)
            ->setFilters($filterable)
            ->setCurrentFilters(
                array_filter(
                    request()->only(
                        array_map(
                            fn($c) => "filter_{$c}",
                            array_keys($filterable),
                        ),
                    ),
                ),
            )
            ->setSort($sortCol, $sortDir);

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
                    "password",
                    "auth_provider",
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

    /**
     * Get filterable columns with options (override in model with protected static $filterable)
     */
    protected static function getFilters(): array
    {
        if (empty(static::$filterable)) {
            return [];
        }

        $filters = [];
        foreach (static::$filterable as $columnName) {
            // Get distinct status values from forUser query
            try {
                $values = static::forUser()
                    ->distinct()
                    ->pluck($columnName)
                    ->filter()
                    ->sort();
                if ($values->isEmpty()) {
                    return [];
                }
                $filters[$columnName] = $values
                    ->mapWithKeys(fn($v) => [$v => ucfirst($v)])
                    ->toArray();
            } catch (\Exception $e) {
                return [];
            }
        }

        return $filters;
    }
}
