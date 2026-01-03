<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Migrate options from display.json to general.json
     */
    public function up(): void
    {
        $optionsPath = config('options.path');
        $displayFile = $optionsPath . '/display.json';
        $generalFile = $optionsPath . '/general.json';

        // Check if display.json exists
        if (!file_exists($displayFile)) {
            Log::info('Options migration: No display.json found - nothing to migrate');
            return;
        }

        // Read display.json
        $displayData = json_decode(file_get_contents($displayFile), true) ?? [];
        Log::info('Options migration: Found display.json with ' . count($displayData) . ' option(s)');

        // Read general.json if exists
        $generalData = [];
        if (file_exists($generalFile)) {
            $generalData = json_decode(file_get_contents($generalFile), true) ?? [];
            Log::info('Options migration: Found existing general.json with ' . count($generalData) . ' option(s)');
        }

        // Merge (display.json values take precedence if conflicts)
        $merged = array_merge($generalData, $displayData);

        // Write to general.json
        file_put_contents(
            $generalFile,
            json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
        Log::info('Options migration: Wrote general.json with ' . count($merged) . ' option(s)');

        // Rename display.json to display.json.migrated
        rename($displayFile, $displayFile . '.migrated');
        Log::info('Options migration: Renamed display.json → display.json.migrated');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $optionsPath = config('options.path');
        $displayFile = $optionsPath . '/display.json';
        $generalFile = $optionsPath . '/general.json';
        $backupFile = $displayFile . '.migrated';

        // Restore display.json from backup if exists
        if (file_exists($backupFile)) {
            rename($backupFile, $displayFile);
            Log::info('Options migration rollback: Restored display.json from backup');
        }

        // Remove timezone from general.json
        if (file_exists($generalFile)) {
            $generalData = json_decode(file_get_contents($generalFile), true) ?? [];
            unset($generalData['timezone']);
            
            if (empty($generalData)) {
                unlink($generalFile);
                Log::info('Options migration rollback: Removed empty general.json');
            } else {
                file_put_contents(
                    $generalFile,
                    json_encode($generalData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                );
                Log::info('Options migration rollback: Updated general.json');
            }
        }
    }
};
