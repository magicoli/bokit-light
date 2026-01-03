<?php

namespace App\Support;

use App\Traits\TimezoneTrait;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;

class DataList
{
    use TimezoneTrait;

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

        // Load searchable/sortable/filters from model config
        if (method_exists($modelClass, "getConfig")) {
            $config = $modelClass::getConfig();
            $this->searchable = $config["searchable"] ?? [];
            $this->sortable = $config["sortable"] ?? [];
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

    // /**
    //  * Set searchable columns
    //  */
    // public function setSearchable(array $searchable): self
    // {
    //     $this->searchable = $searchable;
    //     return $this;
    // }

    // /**
    //  * Set sortable columns
    //  */
    // public function setSortable(array $sortable): self
    // {
    //     $this->sortable = $sortable;
    //     return $this;
    // }

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

        try {
            $fields = [];

            // Search field
            if (!empty($this->searchable)) {
                $fields["search"] = [
                    "type" => "text",
                    "label" => __("forms.search"),
                    "placeholder" => implode(", ", $this->searchable),
                    "default" => "",
                ];
            }

            $modelClass = get_class($this->model);
            $classSlug = strtolower(class_basename($modelClass));

            // Filter fields
            foreach ($this->filters as $column => $options) {
                $columnName = str_replace(
                    "$classSlug.field.",
                    "",
                    __("$classSlug.field.{$column}"),
                );
                $fields["filter_{$column}"] = [
                    "type" => "select",
                    "label" => $columnName,
                    "options" =>
                        [
                            "" => __("forms.filter_field.name", [
                                "field.name" => $columnName,
                            ]),
                        ] + $options,
                    "default" => "",
                ];
            }

            // Per page selector (if paginator exists)
            // TODO: if nr of pages is 1, disable navigation buttons
            if ($this->paginator) {
                $fields["paginator"] = $this->paginatorField();
            }

            // Collect field names for request values BEFORE wrapping
            $fieldNames = array_keys($fields);
            if (isset($fields["paginator"]["items"])) {
                $fieldNames = array_merge(
                    $fieldNames,
                    array_keys($fields["paginator"]["items"]),
                );
                // Remove 'paginator' from field names as it's a container
                $fieldNames = array_diff($fieldNames, ["paginator"]);
            }

            $fields = [
                "control-row" => [
                    "type" => "fields-row",
                    "items" => $fields,
                ],
            ];

            // Get current values from request using collected field names
            $values = request()->only($fieldNames);

            // Create form
            $form = new Form($values, fn() => $fields, request()->url());
            $form->method("GET")->submitButton(__("forms.submit"));
            return $form;
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            // Handle exception
            return null;
        }
    }

    /**
     * Format value based on column configuration
     *
     * Simplified: Get accessor/column value and format only basic types
     */
    private function formatValue(
        $item,
        string $columnKey,
        array $column,
    ): string {
        $format = $column["format"] ?? "auto";

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

        // If value is null, return empty string
        if ($value === null) {
            return "";
        }

        // If format is explicit, use it
        if ($format !== "auto") {
            return match ($format) {
                "boolean" => $value ? "✓" : "✗",
                "integer" => self::formatInteger($value),
                "decimal" => self::formatDecimal($value, 2),
                "currency" => self::formatDecimal($value, 2),
                "date" => $this->formatDate($value),
                "datetime" => $this->formatDateTime($value),
                "array" => self::formatArray($value),
                default => (string) $value,
            };
        }

        // Auto-detect format from model casts
        if ($this->model) {
            $modelClass = get_class($this->model);
            if (method_exists($modelClass, "getConfig")) {
                $config = $modelClass::getConfig();
                $casts = $config["casts"] ?? [];

                if (isset($casts[$columnKey])) {
                    $castType = $casts[$columnKey];

                    return match (true) {
                        str_starts_with($castType, "date") => $this->formatDate(
                            $value,
                        ),
                        $castType === "datetime" => $this->formatDateTime(
                            $value,
                        ),
                        str_starts_with($castType, "decimal")
                            => self::formatDecimal($value, 2),
                        in_array($castType, ["int", "integer"])
                            => self::formatInteger($value),
                        in_array($castType, ["float", "double"])
                            => self::formatDecimal($value, 2),
                        in_array($castType, ["bool", "boolean"]) => $value
                            ? "✓"
                            : "✗",
                        $castType === "array" => self::formatArray($value),
                        default => (string) $value,
                    };
                }
            }
        }

        // No cast defined - detect from PHP type
        return match (true) {
            is_bool($value) => $value ? "✓" : "✗",
            is_int($value) => self::formatInteger($value),
            is_float($value) => self::formatDecimal($value, 2),
            is_array($value) => self::formatArray($value),
            is_object($value) && method_exists($value, "format")
                => $this->formatDate($value),
            default => (string) $value,
        };
    }

    /**
     * Format helpers - delegate to ModelConfigTrait
     */
    private static function formatInteger($value): string
    {
        return \App\Traits\ModelConfigTrait::formatInteger($value);
    }

    private static function formatDecimal($value, int $decimals = 2): string
    {
        return \App\Traits\ModelConfigTrait::formatDecimal($value, $decimals);
    }

    private static function formatArray(
        $value,
        string $separator = ", ",
    ): string {
        return \App\Traits\ModelConfigTrait::formatArray($value, $separator);
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
