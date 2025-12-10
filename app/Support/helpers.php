<?php

use App\Support\Options;

if (!function_exists('options')) {
    /**
     * Get or set an option value
     * 
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    function options(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return new Options();
        }

        return Options::get($key, $default);
    }
}
