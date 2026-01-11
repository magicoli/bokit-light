<?php

use App\Models\Booking;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * Resave all existing bookings to apply timezone conversion via mutators.
     * This fixes dates that were saved without timezone information before
     * the checkIn/checkOut accessors/mutators were implemented.
     */
    public function up(): void
    {
        // Process bookings in chunks to avoid memory issues
        Booking::with("unit.property")->chunk(100, function ($bookings) {
            foreach ($bookings as $booking) {
                if (!$booking->unit || !$booking->unit->property) {
                    Log::warning(
                        "Booking {$booking->id} has no unit or property, skipping timezone fix",
                    );
                    continue;
                }

                $newCheckIn = $booking->unit->shiftAndFormat(
                    $booking->check_in,
                );
                $newCheckOut = $booking->unit->shiftAndFormat(
                    $booking->check_out,
                );

                $note = "DEBUG $newCheckIn - $newCheckOut";

                // Bypass Eloquent and update directly with SQL
                // This avoids issues with mutators/casts detection
                DB::table("bookings")
                    ->where("id", $booking->id)
                    ->update([
                        "check_in" => $newCheckIn,
                        "check_out" => $newCheckOut,
                        "updated_at" => now(),
                    ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot reverse this data migration
        // If rollback needed, restore from backup
    }
};
