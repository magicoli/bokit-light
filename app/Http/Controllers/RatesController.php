<?php

namespace App\Http\Controllers;

use App\Forms;
use App\Models\Rate;
use App\Models\Coupon;
use App\Models\Unit;
use App\Models\Property;
use App\Models\Booking;
use App\Services\RatesCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class RatesController extends Controller
{
    public function __construct(private RatesCalculator $ratesCalculator) {}

    /**
     * Show rates management page
     */
    public function index()
    {
        try {
            $rates = Rate::with(["unit", "parentRate", "rateProperty"])
                ->orderBy("priority", "desc")
                ->get();

            // Get authorized properties for the user
            $query = Property::query();
            
            if (!auth()->user()->isAdmin()) {
                $query->whereHas('users', function ($q) {
                    $q->where('users.id', auth()->id());
                });
            }
            
            $properties = $query->get();
            
            // Get all units and coupons for dynamic select population
            $units = Unit::with('property')->get();
            $coupons = Coupon::where('is_active', true)->get();
            
            // Get unique unit types from both units table and rates table
            $unitTypesFromUnits = $units->pluck('unit_type')->filter()->unique()->values();
            $unitTypesFromRates = Rate::whereNotNull('unit_type')
                ->distinct()
                ->pluck('unit_type')
                ->filter()
                ->unique();
            $allUnitTypes = $unitTypesFromUnits->merge($unitTypesFromRates)->unique()->sort()->values();
            
            // Prepare priority options
            $priorityOptions = [
                'high' => __('rates.priority_high'),
                'normal' => __('rates.priority_normal'),
                'low' => __('rates.priority_low'),
            ];

            return view("rates", compact("rates", "properties", "units", "coupons", "allUnitTypes", "priorityOptions"));
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ":" . __LINE__ . " " . $e->getMessage(), [
                "trace" => $e->getTraceAsString(),
            ]);
            notice($e->getMessage(), "error");
            
            return back();
        }
    }

    /**
     * Store a new rate
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate(Rate::validationRules());
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error(__METHOD__ . ":" . __LINE__ . " " . $e->getMessage(), [
                "error" => $e->getMessage(),
                "errors" => $e->validator->errors()->all(),
            ]);
            
            // Display first validation error to user
            $firstError = $e->validator->errors()->first();
            notice($firstError, "error");

            return back()->withInput()->withErrors($e->validator);
        }

        try {
            Rate::create($validated);
            notice("Rate created successfully", "success");
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ":" . __LINE__ . " " . $e->getMessage(), [
                "trace" => $e->getTraceAsString(),
            ]);
            
            notice($e->getMessage(), "error");

            return back()->withInput();
        }

        return back();
    }

    /**
     * Update a rate
     */
    public function update(Request $request, Rate $rate)
    {
        try {
            $validated = $request->validate(Rate::validationRules(forUpdate: true));
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error(__METHOD__ . ":" . __LINE__ . " " . $e->getMessage(), [
                "trace" => $e->getTraceAsString(),
            ]);
            
            $firstError = $e->validator->errors()->first();
            notice($firstError, "error");
            
            return back()->withInput()->withErrors($e->validator);
        }

        try {
            $rate->update($validated);
            notice("Rate updated successfully", "success");
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ":" . __LINE__ . " " . $e->getMessage(), [
                "trace" => $e->getTraceAsString(),
            ]);
            notice($e->getMessage(), "error");
            
            return back()->withInput();
        }

        return back();
    }

    /**
     * Delete a rate
     */
    public function destroy(Rate $rate)
    {
        try {
            $rate->delete();
            notice("Rate deleted successfully", "success");
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ":" . __LINE__ . " " . $e->getMessage(), [
                "trace" => $e->getTraceAsString(),
            ]);
            notice($e->getMessage(), "error");
            
            return back();
        }

        return back();
    }

    /**
     * Show rates calculator/test page
     */
    public function calculator()
    {
        try {
            $properties = Property::all();
            $units = Unit::with("property")->get();
            
            return view("rates.calculator", compact("properties", "units"));
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ":" . __LINE__ . " " . $e->getMessage(), [
                "trace" => $e->getTraceAsString(),
            ]);
            notice($e->getMessage(), "error");
            
            return back();
        }
    }

    /**
     * Calculate price for test booking
     */
    public function calculate(Request $request)
    {
        try {
            $validated = $request->validate([
                "property_id" => "required|exists:properties,id",
                "unit_id" => "required|exists:units,id",
                "check_in" => "required|date",
                "check_out" => "required|date|after:check_in",
                "adults" => "required|integer|min:1",
                "children" => "integer|min:0",
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error(__METHOD__ . ":" . __LINE__ . " " . $e->getMessage(), [
                "error" => $e->getMessage(),
                "errors" => $e->validator->errors()->all(),
            ]);
            
            $firstError = $e->validator->errors()->first();
            notice($firstError, "error");
            
            return back()->withInput()->withErrors($e->validator);
        }

        try {
            // Create test booking
            $booking = new Booking([
                "property_id" => $validated["property_id"],
                "unit_id" => $validated["unit_id"],
                "check_in" => $validated["check_in"],
                "check_out" => $validated["check_out"],
                "adults" => $validated["adults"],
                "children" => $validated["children"] ?? 0,
            ]);

            // Load relationships
            $booking->unit = Unit::find($validated["unit_id"]);
            $booking->property = Property::find($validated["property_id"]);

            $calculation = $this->ratesCalculator->calculate($booking);
            
            notice("Price calculated successfully", "success");
            
            return back()
                ->with("calculation", $calculation)
                ->with("test_booking", $booking);
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ":" . __LINE__ . " " . $e->getMessage(), [
                "trace" => $e->getTraceAsString(),
            ]);
            notice("Calculation failed: " . $e->getMessage(), "error");
            
            return back()->withInput();
        }
    }

    /**
     * API endpoint for parent rates (potential parent rates for a property)
     */
    public function parentRates($propertyId): JsonResponse
    {
        try {
            $rates = Rate::with(["parentRate"])
                ->where("property_id", $propertyId)
                ->orWhereNull("property_id")
                ->get()
                ->map(function ($rate) {
                    return [
                        "id" => $rate->id,
                        "display_name" => $rate->display_name,
                        "base" => $rate->base,
                    ];
                });

            return response()->json($rates);
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ":" . __LINE__ . " " . $e->getMessage(), [
                "trace" => $e->getTraceAsString(),
            ]);
            
            return response()->json(
                [
                    "error" => "Failed to fetch parent rates",
                    "message" => $e->getMessage(),
                ],
                500,
            );
        }
    }
}
