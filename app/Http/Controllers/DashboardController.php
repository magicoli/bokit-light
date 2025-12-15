<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Display the calendar dashboard
     */
    public function index(Request $request)
    {
        // Get view type from request (default: month)
        $view = $request->get("view", "month");

        // Get date from request or use today
        $dateParam = $request->get("date");
        $currentDate = $dateParam ? Carbon::parse($dateParam) : Carbon::now();

        // Calculate date range based on view type
        switch ($view) {
            case "week":
                $startDate = $currentDate->copy()->startOfWeek();
                $endDate = $startDate->copy()->addDays(6);
                $prevPeriod = $startDate->copy()->subWeek();
                $nextPeriod = $startDate->copy()->addWeek();
                break;
            case "2weeks":
                $startDate = $currentDate->copy()->startOfWeek();
                $endDate = $startDate->copy()->addDays(13);
                $prevPeriod = $startDate->copy()->subWeeks(2);
                $nextPeriod = $startDate->copy()->addWeeks(2);
                break;
            case "month":
            default:
                $startDate = $currentDate
                    ->copy()
                    ->startOfMonth()
                    ->startOfWeek();
                $endDate = $currentDate->copy()->endOfMonth()->endOfWeek();
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

        // Load properties with their units and bookings
        // Filter by user access if not admin
        $query = Property::with([
            "units.bookings" => function ($query) use ($startDate, $endDate) {
                $query
                    ->where("check_out", ">=", $startDate->format("Y-m-d"))
                    ->where("check_in", "<=", $endDate->format("Y-m-d"));
            },
        ]);

        // Filter properties for non-admin users
        if (!auth()->user()->isAdmin()) {
            $query->whereHas('users', function ($q) {
                $q->where('users.id', auth()->id());
            });
        }

        $properties = $query->get();

        return view("dashboard", [
            "view" => $view,
            "currentDate" => $currentDate,
            "startDate" => $startDate,
            "endDate" => $endDate,
            "days" => $days,
            "prevYear" => $prevYear,
            "nextYear" => $nextYear,
            "prevPeriod" => $prevPeriod,
            "nextPeriod" => $nextPeriod,
            "canNavigateForward" => $canNavigateForward,
            "canNavigateYearForward" => $canNavigateYearForward,
            "properties" => $properties,
        ]);
    }

    /**
     * Get booking details (API endpoint)
     */
    public function booking($id)
    {
        $booking = \App\Models\Booking::with("unit.property")->findOrFail($id);

        // Check if user has access to this booking's property
        if (!auth()->user()->hasAccessTo($booking->unit->property)) {
            abort(403, 'Access denied');
        }

        return response()->json($booking);

        return response()->json([
            "id" => $booking->id,
            "guest_name" => $booking->guest_name,
            "check_in" => $booking->check_in->format("Y-m-d"),
            "check_out" => $booking->check_out->format("Y-m-d"),
            "status" => $booking->status,
            "status_label" => $booking->status_label,
            "color" => $booking->color,
            "adults" => $booking->adults,
            "children" => $booking->children,
            "notes" => $booking->notes,
            "raw_data" => $booking->raw_data,
            "source_name" => $booking->source_name,
            "unit" => [
                "id" => $booking->unit->id,
                "name" => $booking->unit->name,
                "color" => $booking->unit->color,
            ],
            "property" => [
                "id" => $booking->unit->property->id,
                "name" => $booking->unit->property->name,
            ],
        ]);
    }
}
