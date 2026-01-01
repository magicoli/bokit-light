<?php

namespace App\Observers;

use App\Models\Booking;
use App\Models\Unit;

class BookingObserver
{
    /**
     * Handle the Booking "creating" event.
     * Sync property_id from unit before creating
     */
    public function creating(Booking $booking): void
    {
        $this->syncPropertyId($booking);
    }

    /**
     * Handle the Booking "updating" event.
     * Sync property_id from unit if unit_id changed
     */
    public function updating(Booking $booking): void
    {
        // Only sync if unit_id is being changed
        if ($booking->isDirty('unit_id')) {
            $this->syncPropertyId($booking);
        }
    }

    /**
     * Sync property_id from unit
     */
    protected function syncPropertyId(Booking $booking): void
    {
        if ($booking->unit_id) {
            $unit = Unit::find($booking->unit_id);
            if ($unit) {
                $booking->property_id = $unit->property_id;
            }
        }
    }
}
