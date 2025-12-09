<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Get view mode from query or default based on screen size (handled by JS)
        $view = $request->get('view', 'month');
        $date = $request->has('date') 
            ? Carbon::parse($request->date)
            : Carbon::now();
        
        // Limit future navigation (configurable, default 24 months)
        $maxFutureMonths = env('MAX_FUTURE_MONTHS', 24);
        $maxDate = Carbon::now()->addMonths($maxFutureMonths);
        
        if ($date->isAfter($maxDate)) {
            $date = $maxDate;
        }
        
        // Calculate date range based on view
        switch ($view) {
            case 'week':
                $startDate = $date->copy()->startOfWeek()->startOfDay();
                $endDate = $date->copy()->endOfWeek()->startOfDay();
                $prevPeriod = $date->copy()->subWeek();
                $nextPeriod = $date->copy()->addWeek();
                break;
                
            case '2weeks':
                $startDate = $date->copy()->startOfWeek()->startOfDay();
                $endDate = $date->copy()->startOfWeek()->addDays(13)->startOfDay(); // 2 weeks
                $prevPeriod = $date->copy()->subWeeks(2);
                $nextPeriod = $date->copy()->addWeeks(2);
                break;
                
            case 'month':
            default:
                $startDate = $date->copy()->startOfMonth()->startOfDay();
                $endDate = $date->copy()->endOfMonth()->startOfDay(); // Normalize to midnight
                $prevPeriod = $date->copy()->subMonth();
                $nextPeriod = $date->copy()->addMonth();
                break;
        }
        
        // Generate days array
        $days = [];
        $currentDay = $startDate->copy();
        while ($currentDay <= $endDate) {
            $days[] = $currentDay->copy();
            $currentDay->addDay();
        }
        
        // Get all active properties with their bookings for the period
        $properties = Property::active()
            ->with(['bookings' => function ($query) use ($startDate, $endDate) {
                $query->withTrashed() // Include soft deleted bookings for debug
                    ->where(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('check_in', [$startDate, $endDate])
                      ->orWhereBetween('check_out', [$startDate, $endDate])
                      ->orWhere(function ($q2) use ($startDate, $endDate) {
                          $q2->where('check_in', '<=', $startDate)
                             ->where('check_out', '>=', $endDate);
                      });
                })->orderBy('check_in');
            }])
            ->orderBy('name')
            ->get();
        
        return view('dashboard', [
            'properties' => $properties,
            'days' => $days,
            'currentDate' => $date,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'view' => $view,
            'prevPeriod' => $prevPeriod,
            'nextPeriod' => $nextPeriod,
            'prevYear' => $date->copy()->subYear(),
            'nextYear' => $date->copy()->addYear(),
            'canNavigateForward' => $nextPeriod->isBefore($maxDate),
            'canNavigateYearForward' => $date->copy()->addYear()->isBefore($maxDate),
        ]);
    }
    
    public function booking($id)
    {
        // Load with trashed to allow viewing deleted bookings
        $booking = Booking::withTrashed()->with('property')->findOrFail($id);
        return response()->json($booking);
    }
}
