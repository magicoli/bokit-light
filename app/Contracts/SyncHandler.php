<?php

namespace App\Contracts;

use App\Models\Unit;

/**
 * Implemented by each sync module (Beds24, iCal, HBook, Multipass, …).
 *
 * Modules register an instance via SyncRegistry::register() in their
 * ServiceProvider::boot(). bokit:sync iterates Property → Unit → options.sources
 * and dispatches each source entry to the registered handler for its type.
 *
 * Source priority is respected by the order of entries in unit.options.sources:
 * if a booking originates from HBook, subsequent sources (iCal, Beds24) must not
 * overwrite its data — this is enforced via the three-way merge in Booking::applySyncData().
 */
interface SyncHandler
{
    /**
     * The source type this handler manages.
     * Must match the 'type' field in unit.options.sources entries.
     * Examples: 'beds24', 'ical', 'hbook', 'multipass'
     */
    public function sourceType(): string;

    /**
     * A short human-readable label shown in sync output (e.g. "Beds24 API").
     */
    public function label(): string;

    /**
     * Sync one source entry for one unit.
     *
     * @param  Unit  $unit  The unit being synced (property relation already loaded).
     * @param  array  $sourceConfig  The source config from unit.options.sources.
     * @param  bool  $dryRun  When true, report changes without writing.
     * @return array{label: string, success: bool, total: int, new: int, updated: int, deleted: int, vanished: int, error: ?string}
     */
    public function syncSource(Unit $unit, array $sourceConfig, bool $dryRun = false): array;
}
