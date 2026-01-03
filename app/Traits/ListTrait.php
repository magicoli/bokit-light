<?php

namespace App\Traits;

use App\Support\DataList;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

trait ListTrait
{
    use ModelConfigTrait;

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

        // If items provided, use them directly (no pagination/filters)
        if ($items !== null) {
            $list->items($items);
            if ($routePrefix) {
                $list->routePrefix($routePrefix);
            }
            return $list;
        }

        // Get configuration
        $config = static::getConfig();

        // Build query
        $query = static::forUser();

        // Apply search
        $search = request("search", "");
        if ($search) {
            $searchable = $config["searchable"];
            if (!empty($searchable)) {
                $query->where(function ($q) use ($searchable, $search) {
                    foreach ($searchable as $col) {
                        $q->orWhere($col, "like", "%{$search}%");
                    }
                });
            }
        }

        // Apply filters
        $filters = static::getFilters();
        foreach (array_keys($filters) as $col) {
            $val = request("filter_{$col}");
            if ($val !== null && $val !== "") {
                $query->where($col, $val);
            }
        }

        // Apply sorting
        $sortCol = request("sort", "id");
        $sortDir = request("dir", "asc");
        if (in_array($sortDir, ["asc", "desc"])) {
            $sortable = $config["sortable"];
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
            ->setSort($sortCol, $sortDir)
            ->setFilters($filters);
        // ->setSearchable($config['searchable'])
        // ->setSortable($config['sortable'])

        // Set route prefix if provided
        if ($routePrefix) {
            $list->routePrefix($routePrefix);
        }

        return $list;
    }

    public static function listColumns(): array
    {
        $config = static::getConfig();
        $fillable = $config["fillable"];
        $appends = $config["appends"] ?? [];
        $columns = empty($config["list_columns"])
            ? array_merge($fillable, $appends)
            : $config["list_columns"];
        $classSlug = $config["classSlug"];

        if (empty($columns)) {
            return [];
        }

        // Exclude columns prefixed with raw_ or suffixed with id or uid
        $keys = array_filter($columns, function ($column) {
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
                    "label" => __("{$classSlug}.column_{$key}"),
                    "sortable" => true,
                ];
            }, $keys),
        );

        return $columns;
    }

    /**
     * Get filterable columns with options
     */
    protected static function getFilters(): array
    {
        $config = static::getConfig();
        $filterable = $config["filterable"];

        if (empty($filterable)) {
            return [];
        }

        $filters = [];
        foreach ($filterable as $columnName) {
            // Get distinct values from forUser query
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
                continue;
            }
        }

        return $filters;
    }
}
