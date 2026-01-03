<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Form
{
    private ?Model $model = null;
    private array $values = [];
    private ?string $action = null;
    private string $method = "POST";
    private array $fields = [];
    private array $fieldOptions = [];
    private $fieldsCallback;
    private array $buttons = [];

    /**
     * @param Model|array|null $data Model instance, array of values, or null
     * @param callable|string|array $fieldsCallback Method name, callable, or array [class, method]
     * @param string|null $action Form action URL
     */
    public function __construct($data, $fieldsCallback, ?string $action = null)
    {
        // Handle different data types
        if ($data instanceof Model) {
            $this->model = $data;
        } elseif (is_array($data)) {
            $this->values = $data;
        } elseif ($data !== null) {
            throw new \InvalidArgumentException(
                "Form data must be a Model, array, or null",
            );
        }

        $this->fieldsCallback = $fieldsCallback;
        $this->action = $action;
        $this->loadFields($fieldsCallback);

        // Default buttons: reset + submit
        $this->buttons = [
            "reset" => [
                "label" => __("forms.reset"),
                "type" => "reset",
                "class" => "button secondary",
            ],
            "submit" => [
                "label" => __("forms.save"),
                "type" => "submit",
                "class" => "button primary ms-auto",
            ],
        ];
    }

    /**
     * Load fields from callback
     */
    private function loadFields($callback): void
    {
        if (is_string($callback)) {
            // Method on model class (or static method if no model)
            if ($this->model) {
                $modelClass = get_class($this->model);
                if (!method_exists($modelClass, $callback)) {
                    throw new \BadMethodCallException(
                        "Method {$callback} does not exist on {$modelClass}",
                    );
                }
                $this->fields = $modelClass::$callback();
            } else {
                throw new \InvalidArgumentException(
                    "Cannot use string method callback without a Model",
                );
            }
        } elseif (is_array($callback)) {
            // [class, method] or [$object, method]
            if (!is_callable($callback)) {
                throw new \InvalidArgumentException(
                    "Array callback must be callable [class, method]",
                );
            }
            $this->fields = call_user_func($callback);
        } elseif (is_callable($callback)) {
            // Direct callable/closure
            $this->fields = $callback();
        } else {
            throw new \InvalidArgumentException(
                "Fields callback must be a string method name, array [class, method], or callable",
            );
        }
    }

    /**
     * Set form action URL
     */
    public function action(string $action): self
    {
        $this->action = $action;
        return $this;
    }

    /**
     * Set form method
     */
    public function method(string $method): self
    {
        $this->method = strtoupper($method);
        return $this;
    }

    /**
     * Set values (for forms without model)
     */
    public function values(array $values): self
    {
        $this->values = $values;
        return $this;
    }

    /**
     * Set options for a specific field
     */
    public function fieldOptions(string $fieldName, array $options): self
    {
        $this->fieldOptions[$fieldName] = $options;
        return $this;
    }

    /**
     * Set submit button label
     */
    public function submitButton(string $label): self
    {
        $this->buttons["submit"]["label"] = $label;
        return $this;
    }

    /**
     * Set all buttons at once
     *
     * @param array $buttons Format: ['submit' => ['label' => '...', 'type' => '...', 'class' => '...']]
     */
    public function buttons(array $buttons): self
    {
        $this->buttons = $buttons;
        return $this;
    }

    /**
     * Add a button
     */
    public function addButton(
        string $key,
        string $label,
        array $attributes = [],
    ): self {
        $this->buttons[$key] = array_merge(
            [
                "label" => $label,
                "type" => "button",
                "class" => "button",
            ],
            $attributes,
        );
        return $this;
    }

    /**
     * Remove reset button
     */
    public function withoutReset(): self
    {
        unset($this->buttons["reset"]);
        return $this;
    }

    /**
     * Add/override reset button (already included by default)
     */
    public function withReset(string $label = null): self
    {
        $this->buttons["reset"] = [
            "label" => $label ?? __("forms.reset"),
            "type" => "reset",
            "class" => "button secondary",
        ];
        return $this;
    }

    /**
     * Render a single field to HTML
     */
    private function renderField(string $fieldName, array $field): string
    {
        try {
            // Get model name for translations
            $modelName = $this->model
                ? strtolower(class_basename($this->model))
                : "edit";

            // Complete field configuration
            $field["name"] = $field["name"] ?? $fieldName;
            $field["id"] = $field["id"] ?? $fieldName;

            // Get value: use existing value if already set (from parent), otherwise get from model
            if (!isset($field["value"])) {
                $modelValue = $this->model
                    ? $this->model->$fieldName ?? null
                    : $this->values[$fieldName] ?? null;
                $field["value"] = old(
                    $fieldName,
                    $modelValue ?? ($field["default"] ?? null),
                );
            }

            // Determine type from $casts if not specified (source of truth)
            if (!isset($field["type"])) {
                if (
                    $this->model &&
                    isset($this->model->getCasts()[$fieldName])
                ) {
                    $cast = $this->model->getCasts()[$fieldName];
                    // Use cast as-is, the switch below will handle it
                    $field["type"] = $cast;
                } else {
                    // Default to text like HTML
                    $field["type"] = "text";
                }
            }

            // Default values for common properties
            $field["label"] =
                $field["label"] ?? __("$modelName.field.{$field["name"]}");
            $field["default"] = $field["default"] ?? null;
            $field["attributes"] = $field["attributes"] ?? [];
            $field["options"] =
                $this->fieldOptions[$fieldName] ?? ($field["options"] ?? []);
            $field["description"] = $field["description"] ?? null;
            $field["placeholder"] =
                $field["placeholder"] ??
                ($field["attributes"]["placeholder"] ?? null);

            // Build CSS classes
            $fieldsetClass = "form-field field-{$field["type"]} field-{$fieldName}";
            if (isset($field["class"])) {
                $fieldsetClass .= " " . $field["class"];
            }

            $inputClass = "input-{$field["type"]}";
            if (isset($field["attributes"]["class"])) {
                $inputClass .= " " . $field["attributes"]["class"];
            }

            // Handle required/checked/disabled/readonly attributes
            if ($field["required"] ?? false) {
                $field["attributes"]["required"] = true;
            }
            if ($field["checked"] ?? false) {
                $field["attributes"]["checked"] = true;
            }
            if ($field["attributes"]["disabled"] ?? false) {
                $field["disabled"] = true;
                $fieldsetClass .= " disabled";
            }
            if ($field["readonly"] ?? false) {
                $field["attributes"]["readonly"] = true;
            }

            // Extract decimal precision before type conversion (e.g., "decimal:2")
            $decimalPrecision = null;
            if (preg_match('/^decimal:(\d+)$/', $field["type"], $matches)) {
                $decimalPrecision = (int) $matches[1];
            }

            // Container types
            $field["isContainer"] = in_array($field["type"], [
                "html",
                "section",
                "fields-row",
                "fields-group",
                "input-group",
            ]);

            // Container element (input, textarea, a, span, etc.)
            $field["container"] = "input";

            $field["attributes"]["data-type"] = $field["type"];

            // Type-specific adjustments
            if (!$field["isContainer"]) {
                // Handle decimal:N patterns
                if (preg_match('/^decimal:\d+$/', $field["type"])) {
                    $field["type"] = "decimal";
                }

                switch ($field["type"]) {
                    // Cast aliases - convert to HTML types
                    case "boolean":
                    case "bool":
                        $field["type"] = "checkbox";
                        if (old($fieldName, $field["default"]) == "1") {
                            $field["attributes"]["checked"] = true;
                        } else {
                            unset($field["attributes"]["checked"]);
                        }
                        $field["value"] = 1;
                        break;

                    case "integer":
                    case "int":
                        $field["size"] = $field["size"] ?? 6;
                        $field["type"] = "number";
                        break;

                    case "decimal":
                    case "float":
                    case "double":
                        // Use extracted precision or default to 2
                        $precision = $decimalPrecision ?? 2;
                        $step = "0." . str_repeat("0", $precision - 1) . "1";
                        $field["attributes"]["step"] =
                            $field["attributes"]["step"] ?? $step;
                        $field["size"] = $field["size"] ?? 8;
                        $field["type"] = "number";
                        break;

                    case "datetime":
                    case "timestamp":
                        $field["type"] = "date";
                        // $field["type"] = "datetime-local";
                        break;

                    case "textarea":
                        $field["container"] = "textarea";
                        break;

                    case "checkbox":
                    case "switch":
                        $field["type"] = "checkbox";
                        if (old($fieldName, $field["default"]) == "1") {
                            $field["attributes"]["checked"] = true;
                        } else {
                            unset($field["attributes"]["checked"]);
                        }
                        $field["value"] = 1;
                        break;

                    case "date":
                    case "date-range":
                        if ($field["type"] === "date-range") {
                            $field["attributes"]["flatpickr-mode"] = "range";
                        }
                        $field["type"] = "text";
                        $inputClass = trim("flatpickr-input {$inputClass}");
                        break;

                    case "link":
                        if ($field["disabled"] ?? false) {
                            $field["container"] = "span";
                            unset($field["attributes"]["href"]);
                        } else {
                            $field["container"] = "a";
                        }
                        break;

                    case "array":
                        $values = is_string($field["value"])
                            ? json_decode($field["value"], true)
                            : $field["value"];
                        $field["description"] =
                            "DEBUG: " . var_export($values, true);
                        // If associative array (has string keys), make it a fields-group
                        if (
                            is_array($values) &&
                            array_keys($values) !== range(0, count($values) - 1)
                        ) {
                            $field["type"] = "fields-group";
                            $field["items"] = [];

                            foreach ($values as $key => $value) {
                                $subFieldName = $fieldName . "." . $key;

                                $field["items"][$subFieldName] = [
                                    "label" => __(
                                        "$modelName.field.$subFieldName",
                                    ),
                                    "value" => $value,
                                ];
                            }
                            $field["isContainer"] = true;
                        } else {
                            // Indexed array: convert to textarea, one value per line
                            $field["type"] = "textarea";
                            $field["container"] = "textarea";
                            $field["value"] = is_array($values)
                                ? implode("\n", $values)
                                : $values;
                        }
                        $field["description"] =
                            "DEBUG: {$field["type"]} " .
                            var_export($values, true);
                        break;

                    case "json":
                        $field["type"] = "textarea";
                        break;

                    default:
                        break;
                }
            }

            // Field size
            if (isset($field["attributes"]["size"]) || isset($field["size"])) {
                $fieldSize = $field["attributes"]["size"] ?? $field["size"];
                $inputClass .= " w-[{$fieldSize}rem]";
            }

            // Store computed classes
            $field["fieldsetClass"] = $fieldsetClass;
            $field["inputClass"] = $inputClass;

            // Convert attributes to HTML string
            $field["attrs"] = array_to_attrs($field["attributes"]);

            // Sanitize values
            $field["default"] = sanitize_field_value($field["default"]);
            $field["value"] = sanitize_field_value($field["value"]);

            // Recursive rendering for container items
            if (!empty($field["items"])) {
                $field["items_content"] = "";
                foreach ($field["items"] as $key => $item) {
                    $renderedItem = $this->renderField($key, $item);
                    Log::debug("rendering $key", [
                        "subfield" => $item,
                        // "rendered" => $renderedItem,
                    ]);
                    $field["items_content"] .= $renderedItem;
                }
            }

            // Pass complete $field to view
            return view("components.form-field", ["field" => $field])->render();
        } catch (\Throwable $e) {
            Log::error("Field rendering failed", [
                "field" => $fieldName,
                "error" => $e->getMessage(),
                "trace" => $e->getTraceAsString(),
            ]);

            return '<div class="field-error alert alert-danger">' .
                "<strong>" .
                htmlspecialchars($fieldName) .
                ":</strong> " .
                htmlspecialchars($e->getMessage()) .
                "</div>";
        }
    }

    /**
     * Render the form using Blade view
     */
    public function render(): string
    {
        if (!$this->action) {
            throw new \RuntimeException(
                "Form action must be set before rendering. Use action(route(...))",
            );
        }

        // Generate ID from model or generic
        $formId = $this->model
            ? strtolower(class_basename($this->model)) .
                "-" .
                ($this->model->id ?? "new")
            : "form-" . uniqid();

        try {
            // Render all fields in PHP
            $fieldsHtml = "";
            foreach ($this->fields as $fieldName => $field) {
                $fieldsHtml .= $this->renderField($fieldName, $field);
            }

            // Now render the simple wrapper template
            $result = view("components.form", [
                "action" => $this->action,
                "method" => $this->method,
                "formId" => $formId,
                "fieldsHtml" => $fieldsHtml, // Pre-rendered HTML
                "buttons" => $this->buttons,
            ])->render();

            return $result;
        } catch (\Throwable $e) {
            // Log detailed error
            Log::error("Form rendering failed", [
                "error" => $e->getMessage(),
                "file" => $e->getFile(),
                "line" => $e->getLine(),
                "formId" => $formId,
                "fields" => array_keys($this->fields),
                "trace" => $e->getTraceAsString(),
            ]);

            // Return user-friendly error message
            return '<div class="form-error">' .
                '<p class="error">Form rendering error: ' .
                htmlspecialchars($e->getMessage()) .
                "</p>" .
                '<p class="text-sm text-gray-500">Details logged for debugging.</p>' .
                "</div>";
        }
    }
}
