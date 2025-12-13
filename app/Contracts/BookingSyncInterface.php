<?php

namespace App\Contracts;

interface BookingSyncInterface
{
    /**
     * Get the control string for this sync event
     * Each sync class must implement this method to define
     * what parameters are needed for its control string
     *
     * @return string Control string for this event
     */
    public function getControlString(): string;

    /**
     * Calculate a control string from variable arguments
     * This method provides a consistent way to calculate control strings
     * across all sync implementations
     *
     * @param mixed ...$args Variable arguments for control string calculation
     * @return string Control string value
     */
    public static function calculateControlString(...$args): string;
}
