<?php

namespace App\Http\Controllers;

use App\Models\Rate;
use App\Models\Coupon;
use App\Models\Unit;
use App\Models\Property;
use App\Models\Booking;
use App\Services\PricingCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PricingController extends Controller
{
    public function __construct(private PricingCalculator $pricingCalculator)
    {
    }

    /**
     * Show pricing management page
     */
    public function index()
    {
        $rates = Rate::with(['unit', 'referenceRate', 'rateProperty'])
            ->orderBy('priority', 'desc')
            ->get();

        $properties = Property::all();
        $units = Unit::with('property')->get();
        $unitTypes = Unit::distinct()->pluck('unit_type')->filter();
        $coupons = Coupon::active()->get();

        return view('pricing.index', compact('rates', 'properties', 'units', 'unitTypes', 'coupons'));
    }

    /**
     * Store a new rate
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'unit_id' => 'nullable|exists:units,id',
            'unit_type' => 'nullable|string|max:100',
            'property_id' => 'required|exists:properties,id',
            'base_rate' => 'required|numeric|min:0',
            'reference_rate_id' => 'nullable|exists:rates,id',
            'calculation_formula' => 'required|string|max:500',
            'is_active' => 'boolean',
            'priority' => 'in:high,normal,low',
            'booking_from' => 'nullable|date',
            'booking_to' => 'nullable|date|after_or_equal:booking_from',
            'stay_from' => 'nullable|date',
            'stay_to' => 'nullable|date|after_or_equal:stay_from',
        ]);

        // Ensure only one scope is set
        $scopes = array_filter([
            'unit_id' => $validated['unit_id'] ?? null,
            'unit_type' => $validated['unit_type'] ?? null,
            'coupon' => $validated['coupon'] ?? null,
        ]);

        if (count($scopes) > 1) {
            return back()->withErrors([
                'scope' => 'Only one of unit, unit type, or coupon can be set'
            ]);
        }

        // Handle coupon logic (convert to appropriate fields)
        if (!empty($validated['coupon'])) {
            // This will be implemented later
            $validated['unit_type'] = $validated['coupon'];
        }
        
        // Remove coupon field as it's not a DB column
        unset($validated['coupon']);

        Rate::create($validated);

        return back()->with('success', 'Rate created successfully');
    }

    /**
     * Update a rate
     */
    public function update(Request $request, Rate $rate)
    {
        $validated = $request->validate([
            'name' => 'sometimes|nullable|string|max:255',
            'unit_id' => 'nullable|exists:units,id',
            'unit_type' => 'nullable|string|max:100',
            'base_rate' => 'sometimes|required|numeric|min:0',
            'reference_rate_id' => 'nullable|exists:rates,id',
            'calculation_formula' => 'sometimes|required|string|max:500',
            'is_active' => 'sometimes|boolean',
            'priority' => 'sometimes|in:high,normal,low',
        ]);

        $rate->update($validated);

        return back()->with('success', 'Rate updated successfully');
    }

    /**
     * Delete a rate
     */
    public function destroy(Rate $rate)
    {
        $rate->delete();
        return back()->with('success', 'Rate deleted successfully');
    }

    /**
     * Show pricing calculator/test page
     */
    public function calculator()
    {
        $properties = Property::all();
        $units = Unit::with('property')->get();

        return view('pricing.calculator', compact('properties', 'units'));
    }

    /**
     * Calculate price for test booking
     */
    public function calculate(Request $request)
    {
        $validated = $request->validate([
            'property_id' => 'required|exists:properties,id',
            'unit_id' => 'required|exists:units,id',
            'check_in' => 'required|date',
            'check_out' => 'required|date|after:check_in',
            'adults' => 'required|integer|min:1',
            'children' => 'integer|min:0',
        ]);

        // Create test booking
        $booking = new Booking([
            'property_id' => $validated['property_id'],
            'unit_id' => $validated['unit_id'],
            'check_in' => $validated['check_in'],
            'check_out' => $validated['check_out'],
            'adults' => $validated['adults'],
            'children' => $validated['children'] ?? 0,
        ]);

        // Load relationships
        $booking->unit = Unit::find($validated['unit_id']);
        $booking->property = Property::find($validated['property_id']);

        try {
            $calculation = $this->pricingCalculator->calculate($booking);
            
            return back()->with('success', 'Price calculated successfully')
                ->with('calculation', $calculation)
                ->with('test_booking', $booking);
                
        } catch (\Exception $e) {
            return back()->withErrors([
                'calculation' => 'Calculation failed: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * API endpoint for reference rates
     */
    public function referenceRates($propertyId): JsonResponse
    {
        $rates = Rate::with(['referenceRate'])
            ->where('property_id', $propertyId)
            ->orWhereNull('property_id')
            ->get()
            ->map(function ($rate) {
                return [
                    'id' => $rate->id,
                    'display_name' => $rate->display_name,
                    'base_rate' => $rate->base_rate,
                ];
            });

        return response()->json($rates);
    }
}