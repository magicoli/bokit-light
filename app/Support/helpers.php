<?php

use App\Support\Options;

if (!function_exists("options")) {
    /**
     * Get or set an option value
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    function options(?string $key, mixed $default = null): mixed
    {
        if ($key === null) {
            return new Options();
        }

        return Options::get($key, $default);
    }
}

if (!function_exists("isLocal")) {
    /**
     * Check if the application is running in local environment
     *
     * @return bool
     */
    function isLocal(): bool
    {
        if (env("APP_ENV") === "local") {
            return true;
        }

        // Fallback to local network detection
        $ip = $_SERVER["REMOTE_ADDR"] ?? "127.0.0.1";
        $localIps = [
            "127.0.0.1",
            "::1",
            "192.168.0.0/16",
            "10.0.0.0/8",
            "172.16.0.0/12",
        ];

        foreach ($localIps as $localIp) {
            if (ip2long($ip) === ip2long($localIp)) {
                return true;
            }
        }

        return false;
    }
}

function localAppName($appName = ""): string
{
    $appName = $appName ?: "Bokit";
    if (!isLocal()) {
        return $appName;
    }
    $hostname = $_SERVER["HTTP_HOST"] ?? "localhost";
    $hostname = preg_replace('/:\d+$/', "", $hostname);
    $appName .= empty($hostname) ? "" : " ($hostname)";
    return $appName;
}

function appLogoHtml(): string
{
    return sprintf(
        '<div class="flex justify-center"><img src="%s" alt="%s" style="max-height: 60px;"></div>',
        asset(config("app.logo", "/images/logo.png")),
        config("app.name", "Bokit"),
    );
}
// <div class="branding justify-center text-center mb-6">
//     {!! appLogoHtml() !!}
//     <h1 class="text-2xl font-bold">{{ config('app.name') }}</h1>
//     <p class="text-sm text-gray-500">{{ config('app.slogan') }}</p>
// </div>

function appBrandingHtml(): string
{
    return sprintf(
        '<div class="branding justify-center text-center mb-6">
            %s
            <h1 class="text-2xl font-bold">%s</h1>
            <p class="text-sm text-gray-500">%s</p>
        </div>',
        appLogoHtml(),
        config("app.name", "Bokit"),
        config("app.slogan", "Your Ultimate Platform"),
    );
}

if (!function_exists("notice")) {
    function notice($message, $tag = "info")
    {
        $notices = session()->get("notices", []);
        $notices[] = [
            "message" => $message ?: __("notice.no_message"),
            "tag" => $tag,
        ];
        session()->flash("notices", $notices);
    }
}

function get_notices()
{
    $notices = session()->get("notices", []);
    $html = "";
    if (!empty($notices)) {
        foreach ($notices as $notice) {
            $tag = $notice["tag"] ?? "info";
            $html .= sprintf(
                '<div class="notice notice-%s" role="alert">
                    <span class="tag">%s</span>
                    <span class="message">%s</span>
                </div>',
                $tag,
                ucfirst(str_replace("notice.", "", __("notice.$tag"))),
                $notice["message"],
            );
        }
        $html = sprintf('<div class="notices">%s</div>', $html);
    }
    return $html;
}

function array_to_attrs($attributes)
{
    if (!is_array($attributes)) {
        return "";
    }

    $attrs = join(
        " ",
        array_map(function ($key) use ($attributes) {
            if (is_bool($attributes[$key])) {
                return $attributes[$key] ? $key : "";
            }
            if (is_array($attributes[$key]) || is_object($attributes[$key])) {
                return addslashes(json_encode($attributes[$key]));
            }
            return $key . '="' . sanitize_field_value($attributes[$key]) . '"';
        }, array_keys($attributes)),
    );
    return $attrs;
}

