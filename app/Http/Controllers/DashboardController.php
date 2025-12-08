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
        // Get month from query string or default to current month
        $date = $request->has('month') 
            ? Carbon::parse($request->month)
            : Carbon::now();
        
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();
        
        // Get all active properties with their bookings for the month
        $properties = Property::active()
            ->with(['bookings' => function ($query) use ($startOfMonth, $endOfMonth) {
                $query->inRange($startOfMonth, $endOfMonth)
                      ->orderBy('check_in');
            }])
            ->orderBy('name')
            ->get();
        
        // Generate calendar grid (including days from previous/next month)
        $startOfCalendar = $startOfMonth->copy()->startOfWeek();
        $endOfCalendar = $endOfMonth->copy()->endOfWeek();
        
        $calendarDays = [];
        $currentDay = $startOfCalendar->copy();
        
        while ($currentDay <= $endOfCalendar) {
            $calendarDays[] = [
                'date' => $currentDay->copy(),
                'isCurrentMonth' => $currentDay->month === $date->month,
                'isToday' => $currentDay->isToday(),
            ];
            $currentDay->addDay();
        }
        
        return view('dashboard', [
            'properties' => $properties,
            'calendarDays' => $calendarDays,
            'currentMonth' => $date,
            'prevMonth' => $date->copy()->subMonth(),
            'nextMonth' => $date->copy()->addMonth(),
        ]);
    }
    
    public function booking(Booking $booking)
    {
        $booking->load('property');
        return response()->json($booking);
    }
}
