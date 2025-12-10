<?php

$path = env('OPTIONS_PATH', storage_path('options'));

if (!is_dir($path)) {
    mkdir($path, 0755, true);
}

return [
    /*
    |--------------------------------------------------------------------------
    | Options Storage Path
    |--------------------------------------------------------------------------
    |
    | This value determines where the application's options are stored.
    | Options are stored as JSON files organized by section (e.g., auth.json,
    | layout.json, etc.).
    |
    */

    'path' => $path,
];