if (!function_exists("sanitize_field_value")) {
    /**
     * Sanitize field value for safe display
     *
     * @param mixed $value
     * @return string
     */
    function sanitize_field_value($value): string
    {
        if (is_null($value)) {
            return "";
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }

        if (is_bool($value)) {
            return $value ? "1" : "0";
        }

        return (string) $value;
    }
}

if (!function_exists("icon")) {
    /**
     * Get icon HTML from SVG file
     *
     * Simple, unified way to get icons. Change one line to switch icon libraries.
     *
     * @param string $name Icon name (e.g., 'dashboard', 'settings-sliders')
     * @param string $class CSS classes to add (default: 'w-4 h-4 inline')
     * @return string HTML/SVG content or empty string if not found
     *
     * @example
     *   icon('dashboard')
     *   icon('settings-sliders', 'w-6 h-6')
     */
    function icon(string $name, string $class = "w-4 h-4 inline"): string
    {
        // Path to iconic SVG files
        // Change this line to switch to another icon library
        $path = base_path(
            "vendor/itsmalikjones/blade-iconic/resources/svg/{$name}.svg",
        );

        if (!file_exists($path)) {
            return "[$name]";
        }

        $svg = file_get_contents($path);

        // Add class attribute to <svg> tag
        if ($class && strpos($svg, "<svg") !== false) {
            $svg = preg_replace(
                "/<svg([^>]*)>/",
                '<svg$1 class="' . htmlspecialchars($class) . '">',
                $svg,
                1,
            );
        }

        return $svg;
    }
}

if (!function_exists("user_can")) {
    /**
     * Check if current user has permission
     *
     * Unified permission system - supports multiple forms:
     * - user_can('role_name') - Check if user has specific role (e.g., 'property_manager')
     * - user_can('manage', 'ClassName') - Check ability on model class by short name
     * - user_can('manage', \App\Models\Model::class) - Check ability on full class name
     * - user_can('manage', $object) - Check ability on model instance (ownership check)
     *
     * Super admins ALWAYS return true for any permission check.
     *
     * This is the ONLY place where we check user permissions.
     *
     * @param string $ability Ability or role to check
     * @param mixed $model Optional model class, short name, or instance (null for role check)
     * @return bool True if user has permission, false otherwise
     *
     * @example
     *   user_can('property_manager')                    // Role check
     *   user_can('manage', 'Property')                   // Short class name
     *   user_can('manage', \App\Models\Booking::class)  // Full class name
     *   user_can('manage', $booking)                     // Instance check
     *   user_can('edit', $property)                      // Instance check
     */
    function user_can(mixed $ability, mixed $model = null): bool
    {
        if (is_bool($ability)) {
            return $ability;
        }
        if (is_null($ability)) {
            return true;
        }
        if (!is_string($ability)) {
            throw new InvalidArgumentException(
                "Ability must be string or boolean",
            );
        }

        auth()->check();
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        // Super admins can do EVERYTHING
        if ($user->isAdmin()) {
            return true;
        }

        // If no model provided, treat as role check
        if ($model === null) {
            return $user->hasRole($ability);
        }

        // Use Laravel authorization (Gates/Policies)
        // Note: Short class name conversion is handled in the Gate itself
        return $user->can($ability, $model);
    }
}

if (!function_exists("user_roles")) {
    /**
     * Get current user's roles array
     *
     * @return array User roles (empty array if not authenticated)
     */
    function user_roles(): array
    {
        $user = auth()->user();
        return $user ? $user->roles ?? [] : [];
    }
}

if (!function_exists("user_classes")) {
    /**
     * Get CSS classes for current user
     *
     * Returns space-separated classes: "user-{id} role-{role1} role-{role2} ..."
     *
     * @return string CSS classes (empty string if not authenticated)
     */
    function user_classes(): string
    {
        $user = auth()->user();

        if (!$user) {
            return "";
        }

        $classes = ["user-" . $user->id];

        foreach (user_roles() as $role) {
            $classes[] = "role-" . $role;
        }

        return implode(" ", $classes);
    }
}
