<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    /**
     * Disable updated_at (we only track creation)
     */
    public const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'model_type',
        'model_id',
        'source',
        'field',
        'old_value',
        'new_value',
    ];

    /**
     * Get the model that this log entry belongs to
     */
    public function model()
    {
        return $this->morphTo('model', 'model_type', 'model_id');
    }

    /**
     * Log a change
     * 
     * @param Model $model The model being changed
     * @param string $field Field name
     * @param mixed $oldValue Previous value
     * @param mixed $newValue New value
     * @param string $source Source identifier (e.g., 'airbnb_ical', 'beds24_api', 'user:email@example.com')
     */
    public static function logChange(
        Model $model,
        string $field,
        $oldValue,
        $newValue,
        string $source
    ): self {
        return self::create([
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'source' => $source,
            'field' => $field,
            'old_value' => is_array($oldValue) || is_object($oldValue) 
                ? json_encode($oldValue) 
                : (string)$oldValue,
            'new_value' => is_array($newValue) || is_object($newValue)
                ? json_encode($newValue)
                : (string)$newValue,
        ]);
    }
}
