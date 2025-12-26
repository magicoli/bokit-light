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
     * Calculate price for test booking (widget)
     */
    public function calculate(Request $request)
    {
        try {
            $validated = $request->validate([
                "check_in" => "required|date",
                "check_out" => "required|date|after:check_in",
                "adults" => "required|integer|min:1",
                "children" => "nullable|integer|min:0",
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
            $checkIn = \Carbon\Carbon::parse($validated["check_in"]);
            $checkOut = \Carbon\Carbon::parse($validated["check_out"]);
            $nights = $checkIn->diffInDays($checkOut);
            $adults = $validated["adults"];
            $children = $validated["children"] ?? 0;
            $totalGuests = $adults + $children;

            // Get all units
            $units = Unit::with(['property'])->where('is_active', true)->get();
            
            $results = [];
            
            foreach ($units as $unit) {
                // Check max_guests capacity
                if ($unit->max_guests && $totalGuests > $unit->max_guests) {
                    continue; // Skip this unit
                }
                
                // Find applicable rate
                $rate = $this->findApplicableRateForUnit($unit, $checkIn, $checkOut);
                
                if (!$rate) {
                    continue; // No rate found for this unit
                }
                
                // Calculate price
                $variables = [
                    'base' => (float) $rate->base,
                    'booking_nights' => $nights,
                    'nights' => $nights,
                    'guests' => $totalGuests,
                    'adults' => $adults,
                    'children' => $children,
                ];
                
                $total = $this->evaluateFormula($rate->calculation_formula, $variables);
                
                // Simplify names: don't repeat property name if unit name = property name
                $unitDisplayName = ($unit->name === $unit->property->name) 
                    ? $unit->name 
                    : $unit->name;
                
                $results[] = [
                    'property_id' => $unit->property_id,
                    'property_name' => $unit->property->name,
                    'unit_name' => $unitDisplayName,
                    'rate_name' => $rate->display_name,
                    'nights' => $nights,
                    'price_per_night' => $nights > 0 ? $total / $nights : 0,
                    'total' => $total,
                ];
            }
            
            // Sort by property_name, then by unit_name
            usort($results, function($a, $b) {
                $propCompare = strcmp($a['property_name'], $b['property_name']);
                if ($propCompare !== 0) return $propCompare;
                return strcmp($a['unit_name'], $b['unit_name']);
            });
            
            return back()
                ->with('calculation_results', $results)
                ->withInput();
                
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ":" . __LINE__ . " " . $e->getMessage(), [
                "trace" => $e->getTraceAsString(),
            ]);
            notice("Calculation failed: " . $e->getMessage(), "error");
            
            return back()->withInput();
        }
    }
    
    /**
     * Find applicable rate for a unit
     */
    private function findApplicableRateForUnit($unit, $checkIn, $checkOut): ?Rate
    {
        $propertyId = $unit->property_id;
        
        // Try unit-specific rate first
        $rate = Rate::where('is_active', true)
            ->where('property_id', $propertyId)
            ->where('unit_id', $unit->id)
            ->orderBy('priority', 'desc')
            ->first();
            
        if ($rate) {
            return $rate;
        }
        
        // Try unit type rate
        if ($unit->unit_type) {
            $rate = Rate::where('is_active', true)
                ->where('property_id', $propertyId)
                ->where('unit_type', $unit->unit_type)
                ->orderBy('priority', 'desc')
                ->first();
                
            if ($rate) {
                return $rate;
            }
        }
        
        // Try property-wide rate
        $rate = Rate::where('is_active', true)
            ->where('property_id', $propertyId)
            ->whereNull('unit_id')
            ->whereNull('unit_type')
            ->orderBy('priority', 'desc')
            ->first();
            
        return $rate;
    }
    
    /**
     * Evaluate formula safely
     */
    private function evaluateFormula(string $formula, array $variables): float
    {
        $evaluatedFormula = $formula;
        
        foreach ($variables as $key => $value) {
            if (is_numeric($value)) {
                $evaluatedFormula = str_replace($key, $value, $evaluatedFormula);
            }
        }
        
        // Validate formula
        if (!preg_match('/^[0-9+\-*\/\s().]+$/', $evaluatedFormula)) {
            throw new \Exception("Invalid formula: {$evaluatedFormula}");
        }
        
        try {
            $result = eval("return {$evaluatedFormula};");
            return (float) $result;
        } catch (\ParseError $e) {
            throw new \Exception("Formula error: {$e->getMessage()}");
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
