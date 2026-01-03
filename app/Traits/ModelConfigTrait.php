<?php

namespace App\Traits;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Casts\Attribute;

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
     * (Not yet implemented)
     *
     * @return string|null
     */
    protected function actions(): Attribute
    {
        return Attribute::make(
            get: function () {
                $actions = [
                    "status" => [
                        "url" => false,
                        "text" => __("app.status_" . $this->status),
                        "icon" => $this->getStatusIcon(),
                    ],
                    "view" => [
                        "url" => route("admin.bookings.show", $this->id),
                        "text" => __("lists.action_view"),
                        "icon" => icon("eye"),
                    ],
                    "edit" => [
                        "url" => route("admin.bookings.edit", $this->id),
                        "text" => __("lists.action_edit"),
                        "icon" => icon("edit"),
                    ],
                    // "delete" => [
                    //     "url" => route("admin.bookings.destroy", $this->id),
                    //     "text" => __("lists.action_delete"),
                    //     "icon" => icon("trash"),
                    // ],
                ];
                if ($this->ota_url ?? false) {
                    $actions["ota"] = [
                        "url" => $this->ota_url,
                        "text" => __("lists.action_ota"),
                        "icon" => icon($this->api_source ?? "arrow-up-right"),
                        "target" => "_blank",
                    ];
                }
                $sep = " ";
                foreach ($actions as $key => $value) {
                    $target =
                        $value["target"] ?? false
                            ? 'target="' . $value["target"] . '"'
                            : "";
                    $actions[$key] = sprintf(
                        '<a href="%s" %s class="action-link" title="%s">%s</a>',
                        $value["url"],
                        $target,
                        $value["text"] ?? "",
                        $value["icon"] ?? ($value["text"] ?? ""),
                    );
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
        $fillable = $defaults["fillable"] ?? [];
        $appends = $defaults["appends"] ?? [];
        $valid_columns = array_merge($fillable, $appends);
        $list_columns = empty($defaults["list_columns"])
            ? $fillable
            : $defaults["list_columns"];
        $casts = $defaults["casts"] ?? [];
        $searchable = $defaults["searchable"] ?? ["name"];
        $sortable = $defaults["sortable"] ?? $fillable;
        $filterable = $defaults["filterable"] ?? ["status"];
        $capability = $defaults["capability"] ?? "manage";

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

        self::$config = [
            "fillable" => $fillable,
            "appends" => $appends,
            "casts" => $casts,
            "searchable" => $searchable,
            "sortable" => $sortable,
            "filterable" => $filterable,
            "capability" => $capability,
            "classSlug" => $classSlug,
            "classBasename" => $classBasename,
            "list_columns" => $list_columns,
        ];

        return self::$config;
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
