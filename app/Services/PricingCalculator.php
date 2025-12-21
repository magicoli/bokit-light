<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Rate;
use App\Models\PricingCalculation;
use Illuminate\Support\Facades\Log;

class PricingCalculator
{
    /**
     * Calculate pricing for a booking
     */
    public function calculate(Booking $booking): PricingCalculation
    {
        $rate = $this->findApplicableRate($booking);
        
        if (!$rate) {
            throw new \Exception("No applicable rate found for booking #{$booking->id}");
        }

        $variables = $this->buildVariables($booking, $rate);
        $baseAmount = $this->evaluateFormula($rate->calculation_formula, $variables);

        $calculation = PricingCalculation::create([
            'booking_id' => $booking->id,
            'total_amount' => $baseAmount,
            'base_amount' => $baseAmount,
            'calculation_snapshot' => [
                'rate_id' => $rate->id,
                'rate_name' => $rate->name,
                'formula' => $rate->calculation_formula,
                'variables' => $variables,
                'base_amount' => $baseAmount,
                'calculated_at' => now()->toISOString(),
            ],
        ]);

        // Update booking price
        $booking->update(['price' => $baseAmount]);

        return $calculation;
    }

    /**
     * Find the applicable rate for a booking
     * Priority: unit > unit_type > property
     */
    private function findApplicableRate(Booking $booking): ?Rate
    {
        $unit = $booking->unit;
        $property = $booking->property;

        // Priority 1: Unit-specific rate
        $rate = Rate::active()
            ->forUnit($unit->id)
            ->orderBy('priority', 'desc')
            ->first();

        if ($rate) {
            return $rate;
        }

        // Priority 2: Unit type rate
        if ($unit->unit_type) {
            $rate = Rate::active()
                ->forUnitType($unit->unit_type)
                ->orderBy('priority', 'desc')
                ->first();

            if ($rate) {
                return $rate;
            }
        }

        // Priority 3: Property rate
        $rate = Rate::active()
            ->forProperty($property->id)
            ->orderBy('priority', 'desc')
            ->first();

        return $rate;
    }

    /**
     * Build variables for formula evaluation
     */
    private function buildVariables(Booking $booking, Rate $rate): array
    {
        return [
            'rate' => (float) $rate->base_amount,
            'booking_nights' => $booking->nights(),
            'guests' => ($booking->adults ?? 0) + ($booking->children ?? 0),
            'adults' => $booking->adults ?? 0,
            'children' => $booking->children ?? 0,
            'check_in' => $booking->check_in->format('Y-m-d'),
            'check_out' => $booking->check_out->format('Y-m-d'),
            'unit_id' => $booking->unit_id,
            'property_id' => $booking->property_id,
        ];
    }

    /**
     * Evaluate mathematical formula safely
     */
    private function evaluateFormula(string $formula, array $variables): float
    {
        // Replace variables in formula
        $evaluatedFormula = $formula;
        foreach ($variables as $key => $value) {
            if (is_numeric($value)) {
                $evaluatedFormula = str_replace($key, $value, $evaluatedFormula);
            }
        }

        // Validate formula contains only numbers and operators
        if (!preg_match('/^[0-9+\-*\/\s().]+$/', $evaluatedFormula)) {
            throw new \Exception("Invalid formula characters: {$evaluatedFormula}");
        }

        try {
            // Use eval for simple math (safe in this controlled context)
            $result = eval("return {$evaluatedFormula};");
            
            if (!is_numeric($result)) {
                throw new \Exception("Formula did not return numeric result: {$result}");
            }

            return (float) $result;
        } catch (\ParseError $e) {
            throw new \Exception("Formula parse error: {$e->getMessage()}");
        }
    }

    /**
     * Recalculate pricing for existing booking
     */
    public function recalculate(Booking $booking): PricingCalculation
    {
        // Delete existing calculation
        PricingCalculation::where('booking_id', $booking->id)->delete();
        
        // Recalculate
        return $this->calculate($booking);
    }
}