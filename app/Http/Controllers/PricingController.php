<?php

namespace App\Http\Controllers;

use App\Models\Rate;
use App\Models\Unit;
use App\Models\Property;
use App\Models\Booking;
use App\Services\PricingCalculator;
use Illuminate\Http\Request;

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
        $rates = Rate::with(['unit', 'property'])
            ->orderBy('priority', 'desc')
            ->get();

        $properties = Property::all();
        $units = Unit::with('property')->get();
        $unitTypes = Unit::distinct()->pluck('unit_type')->filter();

        return view('pricing.index', compact('rates', 'properties', 'units', 'unitTypes'));
    }

    /**
     * Store a new rate
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'unit_id' => 'nullable|exists:units,id',
            'unit_type' => 'nullable|string|max:100',
            'property_id' => 'nullable|exists:properties,id',
            'base_amount' => 'required|numeric|min:0',
            'calculation_formula' => 'required|string|max:500',
            'is_active' => 'boolean',
            'priority' => 'integer|min:0',
        ]);

        // Ensure only one scope is set
        $scopes = array_filter([
            'unit_id' => $validated['unit_id'] ?? null,
            'unit_type' => $validated['unit_type'] ?? null,
            'property_id' => $validated['property_id'] ?? null,
        ]);

        if (count($scopes) !== 1) {
            return back()->withErrors([
                'scope' => 'Exactly one of unit, unit type, or property must be selected'
            ]);
        }

        Rate::create($validated);

        return back()->with('success', 'Rate created successfully');
    }

    /**
     * Update a rate
     */
    public function update(Request $request, Rate $rate)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'unit_id' => 'nullable|exists:units,id',
            'unit_type' => 'nullable|string|max:100',
            'property_id' => 'nullable|exists:properties,id',
            'base_amount' => 'sometimes|required|numeric|min:0',
            'calculation_formula' => 'sometimes|required|string|max:500',
            'is_active' => 'sometimes|boolean',
            'priority' => 'sometimes|integer|min:0',
        ]);

        // Ensure only one scope is set
        $scopes = array_filter([
            'unit_id' => $validated['unit_id'] ?? $rate->unit_id,
            'unit_type' => $validated['unit_type'] ?? $rate->unit_type,
            'property_id' => $validated['property_id'] ?? $rate->property_id,
        ]);

        if (count($scopes) !== 1) {
            return back()->withErrors([
                'scope' => 'Exactly one of unit, unit type, or property must be selected'
            ]);
        }

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
}