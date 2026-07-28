<?php

namespace App\Sync\Contracts;

use App\Models\Booking;
use App\Models\Unit;

/**
 * Implemented by source connectors that can also receive bookings —
 * bokit-origin (manual) bookings are pushed to them so external calendars
 * and OTAs get blocked.
 *
 * Connectors only talk to their API; deciding what to push and recording
 * the returned external id (echo suppression) is the engine's job.
 */
interface PushableConnector
{
    /**
     * Push the booking's current state to the source. When $externalId is
     * null the booking is created there; otherwise the existing external
     * booking is updated (cancellations included, via the status).
     *
     * @return string the booking's id in the source system
     *
     * @throws \RuntimeException when the push fails.
     */
    public function pushBooking(Unit $unit, array $sourceConfig, Booking $booking, ?string $externalId): string;
}
