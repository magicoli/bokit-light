<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make bookings.unit_id nullable to support group booking summary rows.
 *
 * Group bookings (e.g. "Site entier" in HBook) are stored as:
 *   - One summary row with unit_id = NULL, holding the total price/adults/children
 *   - N member rows (one per blocked unit) with unit_id = unit.id, price = NULL
 *
 * All rows in the same group share the same uid ("hbook:{hbook_uid}").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_id')->nullable(false)->change();
        });
    }
};
