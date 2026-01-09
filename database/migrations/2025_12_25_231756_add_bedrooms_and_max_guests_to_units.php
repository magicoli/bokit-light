<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            if (!Schema::hasColumn('units', 'bedrooms')) {
                $table->integer('bedrooms')->nullable()->after('unit_type');
            }
            if (!Schema::hasColumn('units', 'max_guests')) {
                $table->integer('max_guests')->nullable()->after('bedrooms');
            }
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn(['bedrooms', 'max_guests']);
        });
    }
};
