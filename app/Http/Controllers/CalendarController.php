<?php

namespace App\Http\Controllers;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Models\Property;
use App\Traits\TimezoneTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    use TimezoneTrait;

    /**
     * Display the calendar
     */
    public function index(Request $request)
    {
        // Get view type from request (default: month)
        $view = $request->get('view', 'month');

        // Get site default timezone (used for calendar navigation)
        // Each unit displays in its own timezone
        // TODO:
        // - first fetch properties and units to display
        // - if all properties use the same timezone, use it as main timezone
        // - otherwise, use the first property's timezone as main timezone
        $tzString = self::defaultTimezone();
        $tzShort = self::timezoneShort($tzString);

        // Get date from request or use today in site timezone
        $dateParam = $request->get('date');
        $currentDate = $dateParam
            ? Carbon::parse($dateParam)
            : Carbon::now($tzString);

        // Calculate date range based on view type
        switch ($view) {
            case 'week':
                $startDate = $currentDate->copy()->startOfWeek();
                $endDate = $startDate->copy()->addDays(6);
                $prevPeriod = $startDate->copy()->subWeek();
                $nextPeriod = $startDate->copy()->addWeek();
                break;
            case '2weeks':
                $startDate = $currentDate->copy()->startOfWeek();
                $endDate = $startDate->copy()->addDays(13);
                $prevPeriod = $startDate->copy()->subWeeks(2);
                $nextPeriod = $startDate->copy()->addWeeks(2);
                break;
            case 'month':
            default:
                // Afficher uniquement les jours du mois en cours
                $startDate = $currentDate->copy()->startOfMonth();
                $endDate = $currentDate->copy()->endOfMonth();
                $prevPeriod = $currentDate->copy()->subMonth();
                $nextPeriod = $currentDate->copy()->addMonth();
                break;
        }

        // Year navigation
        $prevYear = $currentDate->copy()->subYear();
        $nextYear = $currentDate->copy()->addYear();

        // Check if we can navigate forward (not beyond today + 2 years)
        $maxFutureDate = Carbon::now()->addYears(2);
        $canNavigateForward = $nextPeriod->lte($maxFutureDate);
        $canNavigateYearForward = $nextYear->lte($maxFutureDate);

        // Generate array of days for the view
        $days = [];
        $day = $startDate->copy();
        while ($day <= $endDate) {
            $days[] = $day->copy();
            $day->addDay();
        }

        // Display filters: cancelled bookings are hidden by default,
        // quotes (priced but not blocking) are shown by default
        $showCancelled = $request->boolean('cancelled');
        $showQuotes = $request->boolean('quotes', true);

        $hiddenStatuses = $showCancelled ? [] : Booking::CANCELLED_STATUSES;
        if (! $showQuotes) {
            $hiddenStatuses[] = 'quote';
        }

        // Load properties with their units and bookings
        // Filter by user access if not admin
        $query = Property::where('is_active', true)->with([
            'units' => fn ($query) => $query->where('is_active', true),
            'units.bookings' => function ($query) use ($startDate, $endDate, $hiddenStatuses) {
                $query
                    ->with(['unit', 'property']) // Eager-load for timezone() accessor
                    ->where('check_out', '>=', $startDate->format('Y-m-d'))
                    ->where('check_in', '<=', $endDate->format('Y-m-d'))
                    ->when($hiddenStatuses, fn ($q) => $q->whereNotIn('status', $hiddenStatuses));
            },
        ]);

        // Filter by user authorization
        $properties = $query->forUser()->get();

        return view('calendar', [
            'view' => $view,
            'currentDate' => $currentDate,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'days' => $days,
            'prevYear' => $prevYear,
            'nextYear' => $nextYear,
            'prevPeriod' => $prevPeriod,
            'nextPeriod' => $nextPeriod,
            'canNavigateForward' => $canNavigateForward,
            'canNavigateYearForward' => $canNavigateYearForward,
            'properties' => $properties,
            'displayTimezone' => $tzString,
            'displayTimezoneShort' => $tzShort,
            'showCancelled' => $showCancelled,
            'showQuotes' => $showQuotes,
            // Non-default filter params, appended to navigation links
            'filterQuery' => ($showCancelled ? '&cancelled=1' : '')
                .($showQuotes ? '' : '&quotes=0'),
        ]);
    }

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

        $sumOf = fn (Collection $members, callable $amountOf): ?float => $members->contains(fn (Booking $m): bool => $amountOf($m) !== null)
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
            'view_url' => BookingResource::getUrl('view', ['record' => $booking], panel: 'admin'),
            'edit_url' => BookingResource::getUrl('edit', ['record' => $booking], panel: 'admin'),
            'source' => [
                'label' => $origin
                    ? preg_replace('/^✓ /u', '', $origin->display_label)
                    : $booking->source_name,
                'url' => $origin && ! $origin->is_placeholder ? $origin->external_url : null,
            ],
            'group' => $isGroup ? [
                'count' => $active->count(),
                'members' => $members->map(fn (Booking $m): array => [
                    'id' => $m->id,
                    'unit_name' => $m->unit?->name,
                    'check_in' => $m->check_in->format('Y-m-d'),
                    'check_out' => $m->check_out->format('Y-m-d'),
                    'price' => $m->isCancelled() ? null : $rawPrice($m),
                    'is_current' => $m->id === $booking->id,
                    'is_cancelled' => $m->isCancelled(),
                ])->values()->all(),
            ] : null,
        ];
    }
}
