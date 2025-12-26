<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->integer('bedrooms')->nullable()->after('unit_type');
            $table->integer('max_guests')->nullable()->after('bedrooms');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn(['bedrooms', 'max_guests']);
        });
    }
};
