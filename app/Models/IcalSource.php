<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IcalSource extends Model
{
    protected $fillable = [
        "unit_id",
        "name",
        "type",
        "url",
        "sync_enabled",
        "last_synced_at",
        "last_sync_status",
        "last_error",
    ];

    protected $casts = [
        "sync_enabled" => "boolean",
        "last_synced_at" => "datetime",
    ];

    /**
     * Get the unit that owns this iCal source
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Scope to get only enabled sources
     */
    public function scopeEnabled($query)
    {
        return $query->where("sync_enabled", true);
    }

    /**
     * Mark the source as synced
     */
    public function markAsSynced(): void
    {
        $this->update([
            "last_synced_at" => now(),
            "last_sync_status" => "success",
            "last_error" => null,
        ]);
    }

    /**
     * Mark the source as having an error
     */
    public function markAsErrored(string $error): void
    {
        $this->update([
            "last_synced_at" => now(),
            "last_sync_status" => "error",
            "last_error" => $error,
        ]);
    }

    /**
     * Get the full name including property, unit and source
     */
    public function fullname(): string
    {
        return trim("{$this->unit->fullname()} {$this->name}");
    }
}
