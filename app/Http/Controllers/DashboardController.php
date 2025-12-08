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
        // Get date range from query string
        $view = $request->get('view', 'month'); // month or week
        $date = $request->has('date') 
            ? Carbon::parse($request->date)
            : Carbon::now();
        
        if ($view === 'week') {
            $startDate = $date->copy()->startOfWeek();
            $endDate = $date->copy()->endOfWeek();
        } else {
            $startDate = $date->copy()->startOfMonth();
            $endDate = $date->copy()->endOfMonth();
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
                // Get bookings that overlap with our date range
                // Include check_out day for visual display
                $query->where(function ($q) use ($startDate, $endDate) {
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
            'prevPeriod' => $view === 'week' ? $date->copy()->subWeek() : $date->copy()->subMonth(),
            'nextPeriod' => $view === 'week' ? $date->copy()->addWeek() : $date->copy()->addMonth(),
            'prevYear' => $date->copy()->subYear(),
            'nextYear' => $date->copy()->addYear(),
        ]);
    }
    
    public function booking(Booking $booking)
    {
        $booking->load('property');
        return response()->json($booking);
    }
}
