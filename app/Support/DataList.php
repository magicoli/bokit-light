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
    private array $searchable = [];
    private array $sortable = [];
    private array $filters = [];
    private string $search = "";
    private string $sortColumn = "";
    private string $sortDirection = "asc";

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
     * Paginator fields
     */
    public function paginatorField(): array
    {
        if (!$this->paginator ?? false) {
            return [];
        }
        $field = [
            "type" => "input-group",
            "class" => "paginator",
            "label" => __("forms.showing_x_y_of_z", [
                "first" => $this->paginator->firstItem(),
                "last" => $this->paginator->lastItem(),
                "total" => $this->paginator->total(),
            ]),
            "items" => [
                "first" => [
                    "type" => "link",
                    "label" => "",
                    "icon" => icon("skip-back"),
                    "attributes" => [
                        // "disabled" => $this->paginator->onFirstPage(),
                        "href" => $this->paginator->url(1),
                    ],
                ],
                "previous" => [
                    "type" => "link",
                    "label" => "",
                    "icon" => icon("chevron-left"),
                    "attributes" => [
                        // "disabled" => $this->paginator->onFirstPage(),
                        "href" => $this->paginator->previousPageUrl(),
                    ],
                ],
                "per_page" => [
                    "type" => "select",
                    "label" => "",
                    "options" => [
                        10 => "10",
                        25 => "25",
                        50 => "50",
                        100 => "100",
                    ],
                    "default" => 25,
                ],
                "next" => [
                    "type" => "link",
                    "label" => "",
                    "icon" => icon("chevron-right"),
                    "attributes" => [
                        // "disabled" => $this->paginator->hasMorePages(),
                        "href" => $this->paginator->nextPageUrl(),
                    ],
                ],
                "last" => [
                    "type" => "link",
                    "label" => "",
                    "icon" => icon("skip-forward"),
                    "attributes" => [
                        // "disabled" => $this->paginator->hasMorePages(),
                        "href" => $this->paginator->url(
                            $this->paginator->lastPage(),
                        ),
                    ],
                ],
            ],
        ];
        return $field;
    }

    /**
     * Set searchable columns
     */
    public function setSearchable(array $searchable): self
    {
        $this->searchable = $searchable;
        return $this;
    }

    /**
     * Set sortable columns
     */
    public function setSortable(array $sortable): self
    {
        $this->sortable = $sortable;
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
     * Set sort column and direction
     */
    public function setSort(string $column, string $direction = "asc"): self
    {
        $this->sortColumn = $column;
        $this->sortDirection = $direction;
        return $this;
    }

    /**
     * Create the controls form (search, filters, per_page)
     */
    private function createControlsForm(): ?Form
    {
        // No controls if no searchable, no filters, and no paginator
        if (
            empty($this->searchable) &&
            empty($this->filters) &&
            !$this->paginator
        ) {
            return null;
        }

        $fields = [];

        // Search field
        if (!empty($this->searchable)) {
            $fields["search"] = [
                "type" => "text",
                "label" => null,
                "placeholder" => __("forms.search"),
                "default" => "",
            ];
        }

        // Filter fields
        foreach ($this->filters as $column => $options) {
            $fields["filter_{$column}"] = [
                "type" => "select",
                "label" => null,
                "options" => ["" => __("forms.all_{$column}")] + $options,
                "default" => "",
            ];
        }

        // Per page selector (if paginator exists)
        // TODO: if nr of pages is 1, disable navigation buttons
        if ($this->paginator) {
            $fields["paginator"] = $this->paginatorField();
        }

        $fields = [
            "control-row" => [
                "type" => "fields-row",
                "items" => $fields,
            ],
        ];

        // Get current values from request
        $values = request()->only(array_keys($fields));

        // Create form
        $form = new Form($values, fn() => $fields, request()->url());
        $form->method("GET")->submitButton(__("forms.submit"));

        return $form;
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

        // Create controls form
        $controlsForm = $this->createControlsForm();

        return view("components.data-list", [
            "items" => $this->items,
            "columns" => $this->columns,
            "routePrefix" => $this->routePrefix,
            "groupBy" => $this->groupBy,
            "controlsForm" => $controlsForm,
            "paginator" => $this->paginator,
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
