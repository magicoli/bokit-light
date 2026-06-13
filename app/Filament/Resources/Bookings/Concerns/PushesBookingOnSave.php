<?php

namespace App\Filament\Resources\Bookings\Concerns;

use App\Services\SyncEngine;
use Filament\Notifications\Notification;

/**
 * Push a booking to its unit's writable sources right after it is created
 * or edited in the panel — bokit acts as master, so a manual or otherwise
 * editable booking is mirrored to Beds24 (and any other pushable source)
 * immediately. Protected (self-managed OTA) bookings are skipped by the
 * engine. Failures are surfaced but never block the save.
 */
trait PushesBookingOnSave
{
    protected function pushBookingOnSave(): void
    {
        $stats = app(SyncEngine::class)->pushBooking($this->record);

        if (($stats['failed'] ?? 0) > 0) {
            Notification::make()
                ->title(__('booking.push.failed'))
                ->body(implode("\n", $stats['errors']))
                ->warning()
                ->send();
        }
    }
}
