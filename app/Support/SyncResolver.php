<?php

namespace App\Support;

use App\Models\SyncLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Three-way merge resolver for sync operations
 * 
 * Prevents sync from overwriting local manual changes by comparing:
 * - Current local value
 * - Last synced value (baseline)
 * - New remote value
 * 
 * Logic: if (local == baseline) → accept remote, else → keep local
 * 
 * Note: Local differences from remote are intentional management edits
 * (corrected names, adjusted times, added details), not "conflicts" to resolve.
 * Both values have their purpose and should be visible.
 */
class SyncResolver
{
    /**
     * Apply sync data to model with three-way merge
     * 
     * @param Model $model Model to update
     * @param array $newData New data from sync source
     * @param string $source Sync source identifier (e.g., 'airbnb_ical', 'beds24_api')
     * @param array $fieldMapping Optional mapping of sync fields to model attributes
     * @return array ['updated' => [...], 'diffs' => [...]]
     */
    public static function applySyncData(
        Model $model,
        array $newData,
        string $source,
        array $fieldMapping = []
    ): array {
        $updated = [];
        $diffs = [];

        // Get current sync_data structure
        $syncData = $model->sync_data ?? [];
        $lastSynced = $syncData[$source]['processed'] ?? [];

        foreach ($newData as $syncField => $newValue) {
            // Map sync field to model attribute
            $modelField = $fieldMapping[$syncField] ?? $syncField;

            // Skip if field doesn't exist on model
            if (!array_key_exists($modelField, $model->getAttributes())) {
                continue;
            }

            $currentValue = $model->$modelField;
            $baselineValue = $lastSynced[$syncField] ?? null;

            // Three-way merge logic
            if (self::valuesEqual($currentValue, $baselineValue)) {
                // No local modification → accept remote
                if (!self::valuesEqual($currentValue, $newValue)) {
                    $model->$modelField = $newValue;
                    $updated[] = $modelField;

                    // Log the change
                    SyncLog::logChange(
                        $model,
                        $modelField,
                        $currentValue,
                        $newValue,
                        $source
                    );
                }
            } else {
                // Local modification detected → keep local, record difference
                $diffs[] = [
                    'field' => $modelField,
                    'local' => $currentValue,
                    'remote' => $newValue,
                    'baseline' => $baselineValue,
                ];

                Log::info("Sync diff detected (local edit preserved)", [
                    'model' => get_class($model),
                    'id' => $model->id,
                    'field' => $modelField,
                    'local' => $currentValue,
                    'remote' => $newValue,
                ]);
            }
        }

        // Update sync_data with new baseline
        $syncData[$source] = [
            'raw' => $newData, // Store raw data
            'processed' => $newData, // Store processed data (same for now)
            'synced_at' => now()->toIso8601String(),
        ];
        $model->sync_data = $syncData;

        if (!empty($updated)) {
            $model->save();
        }

        return [
            'updated' => $updated,
            'diffs' => $diffs,
        ];
    }

    /**
     * Compare two values for equality (handles nulls and type juggling)
     */
    private static function valuesEqual($a, $b): bool
    {
        // Both null/empty
        if (empty($a) && empty($b)) {
            return true;
        }

        // Type-safe comparison
        return $a === $b;
    }

    /**
     * Get sync differences for a model (fields where local != remote)
     * 
     * These are intentional local edits, not conflicts to resolve.
     * Both local (managed) and remote (sync) values should be displayed.
     * 
     * @param Model $model
     * @param string|null $source Source to check (default: first source)
     * @return array ['field' => ['local' => ..., 'remote' => ...], ...]
     */
    public static function getDiffs(Model $model, ?string $source = null): array
    {
        $syncData = $model->sync_data ?? [];

        // Use first source if not specified
        if ($source === null) {
            $source = array_key_first($syncData);
        }

        if (!isset($syncData[$source])) {
            return [];
        }

        $processed = $syncData[$source]['processed'] ?? [];
        $diffs = [];

        foreach ($processed as $field => $remoteValue) {
            if (array_key_exists($field, $model->getAttributes())) {
                $localValue = $model->$field;

                if (!self::valuesEqual($localValue, $remoteValue)) {
                    $diffs[$field] = [
                        'local' => $localValue,
                        'remote' => $remoteValue,
                    ];
                }
            }
        }

        return $diffs;
    }
}
