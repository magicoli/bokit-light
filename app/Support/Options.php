<?php

namespace App\Support;

class Options
{
    private static array $cache = [];

    /**
     * Get the section and data for a given key
     */
    private static function getSection(string $key): array
    {
        // Extract section from key (e.g., 'auth.method' → 'auth')
        $section = explode(".", $key)[0];

        if (!isset(self::$cache[$section])) {
            $path = config("options.path") . "/{$section}.json";
            self::$cache[$section] = file_exists($path)
                ? json_decode(file_get_contents($path), true) ?? []
                : [];
        }

        return [self::$cache[$section], $section];
    }

    /**
     * Get an option value
     *
     * @param string $key Key in dot notation (e.g., 'auth.method')
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        [$data, $section] = self::getSection($key);

        // If key is just the section, return entire section
        if ($key === $section) {
            return $data;
        }

        // Remove section from key (e.g., 'auth.method' → 'method')
        $subKey = substr($key, strlen($section) + 1);

        return data_get($data, $subKey, $default);
    }

    /**
     * Set an option value
     *
     * @param string $key Key in dot notation (e.g., 'auth.method')
     * @param mixed $value Value to set
     * @return void
     */
    public static function set(string $key, mixed $value): void
    {
        [$data, $section] = self::getSection($key);

        // If key is just the section, replace entire section
        if ($key === $section) {
            $data = $value;
        } else {
            // Remove section from key
            $subKey = substr($key, strlen($section) + 1);
            data_set($data, $subKey, $value);
        }

        // Write to file
        file_put_contents(
            "{$dir}/{$section}.json",
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );

        // Update cache
        self::$cache[$section] = $data;
    }

    /**
     * Check if an option exists
     *
     * @param string $key Key in dot notation
     * @return bool
     */
    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }

    /**
     * Delete an option
     *
     * @param string $key Key in dot notation
     * @return void
     */
    public static function forget(string $key): void
    {
        [$data, $section] = self::getSection($key);

        if ($key === $section) {
            // Delete entire section file
            $path = config("options.path") . "/{$section}.json";
            if (file_exists($path)) {
                unlink($path);
            }
            unset(self::$cache[$section]);
        } else {
            // Remove specific key
            $subKey = substr($key, strlen($section) + 1);
            data_forget($data, $subKey);
            self::set($section, $data);
        }
    }
}
