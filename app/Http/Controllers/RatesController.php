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
                $query->whereHas("users", function ($q) {
                    $q->where("users.id", auth()->id());
                });
            }

            $properties = $query->get();

            // Get all units and coupons for dynamic select population
            $units = Unit::with("property")->get();
            $coupons = Coupon::where("is_active", true)->get();

            // Get unique unit types from both units table and rates table
            $unitTypesFromUnits = $units
                ->pluck("unit_type")
                ->filter()
                ->unique()
                ->values();
            $unitTypesFromRates = Rate::whereNotNull("unit_type")
                ->distinct()
                ->pluck("unit_type")
                ->filter()
                ->unique();
            $allUnitTypes = $unitTypesFromUnits
                ->merge($unitTypesFromRates)
                ->unique()
                ->sort()
                ->values();

            // Prepare priority options
            $priorityOptions = [
                "high" => __("rates.priority_high"),
                "normal" => __("rates.priority_normal"),
                "low" => __("rates.priority_low"),
            ];

            return view(
                "rates",
                compact(
                    "rates",
                    "properties",
                    "units",
                    "coupons",
                    "allUnitTypes",
                    "priorityOptions",
                ),
            );
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
            $validated = $request->validate(
                Rate::validationRules(forUpdate: true),
            );
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
            $units = Unit::with(["property"])
                ->where("is_active", true)
                ->get();

            $results = [];

            foreach ($units as $unit) {
                // Check max_guests capacity
                if ($unit->max_guests && $totalGuests > $unit->max_guests) {
                    continue; // Skip this unit
                }

                // Find applicable rate
                $rate = $this->findApplicableRateForUnit(
                    $unit,
                    $checkIn,
                    $checkOut,
                );

                if (!$rate) {
                    continue; // No rate found for this unit
                }

                // Calculate price
                $variables = [
                    "base" => (float) $rate->base,
                    "booking_nights" => $nights,
                    "nights" => $nights,
                    "guests" => $totalGuests,
                    "adults" => $adults,
                    "children" => $children,
                ];

                $formula = $rate->calculation_formula;

                // Inject parent formula if parent_rate is used
                if (
                    $rate->parent_rate_id &&
                    $rate->parentRate &&
                    str_contains($formula, "parent_rate")
                ) {
                    $parentFormula = "(" . $rate->parentRate->calculation_formula . ")";
                    $formula = str_replace("parent_rate", $parentFormula, $formula);
                }

                $total = $this->evaluateFormula($formula, $variables);

                // Simplify names: don't repeat property name if unit name = property name
                $unitDisplayName =
                    $unit->name === $unit->property->name
                        ? $unit->name
                        : $unit->name;

                $results[] = [
                    "property_id" => $unit->property_id,
                    "property_name" => $unit->property->name,
                    "unit_name" => $unitDisplayName,
                    "rate_name" => $rate->display_name,
                    "nights" => $nights,
                    "price_per_night" => $nights > 0 ? $total / $nights : 0,
                    "total" => $total,
                ];
            }

            // Sort by property_name, then by unit_name
            usort($results, function ($a, $b) {
                $propCompare = strcmp($a["property_name"], $b["property_name"]);
                if ($propCompare !== 0) {
                    return $propCompare;
                }
                return strcmp($a["unit_name"], $b["unit_name"]);
            });

            // If no results, return with error message
            if (empty($results)) {
                return back()
                    ->withErrors(["calculation" => __("rates.no_results")])
                    ->withInput();
            }

            return back()->with("calculation_results", $results)->withInput();
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ":" . __LINE__ . " " . $e->getMessage(), [
                "trace" => $e->getTraceAsString(),
            ]);
            notice("Calculation failed: " . $e->getMessage(), "error");

            return back()->withInput();
        }
    }

    /**
     * Find applicable rate for a unit with proper priority system
     * 
     * Priority order (most specific to least specific):
     * 1. Scope: unit > unit_type > property
     * 2. Coupon (if provided)
     * 3. Stay dates (stay_from/stay_to)
     * 4. Booking dates (booking_from/booking_to)
     * 5. Priority field (high > normal > low)
     */
    private function findApplicableRateForUnit(
        $unit,
        $checkIn,
        $checkOut,
        $couponCode = null
    ): ?Rate {
        $propertyId = $unit->property_id;
        $bookingDate = now(); // When the booking is made
        
        // Get all potentially applicable rates
        $rates = Rate::where("is_active", true)
            ->where("property_id", $propertyId)
            ->where(function ($query) use ($unit) {
                // Scope: unit OR unit_type OR property-wide (both null)
                $query->where("unit_id", $unit->id);
                
                if ($unit->unit_type) {
                    $query->orWhere("unit_type", $unit->unit_type);
                }
                
                $query->orWhere(function($q) {
                    $q->whereNull("unit_id")->whereNull("unit_type");
                });
            })
            ->where(function ($query) use ($couponCode) {
                // Coupon: matches provided coupon OR no coupon restriction
                if ($couponCode) {
                    $query->where("coupon_code", $couponCode)
                          ->orWhereNull("coupon_code");
                } else {
                    $query->whereNull("coupon_code");
                }
            })
            ->where(function ($query) use ($checkIn, $checkOut) {
                // Stay dates: booking period overlaps with rate period OR no restriction
                $query->where(function ($q) use ($checkIn, $checkOut) {
                    $q->whereNull("stay_from")
                      ->whereNull("stay_to");
                })->orWhere(function ($q) use ($checkIn, $checkOut) {
                    // Rate period must overlap with stay period
                    $q->where(function ($sq) use ($checkIn) {
                        $sq->whereNull("stay_from")
                           ->orWhere("stay_from", "<=", $checkIn);
                    })->where(function ($sq) use ($checkOut) {
                        $sq->whereNull("stay_to")
                           ->orWhere("stay_to", ">=", $checkOut);
                    });
                });
            })
            ->where(function ($query) use ($bookingDate) {
                // Booking dates: booking date within allowed period OR no restriction
                $query->where(function ($q) use ($bookingDate) {
                    $q->whereNull("booking_from")
                      ->whereNull("booking_to");
                })->orWhere(function ($q) use ($bookingDate) {
                    $q->where(function ($sq) use ($bookingDate) {
                        $sq->whereNull("booking_from")
                           ->orWhere("booking_from", "<=", $bookingDate);
                    })->where(function ($sq) use ($bookingDate) {
                        $sq->whereNull("booking_to")
                           ->orWhere("booking_to", ">=", $bookingDate);
                    });
                });
            })
            ->get();
        
        if ($rates->isEmpty()) {
            return null;
        }
        
        // Sort by priority (most specific first)
        $sorted = $rates->sort(function ($a, $b) {
            // 1. Scope priority: unit > unit_type > property
            $aScope = $a->unit_id ? 3 : ($a->unit_type ? 2 : 1);
            $bScope = $b->unit_id ? 3 : ($b->unit_type ? 2 : 1);
            if ($aScope !== $bScope) return $bScope - $aScope;
            
            // 2. Coupon: has coupon > no coupon
            $aCoupon = $a->coupon_code ? 1 : 0;
            $bCoupon = $b->coupon_code ? 1 : 0;
            if ($aCoupon !== $bCoupon) return $bCoupon - $aCoupon;
            
            // 3. Stay dates: has dates > no dates
            $aStay = ($a->stay_from || $a->stay_to) ? 1 : 0;
            $bStay = ($b->stay_from || $b->stay_to) ? 1 : 0;
            if ($aStay !== $bStay) return $bStay - $aStay;
            
            // 4. Booking dates: has dates > no dates
            $aBooking = ($a->booking_from || $a->booking_to) ? 1 : 0;
            $bBooking = ($b->booking_from || $b->booking_to) ? 1 : 0;
            if ($aBooking !== $bBooking) return $bBooking - $aBooking;
            
            // 5. Priority field: high=3, normal=2, low=1
            $priorityMap = ['high' => 3, 'normal' => 2, 'low' => 1];
            $aPriority = $priorityMap[$a->priority] ?? 2;
            $bPriority = $priorityMap[$b->priority] ?? 2;
            return $bPriority - $aPriority;
        });
        
        return $sorted->first();
    }

    /**
     * Evaluate formula safely
     */
    private function evaluateFormula(string $formula, array $variables): float
    {
        $evaluatedFormula = $formula;

        foreach ($variables as $key => $value) {
            if (is_numeric($value)) {
                $evaluatedFormula = str_replace(
                    $key,
                    $value,
                    $evaluatedFormula,
                );
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
