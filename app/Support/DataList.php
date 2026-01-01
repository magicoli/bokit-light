<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;

class DataList
{
    private ?Model $model = null;
    private Collection $items;
    private ?string $routePrefix = null;
    private array $columns = [];
    private ?string $groupBy = null;
    private $paginator = null;
    private string $search = '';
    private array $filters = [];
    private array $currentFilters = [];
    private string $sortColumn = '';
    private string $sortDirection = 'asc';

    /**
     * Constructor accepts Model, Collection, or array
     */
    public function __construct(
        Model|Collection|array $data,
        ?string $routePrefix = null,
    ) {
        // Handle different input types
        if ($data instanceof Model) {
            $this->model = $data;
            $this->items = new Collection();
            $this->loadColumnsFromModel();
        } elseif ($data instanceof Collection) {
            $this->items = $data;
        } elseif (is_array($data)) {
            $this->items = collect($data);
        }

        if ($routePrefix) {
            $this->routePrefix = $routePrefix;
        }
    }

    /**
     * Load columns configuration from model
     */
    private function loadColumnsFromModel(): void
    {
        if (!$this->model) {
            return;
        }

        $modelClass = get_class($this->model);

        if (method_exists($modelClass, "listColumns")) {
            $this->columns = $modelClass::listColumns();
        }
    }

    /**
     * Set items to display
     */
    public function items(Collection|array $items): self
    {
        $this->items = $items instanceof Collection ? $items : collect($items);
        return $this;
    }

    /**
     * Set columns manually
     */
    public function columns(array $columns): self
    {
        $this->columns = $columns;
        return $this;
    }

    /**
     * Set route prefix for actions
     */
    public function routePrefix(string $prefix): self
    {
        $this->routePrefix = $prefix;
        return $this;
    }

    /**
     * Group results by a field
     */
    public function groupBy(string $field): self
    {
        $this->groupBy = $field;
        return $this;
    }

    /**
     * Set paginator
     */
    public function setPaginator($paginator): self
    {
        $this->paginator = $paginator;
        return $this;
    }

    /**
     * Set search term
     */
    public function setSearch(string $search): self
    {
        $this->search = $search;
        return $this;
    }

    /**
     * Set available filters (column => [value => label])
     */
    public function setFilters(array $filters): self
    {
        $this->filters = $filters;
        return $this;
    }

    /**
     * Set current filter values
     */
    public function setCurrentFilters(array $current): self
    {
        $this->currentFilters = $current;
        return $this;
    }

    /**
     * Set sort column and direction
     */
    public function setSort(string $column, string $direction = 'asc'): self
    {
        $this->sortColumn = $column;
        $this->sortDirection = $direction;
        return $this;
    }

    /**
     * Format value based on column configuration
     */
    private function formatValue(
        $item,
        string $columnKey,
        array $column,
    ): string {
        $format = $column["format"] ?? "text";

        // Custom formatter
        if ($format === "custom" && isset($column["formatter"])) {
            return (string) $column["formatter"]($item);
        }

        // Get value from item (handle both objects and arrays)
        if (is_object($item)) {
            $value = $item->$columnKey ?? null;
        } elseif (is_array($item)) {
            $value = $item[$columnKey] ?? null;
        } else {
            $value = null;
        }

        return match ($format) {
            "boolean" => $value ? "✓" : "✗",
            "currency" => number_format($value, 2),
            "date" => $value ? $value->format("Y-m-d") : "",
            "datetime" => $value ? $value->format("Y-m-d H:i") : "",
            default => is_string($value)
                ? $value
                : (is_array($value)
                    ? implode(", ", $value)
                    : ""),
        };
    }

    /**
     * Render the list using Blade view
     */
    public function render(): string
    {
        if ($this->items->isEmpty() && !$this->model) {
            return __("app.empty_list");
            // No items and no model - can still render empty state
        }
        if (empty($this->columns)) {
            return __("app.error_columns_not_set");
        }

        return view("components.data-list", [
            "items" => $this->items,
            "columns" => $this->columns,
            "routePrefix" => $this->routePrefix,
            "groupBy" => $this->groupBy,
            "model" => $this->model,
            "paginator" => $this->paginator,
            "search" => $this->search,
            "filters" => $this->filters,
            "currentFilters" => $this->currentFilters,
            "sortColumn" => $this->sortColumn,
            "sortDirection" => $this->sortDirection,
            "formatValue" => fn(
                $item,
                $columnKey,
                $column,
            ) => $this->formatValue($item, $columnKey, $column),
        ])->render();
    }
}
