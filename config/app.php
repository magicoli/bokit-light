<?php

use Composer\InstalledVersions;

// Generate a random key and save it if not provided
$key = env('APP_KEY', 'base64:'.base64_encode(random_bytes(32)));
if (empty(env('APP_KEY'))) {
    putenv("APP_KEY={$key}");

    if (! file_exists($envPath = dirname(__DIR__).'/.env')) {
        file_put_contents($envPath, "APP_KEY={$key}\n");
    } else {
        // Replace APP_KEY in .env file if it exists, otherwise add it
        $env = file_get_contents($envPath);
        $env = "APP_KEY={$key}\n".preg_replace('/^APP_KEY=.*$(?:\r\n|\n)?/m', '', $env);
        file_put_contents($envPath, $env);
    }
}

$env_env = env('APP_ENV', 'production');
$env_debug = env('APP_DEBUG', false);
$env_debug = $env_debug == true || $env_debug == 'true';

/**
 * Get app version (stored for production, live and git hash in dev)
 */
$root = dirname(__DIR__);
$version = null;
$gitHash = null;

// Get stored version and hash
if (is_file($versionFile = $root.'/storage/version')) {
    $contents = trim((string) file_get_contents($versionFile));
    $version = strtok($contents, " \n\t");
    $gitHash = strtok(" \n\t") ?: null;
}

// Allow local override — only replaces what was found above if actually set
$version = env('APP_VERSION', null) ?: $version;

// Try to get actual version and hash in debug mode
if ($env_debug || $env_env !== 'production') {
    $composerJson = json_decode((string) @file_get_contents($root.'/composer.json'), true);
    $version = $composerJson['version'] ?? $version;

    if (is_file($headFile = $root.'/.git/HEAD')) {
        $head = @file_get_contents($headFile);

        if ($head !== false && str_starts_with($head = trim($head), 'ref: ')) {
            $head = @file_get_contents(dirname($headFile).'/'.substr($head, 5));
            $head = $head !== false ? trim($head) : false;
        }
        $gitHash = $head ? substr($head, 0, 7) : $gitHash;
    }
}

// Fallback to the composer-installed version. Reached on production, where the debug block above
// is skipped and neither storage/version nor APP_VERSION was set yet — which is exactly the case
// during a fresh release's `composer install` (post-autoload-dump → package:discover). This file
// is in the global namespace, so the class must be fully qualified; guarded so an unresolvable
// class leaves $version empty rather than fataling the whole boot.
if (empty($version) && class_exists(InstalledVersions::class)) {
    $package = InstalledVersions::getRootPackage()['name'];
    $version = InstalledVersions::getPrettyVersion($package);
}

// Build final version
$version .= $gitHash ? " ($gitHash)" : '';

return [
    /*
     |--------------------------------------------------------------------------
     | Application Name
     |--------------------------------------------------------------------------
     |
     | This value is the name of your application, which will be used when the
     | framework needs to place the application's name in a notification or
     | other UI elements where an application name needs to be displayed.
     |
     */

    'name' => env('APP_NAME', 'Bokit').(($env_debug || $env_env !== 'production') ? " [$env_env]" : ''),
    'slogan' => env('APP_SLOGAN', 'Bring On Kitsch Island Time'),
    'logo' => env('APP_LOGO'),
    'version' => $version ?: '',

    /*
     |--------------------------------------------------------------------------
     | Application Environment
     |--------------------------------------------------------------------------
     |
     | This value determines the "environment" your application is currently
     | running in. This may determine how you prefer to configure various
     | services the application utilizes. Set this in your ".env" file.
     |
     */

    'env' => $env_env,

    /*
     |--------------------------------------------------------------------------
     | Application Debug Mode
     |--------------------------------------------------------------------------
     |
     | When your application is in debug mode, detailed error messages with
     | stack traces will be shown on every error that occurs within your
     | application. If disabled, a simple generic error page is shown.
     |
     */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
     |--------------------------------------------------------------------------
     | Application URL
     |--------------------------------------------------------------------------
     |
     | This URL is used by the console to properly generate URLs when using
     | the Artisan command line tool. You should set this to the root of
     | the application so that it's available within Artisan commands.
     |
     */

    'url' => env('APP_URL', 'http://localhost'),

    /*
     |--------------------------------------------------------------------------
     | Application Timezone
     |--------------------------------------------------------------------------
     |
     | Here you may specify the default timezone for your application, which
     | will be used by the PHP date and date-time functions. The timezone
     | is set to "UTC" by default as it is suitable for most use cases.
     |
     */

    'timezone' => 'UTC',

    /*
     |--------------------------------------------------------------------------
     | Application Locale Configuration
     |--------------------------------------------------------------------------
     |
     | The application locale determines the default locale that will be used
     | by Laravel's translation / localization methods. This option can be
     | set to any locale for which you plan to have translation strings.
     |
     */

    'locale' => env('APP_LOCALE', 'en'),

    // A genuinely stable copy of the above: Illuminate\Foundation\Application::setLocale()
    // (called on every request by BezhanSalleh\LanguageSwitch's own SwitchLanguageLocale
    // middleware, among others) overwrites config('app.locale') itself with whichever locale the
    // CURRENT viewer's detection cascade resolved to - so by the time a property's own form
    // renders, config('app.locale') no longer holds the app's configured default, it holds
    // "whatever locale this particular visitor happens to be seeing right now". Anything that
    // needs the real, request-independent default (Property::locale()'s own fallback, its
    // settings form's placeholder) must read THIS key instead
    // (dev/project-tenant-sub-sites.md — the bug Oli caught: an admin's own French UI preference
    // was leaking into a property's displayed "default language").
    'default_locale' => env('APP_LOCALE', 'en'),

    // Latin-script languages first (en/es/de/fr/pt/it/nl), then ru (Cyrillic), ja (CJK), ar (RTL)
    // - specifically so writing-system rendering can be checked later, not because all ten are
    // translated yet (only en/fr have translation files; Laravel falls back to fallback_locale
    // gracefully for the rest — dev/project-tenant-sub-sites.md).
    'locales' => ['en', 'es', 'de', 'fr', 'pt', 'it', 'nl', 'ru', 'ja', 'ar'],

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
     |--------------------------------------------------------------------------
     | Encryption Key
     |--------------------------------------------------------------------------
     |
     | This key is utilized by Laravel's encryption services and should be set
     | to a random, 32 character string to ensure that all encrypted values
     | are secure. You should do this prior to deploying the application.
     |
     */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(explode(',', (string) env('APP_PREVIOUS_KEYS', ''))),
    ],

    /*
     |--------------------------------------------------------------------------
     | Maintenance Mode Driver
     |--------------------------------------------------------------------------
     |
     | These configuration options determine the driver used to determine and
     | manage Laravel's "maintenance mode" status. The "cache" driver will
     | allow maintenance mode to be controlled across multiple machines.
     |
     | Supported drivers: "file", "cache"
     |
     */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],
];
