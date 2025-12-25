<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rates', function (Blueprint $table) {
            // Rename reference_rate_id to parent_rate_id
            $table->renameColumn('reference_rate_id', 'parent_rate_id');
            
            // Rename base_rate to base
            $table->renameColumn('base_rate', 'base');
        });
    }

    public function down(): void
    {
        Schema::table('rates', function (Blueprint $table) {
            $table->renameColumn('parent_rate_id', 'reference_rate_id');
            $table->renameColumn('base', 'base_rate');
        });
    }
};
