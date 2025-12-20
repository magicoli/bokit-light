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
            error_log("DEBUG: Application is running in local environment");
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
                error_log("DEBUG: Application IP matches local IP: $ip");
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
