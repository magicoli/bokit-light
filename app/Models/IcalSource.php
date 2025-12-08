<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IcalSource extends Model
{
    protected $fillable = [
        'property_id',
        'name',
        'url',
        'sync_enabled',
        'last_synced_at',
        'last_error',
    ];

    protected $casts = [
        'sync_enabled' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Get the property that owns this iCal source
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Scope to get only enabled sources
     */
    public function scopeEnabled($query)
    {
        return $query->where('sync_enabled', true);
    }

    /**
     * Mark the source as synced
     */
    public function markAsSynced(): void
    {
        $this->update([
            'last_synced_at' => now(),
            'last_error' => null,
        ]);
    }

    /**
     * Mark the source as having an error
     */
    public function markAsErrored(string $error): void
    {
        $this->update([
            'last_error' => $error,
        ]);
    }
}
