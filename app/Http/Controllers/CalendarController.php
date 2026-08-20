<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Sync\SyncRunner;
use Illuminate\Support\Facades\Log;

/**
 * The calendar itself is App\Filament\Pages\Calendar now (a standard Filament page). What is left
 * here are its JSON endpoints — not navigation, so there was no reason to fold them into the page.
 */
class CalendarController extends Controller
{
    /**
     * Get booking details (API endpoint for the calendar modal)
     */
    public function booking($id)
    {
        $booking = Booking::with(['unit.property', 'originSource'])->findOrFail($id);

        $property = $booking->property ?? $booking->unit?->property;
        if (! $property || ! auth()->user()->hasAccessTo($property)) {
            abort(403, 'Access denied');
        }

        return response()->json($booking->toDetailPayload());
    }

    /**
     * Re-pull the booking's unit from its sources — called when the modal
     * closes after the user followed a source link to edit the booking in
     * the channel manager / OTA, so their change comes back into bokit.
     * Pull only (never push): the source is authoritative for this edit.
     */
    public function resync($id, SyncRunner $runner)
    {
        $booking = Booking::with('unit.property')->findOrFail($id);

        $property = $booking->property ?? $booking->unit?->property;
        if (! $property || ! auth()->user()->hasAccessTo($property)) {
            abort(403, 'Access denied');
        }

        // Same procedure as the periodic sync and as bokit:sync, narrowed to the booking's own
        // unit. Scope kept exactly as it was: a booking belonging to a group spans several units
        // and only this one is refreshed — see the ticket about targeted resync and groups.
        $unit = $booking->unit;

        $result = $runner->run($unit ? [$unit] : []);

        foreach ($result['failures'] as $failure) {
            Log::warning("[Calendar] Resync failed: {$failure}");
        }

        return response()->json(['ok' => true]);
    }
}
