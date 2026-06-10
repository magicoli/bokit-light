<?php

namespace App\Contracts;

use App\Models\Unit;
use App\Support\NormalizedBooking;

/**
 * Implemented by each source module (Beds24, iCal, HBook, Multipass, …).
 *
 * A connector only fetches data from its source and normalizes it into
 * NormalizedBooking objects. It never reads or writes bookings — matching,
 * ownership and persistence are handled centrally by SyncEngine.
 *
 * Modules register an instance via SyncRegistry::register() in their
 * ServiceProvider. bokit:sync iterates Property → Unit → options.sources
 * and dispatches each source entry to SyncEngine with the registered
 * connector for its type.
 */
interface SourceConnector
{
    /**
     * The source type this connector manages.
     * Must match the 'type' field in unit.options.sources entries.
     * Examples: 'beds24', 'ical', 'hbook', 'multipass'
     */
    public function sourceType(): string;

    /**
     * A short human-readable name for this connector (e.g. "Beds24 API").
     */
    public function label(): string;

    /**
     * Label shown in sync output for a specific source entry
     * (e.g. "iCal beds24.com").
     */
    public function displayLabel(array $sourceConfig): string;

    /**
     * Stable identifier for this source instance, used to store
     * (source_key, external_id) pairs in booking_sources.
     * Examples: 'beds24', 'ical:beds24.com'
     */
    public function sourceKey(Unit $unit, array $sourceConfig): string;

    /**
     * Fetch and normalize all bookings from this source for the given unit.
     *
     * @return NormalizedBooking[]
     *
     * @throws \RuntimeException when the source is misconfigured or unreachable.
     */
    public function fetchBookings(Unit $unit, array $sourceConfig): array;
}
