<?php

namespace App\Traits;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Exception;

/**
 * Centralized model configuration trait
 *
 * Reads model properties and provides them in a unified array.
 * Used by AdminResourceTrait, ListTrait, FormTrait.
 *
 * Also provides generic formatting helpers for common data types.
 */
trait ModelConfigTrait
{
    /**
     * Cached configuration for this model
     */
    private static ?array $config = null;

    /**
     * Accessor: Calculate actions
     * Reads default actions from model config, generates URLs dynamically
     *
     * @return string|null
     */
    protected function actions(): Attribute
    {
        return Attribute::make(
            get: function () {
                $config = static::getConfig();
                $resourceName = Str::plural(
                    Str::snake($config["classBasename"]),
                );
                $actions = [];
                $sep = " ";

                // Build actions from config
                foreach ($config["actions"] as $key) {
                    switch ($key) {
                        case "status":
                            $actions[$key] = sprintf(
                                '<span class="action-link action-status status-%s" title="%s">%s</span>',
                                $this->status ?? "unknown",
                                __(
                                    "app.status_" .
                                        ($this->status ?? "unknown"),
                                ),
                                $this->getStatusIcon(),
                            );
                            break;

                        case "edit":
                            if (user_can("edit", $this)) {
                                $actions[$key] = sprintf(
                                    '<a href="%s" class="action-link" title="%s">%s</a>',
                                    route(
                                        "admin.{$resourceName}.edit",
                                        $this->id,
                                    ),
                                    __("app.action_edit"),
                                    icon("edit"),
                                );
                            }
                            break;

                        case "view":
                            if (user_can("view", $this)) {
                                $actions[$key] = sprintf(
                                    '<a href="%s" class="action-link" title="%s">%s</a>',
                                    route(
                                        "admin.{$resourceName}.show",
                                        $this->id,
                                    ),
                                    __("app.action_view"),
                                    icon("eye"),
                                );
                            }
                            break;

                        case "ota":
                            // Custom action for Booking model
                            if ($this->ota_url ?? false) {
                                $actions[$key] = sprintf(
                                    '<a href="%s" target="_blank" class="action-link" title="%s">%s</a>',
                                    $this->ota_url,
                                    __("lists.action_ota"),
                                    icon($this->api_source ?? "arrow-up-right"),
                                );
                            }
                            break;
                    }
                }

                return implode($sep, $actions);
            },
        );
    }

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
     *   - actions: array (default: ['status', 'edit', 'view'])
     *   - classSlug: string
     *   - classBasename: string
     */
    public static function getConfig(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        // Laravel config should have handled locale correctly at this stage (not via APP_LOCALE)

        $class = static::class;
        $reflection = new \ReflectionClass($class);
        $defaults = $reflection->getDefaultProperties();

        // Basic class info
        $classBasename = class_basename($class);
        $resourceName = Str::plural(strtolower($classBasename));

        $classSlug = Str::slug($classBasename);

        // Get properties
        $fillable = $defaults["fillable"] ?? [];
        $appends = $defaults["appends"] ?? [];
        $casts = $defaults["casts"] ?? [];

        $valid_columns = array_merge($fillable, $appends);
        $list_columns = empty($defaults["list_columns"])
            ? $fillable
            : $defaults["list_columns"];
        $searchable = $defaults["searchable"] ?? ["name"];
        $sortable = $defaults["sortable"] ?? $fillable;
        $filterable = $defaults["filterable"] ?? ["status"];

        // Validate against fillable
        $searchable = array_values(
            array_intersect($searchable, $valid_columns),
        );
        $sortable = array_values(array_intersect($sortable, $valid_columns));
        $filterable = array_values(
            array_intersect($filterable, $valid_columns),
        );
        $list_columns = array_values(
            array_intersect($list_columns, $valid_columns),
        );

        // Generate editFields if not defined
        $editFields = $defaults["editFields"] ?? null;
        if ($editFields === null) {
            $editFields = [];
            foreach ($fillable as $fieldName) {
                // Skip internal fields
                if (
                    in_array($fieldName, [
                        "id",
                        "created_at",
                        "updated_at",
                        "deleted_at",
                    ])
                ) {
                    continue;
                }

                // $field = self::getFieldConfig($fieldName);
                // if ($field) {
                //     $editFields[$fieldName] = $field;
                // }
                $editFields[$fieldName] = self::getField($fieldName);
            }
        }

        self::$config = [
            "classSlug" => $classSlug,
            "classBasename" => $classBasename,
            "fillable" => $fillable,
            "appends" => $appends,
            "casts" => $casts,
            "list_columns" => $list_columns,
            "editFields" => $editFields,
            "searchable" => $searchable,
            "sortable" => $sortable,
            "filterable" => $filterable,
            "resource_name" => $resourceName,
            "capability" => $defaults["capability"] ?? "manage",
            "actions" => $defaults["actions"] ?? ["status", "view", "edit"],
            "menu" => [
                "parent" => $defaults["parent"] ?? null,
                "label" => $defaults["label"] ?? __("app.$resourceName"),
                "icon" => $defaults["icon"] ?? null,
                "order" => $defaults["order"] ?? 10,
            ],
        ];

        return self::$config;
    }

