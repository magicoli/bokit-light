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
