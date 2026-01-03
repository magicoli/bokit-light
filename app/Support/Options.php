<?php

namespace App\Support;

class Options
{
    private static array $cache = [];

    /**
     * Normalize key by adding 'general.' prefix if no section specified
     */
    private static function normalizeKey(string $key): string
    {
        // If key contains a dot, it already has a section
        if (strpos($key, '.') !== false) {
            return $key;
        }
        
        // No section specified - add 'general.' prefix
        return 'general.' . $key;
    }

    /**
     * Get the section and data for a given key
     */
    private static function getSection(string $key): array
    {
        // Normalize key (add 'general.' if needed)
        $key = self::normalizeKey($key);
        
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
     * @param string $key Key in dot notation (e.g., 'auth.method') or simple key (e.g., 'timezone' → 'general.timezone')
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        // Normalize key
        $normalizedKey = self::normalizeKey($key);
        
        [$data, $section] = self::getSection($normalizedKey);

        // If key is just the section, return entire section
        if ($normalizedKey === $section) {
            return $data;
        }

        // Remove section from key (e.g., 'auth.method' → 'method')
        $subKey = substr($normalizedKey, strlen($section) + 1);

        try {
            return data_get($data, $subKey, $default);
        } catch (Exception $e) {
            Log::error("Error getting option {$normalizedKey}: {$e->getMessage()}");
            return $default;
        }
    }

    /**
     * Set an option value
     *
     * @param string $key Key in dot notation (e.g., 'auth.method') or simple key (e.g., 'timezone' → 'general.timezone')
     * @param mixed $value Value to set
     * @return void
     */
    public static function set(string $key, mixed $value): void
    {
        // Normalize key
        $normalizedKey = self::normalizeKey($key);
        
        [$data, $section] = self::getSection($normalizedKey);

        // If key is just the section, replace entire section
        if ($normalizedKey === $section) {
            $data = $value;
        } else {
            // Remove section from key
            $subKey = substr($normalizedKey, strlen($section) + 1);
            data_set($data, $subKey, $value);
        }

        // Write to file
        $path = config("options.path") . "/{$section}.json";
        file_put_contents(
            $path,
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
     * @param string $key Key in dot notation or simple key
     * @return void
     */
    public static function forget(string $key): void
    {
        // Normalize key
        $normalizedKey = self::normalizeKey($key);
        
        [$data, $section] = self::getSection($normalizedKey);

        if ($normalizedKey === $section) {
            // Delete entire section file
            $path = config("options.path") . "/{$section}.json";
            if (file_exists($path)) {
                unlink($path);
            }
            unset(self::$cache[$section]);
        } else {
            // Remove specific key
            $subKey = substr($normalizedKey, strlen($section) + 1);
            data_forget($data, $subKey);
            self::set($section, $data);
        }
    }
}
