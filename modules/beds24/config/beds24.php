<?php

return [
    /*
     * Global Beds24 API key (account-level).
     * Should be set via admin settings (Options::set('beds24.api_key', ...)).
     * Falls back to env for initial migration from .env-based config.
     */
    'api_key' => env('BEDS24_API_KEY'),
];