    /**
     * Set complete field configuration for a given object field.
     *
     * @param string $fieldName The name of the field.
     * @param array|null $field The field configuration.
     * @return array|null The field configuration.
     */
    public function setField($fieldName, $field = [])
    {
        try {
            $field = self::getField($fieldName, $field) ?? [];
        } catch (Exception $e) {
            $field["error"] = $e->getMessage();
            return $field;
        }

        // Get value from model instance if not already set
        if (!isset($field["value"])) {
            $field["value"] = $field["value"] ?? ($this->$fieldName ?? null);
        }

        return $field;
    }

    /**
     * Get complete field configuration for a given field name.
     * STATIC method - returns configuration from $casts, no dynamic values.
     *
     * @param string $fieldName The name of the field.
     * @param array $field Optional field configuration to merge.
     * @return array The field configuration.
     */
    public static function getField(
        string $fieldName,
        array $fieldConfig = [],
    ): array {
        if (isset(self::$config["editFields"][$fieldName])) {
            $field = self::$config["editFields"][$fieldName] ?? [];
            if (!empty($field)) {
                return $field;
            }
        }

        $field = is_array($fieldConfig) ? $fieldConfig : [];

        $class = static::class;
        $reflection = new \ReflectionClass($class);
        $className = $reflection->getShortName();
        $defaults = $reflection->getDefaultProperties();
        $localPrefix = "$className.field.";

        foreach ($defaults as $key => $value) {
            $field[$key] = $field[$key] ?? $value;
        }

        // Make sure name and id are set and equal $fieldName, we probably never need to override it.
        $field["name"] = $field["name"] = $fieldName;
        $field["id"] = $field["id"] = $fieldName;

        // Preserve requested type
        $field["attributes"]["data-type"] = $field["type"] ?? null;
        if (!isset($field["type"])) {
            if ($defaults["casts"] && isset($defaults["casts"][$fieldName])) {
                $cast = $defaults["casts"][$fieldName];
                
                // Convert custom cast classes to simple types
                if ($cast === \App\Casts\Password::class || $cast === 'App\Casts\Password') {
                    $field["type"] = "password";
                } else {
                    // Use cast as-is, the switch will handle it later
                    $field["type"] = $cast;
                }
            } else {
                // Leave HTML handle fields without type
                $field["type"] = "text";
            }
        }

        // Fieldset classes
        $field["class"] = trim(
            ($field["class"] ?? "") .
                " form-field field-{$field["type"]} field-{$fieldName}",
        );

        // Input element classes
        $field["attributes"]["class"] = trim(
            ($field["attributes"]["class"] ?? "") . " input-{$field["type"]}",
        );

        $field["label"] = $field["label"] ?? __("$localPrefix{$field["name"]}");

        $field["default"] =
            $field["default"] ?? ($defaults[$fieldName]["default"] ?? null);

        $field["attributes"] = array_merge(
            $defaults[$fieldName]["attributes"] ?? [],
            $field["attributes"] ?? [],
        );

        $field["options"] =
            $field["options"] ?? ($defaults[$fieldName]["options"] ?? []);

        $field["description"] =
            $field["description"] ??
            ($defaults[$fieldName]["description"] ?? null);

        $field["placeholder"] =
            $field["placeholder"] ??
            ($field["attributes"]["placeholder"] ?? null);

        // Handle required/checked/disabled/readonly attributes
        if ($field["required"] ?? false) {
            $field["attributes"]["required"] = true;
        }
        if ($field["checked"] ?? false) {
            $field["attributes"]["checked"] = true;
        }
        if ($field["attributes"]["disabled"] ?? false) {
            $field["disabled"] = true;
            $field["class"] .= " disabled";
        }
        if ($field["readonly"] ?? false) {
            $field["attributes"]["readonly"] = true;
        }

        // Extract decimal precision before type conversion (e.g., "decimal:2")
        $decimalPrecision = null;
        if (preg_match('/^decimal:(\d+)$/', $field["type"], $matches)) {
            $decimalPrecision = (int) $matches[1];
            // Set field step to 10^-precision
            $field["attributes"]["step"] =
                $field["attributes"]["step"] ??
                "0." . str_repeat("0", $decimalPrecision - 1) . "1";
        }

        // $field["value"] = $field["value"] ?? null;
        // TODO: move all remaining static logic from Form->renderField to enable static defaults
        $field["isContainer"] = in_array($field["type"], [
            "html",
            "section",
            "fields-row",
            "fields-group",
            "input-group",
        ]);

        return $field;
    }

