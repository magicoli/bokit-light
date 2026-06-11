<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Convert legacy booking statuses to the canonical internal set
 * (see Booking::STATUSES): confirmed, option, quote, blocked, cancelled
 * (+ internal cancelled variants deleted/vanished).
 */
return new class extends Migration
{
    private const MAP = [
        'pending' => 'option',
        'request' => 'option',
        'inquiry' => 'quote',
        'unavailable' => 'blocked',
        'cancelled_by_owner' => 'cancelled',
        'cancelled_by_guest' => 'cancelled',
    ];

    public function up(): void
    {
        foreach (self::MAP as $legacy => $canonical) {
            DB::table('bookings')->where('status', $legacy)->update(['status' => $canonical]);
        }
    }

    public function down(): void
    {
        // Lossy reverse: pending/request both became option, the
        // cancelled_by_* variants stay cancelled.
        DB::table('bookings')->where('status', 'option')->update(['status' => 'pending']);
        DB::table('bookings')->where('status', 'quote')->update(['status' => 'inquiry']);
        DB::table('bookings')->where('status', 'blocked')->update(['status' => 'unavailable']);
    }
};
