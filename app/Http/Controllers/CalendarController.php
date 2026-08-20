<?php

namespace App\Http\Controllers;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Sync\SyncRunner;
use Illuminate\Database\Eloquent\Collection;
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

        return response()->json(self::bookingPayload($booking));
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

    /**
     * Build the JSON payload for the calendar booking modal.
     *
     * Group reservations aggregate dates, price, paid and guest counts over
     * all members and expose the member list for the group detail table.
     * Links point to the Filament admin panel; the origin link comes from
     * the booking's origin source when its connector provides one.
     *
     * @return array<string, mixed>
     */
    private static function bookingPayload(Booking $booking): array
    {
        $members = $booking->groupMembers();
        $isGroup = $members->count() > 1;

        // Cancelled members hold nothing: they stay listed in the group
        // detail but count for nothing in the aggregates.
        $active = $members->reject(fn (Booking $m): bool => $m->isCancelled())->values();
        if ($active->isEmpty()) {
            $active = new Collection([$booking]);
        }

        $rawPrice = fn (Booking $b): ?float => $b->getRawOriginal('price') !== null
            ? (float) $b->getRawOriginal('price')
            : null;
        $paidOf = function (Booking $b): ?float {
            $paid = $b->getMetadata('invoice_payment_total') ?? $b->getMetadata('paid');

            return $paid !== null ? (float) $paid : null;
        };
        $depositOf = fn (Booking $b): ?float => $b->getMetadata('deposit') !== null
            ? (float) $b->getMetadata('deposit')
            : null;

        if ($booking->isCancelled()) {
            // No money is expected from a cancelled booking — hide amounts.
            $rawPrice = fn (Booking $b): ?float => null;
            $paidOf = fn (Booking $b): ?float => null;
            $depositOf = fn (Booking $b): ?float => null;
        }

        $sumOf = fn (Collection $members, callable $amountOf): ?float => $members->contains(
            fn (Booking $m): bool => $amountOf($m) !== null,
        )
                ? $members->sum(fn (Booking $m): float => $amountOf($m) ?? 0)
                : null;

        if ($isGroup) {
            $price = $sumOf($active, $rawPrice);
            $paid = $sumOf($active, $paidOf);
            $deposit = $sumOf($active, $depositOf);
        } else {
            $price = $rawPrice($booking);
            $paid = $paidOf($booking);
            $deposit = $depositOf($booking);
        }

        $origin = $booking->originSource;

        return [
            'id' => $booking->id,
            'guest_name' => $booking->guest_name,
            'status' => $booking->status,
            'status_label' => __('booking.status.'.$booking->status),
            'display_status' => $booking->displayStatus(),
            'deleted_at' => $booking->deleted_at?->toIso8601String(),
            'check_in' => ($isGroup ? $active->min('check_in') : $booking->check_in)->format('Y-m-d'),
            'check_out' => ($isGroup ? $active->max('check_out') : $booking->check_out)->format('Y-m-d'),
            'adults' => $isGroup ? ($active->sum('adults') ?: null) : $booking->adults,
            'children' => $isGroup ? ($active->sum('children') ?: null) : $booking->children,
            'guests' => $isGroup
                ? ($active->sum(fn (Booking $m): int => (int) ($m->guests ?? 0)) ?: null)
                : $booking->guests,
            'price' => $price,
            'deposit' => $deposit,
            'paid' => $paid,
            'balance' => $price !== null ? round($price - ($paid ?? 0), 2) : null,
            'notes' => $booking->notes,
            'metadata' => $booking->metadata,
            'unit' => [
                'id' => $booking->unit?->id,
                'name' => $booking->unit?->name,
            ],
            'property' => [
                'id' => $booking->property?->id,
                'name' => $booking->property?->name,
            ],
            'view_url' => BookingResource::getUrl('view', ['record' => $booking], panel: 'app'),
            'edit_url' => BookingResource::getUrl('edit', ['record' => $booking], panel: 'app'),
            'source' => [
                'label' => $origin ? preg_replace('/^✓ /u', '', $origin->display_label) : $booking->source_name,
                'url' => $origin && ! $origin->is_placeholder ? $origin->external_url : null,
            ],
            // Real origin channel (airbnb, booking.com, …) with its direct
            // link on the OTA — distinct from the transport source above.
            'origin' => [
                'channel' => $booking->source_name,
                'slug' => $booking->api_source,
                'url' => $booking->originUrl(),
                'logo' => icon_ota($booking->api_source) ?: icon('arrow-up-right'),
            ],
            'group' => $isGroup
                ? [
                    'count' => $active->count(),
                    'members' => $members
                        ->map(fn (Booking $m): array => [
                            'id' => $m->id,
                            'unit_name' => $m->unit?->name,
                            'check_in' => $m->check_in->format('Y-m-d'),
                            'check_out' => $m->check_out->format('Y-m-d'),
                            'price' => $m->isCancelled() ? null : $rawPrice($m),
                            'is_current' => $m->id === $booking->id,
                            'is_cancelled' => $m->isCancelled(),
                        ])
                        ->values()
                        ->all(),
                ] : null,
        ];
    }
}