    public function getStatusIcon(): string
    {
        switch ($this->attributes["status"] ?? null) {
            case "enabled":
                $icon_name = "toggle-on";
                break;
            case "disabled":
                $icon_name = "toggle-off";
                break;
            case "confirmed":
                $icon_name = "check";
                break;
            case "new":
                $icon_name = "patch-check";
                break;
            case "vanished":
            case "deleted":
            case "cancelled":
                $icon_name = "eye-off";
                break;
            case "request":
            case "inquiry":
                $icon_name = "question";
                break;
            case "pending":
                $icon_name = "hourglass-empty";
                break;
            case "unavailable":
                $icon_name = "lock";
                break;
            case "undefined":
                $icon_name = "question-square";
                break;
            case "rejected":
                $icon_name = "close";
                break;
            default:
                $icon_name = "help";
        }

        if ($icon_name ?? false) {
            $icon = icon($icon_name);
        }

        return empty($icon) ? $icon_name : $icon;
    }

    public function getStatus(): Attribute
    {
        $status = $this->status ?? "";

        // TODO: normalize status

        return Attribute::make(get: fn(string $value) => $status);
    }

    /**
     * Clear cached configuration (for testing)
     */
    public static function clearConfig(): void
    {
        self::$config = null;
    }

    /**
     * Format an integer for display (no decimals)
     *
     * @param mixed $value
     * @return string
     */
    public static function formatInteger($value): string
    {
        if ($value === null || $value === "") {
            return "";
        }
        return number_format((int) $value, 0);
    }

    /**
     * Format a decimal/float for display
     *
     * @param mixed $value
     * @param int $decimals Number of decimal places (default: 2)
     * @return string
     */
    public static function formatDecimal($value, int $decimals = 2): string
    {
        if ($value === null || $value === "") {
            return "";
        }
        return number_format((float) $value, $decimals);
    }

    /**
     * Format an array for display
     *
     * @param mixed $value
     * @param string $separator Separator between elements (default: ', ')
     * @return string
     */
    public static function formatArray($value, string $separator = ", "): string
    {
        if (!is_array($value)) {
            return (string) $value;
        }
        return implode($separator, $value);
    }
}
