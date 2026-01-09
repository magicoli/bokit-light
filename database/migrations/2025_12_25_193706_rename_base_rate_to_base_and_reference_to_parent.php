<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Only rename if table exists
        if (!Schema::hasTable('rates')) {
            return;
        }

        Schema::table('rates', function (Blueprint $table) {
            // Rename reference_rate_id to parent_rate_id (if source exists and target doesn't)
            if (Schema::hasColumn('rates', 'reference_rate_id') && 
                !Schema::hasColumn('rates', 'parent_rate_id')) {
                $table->renameColumn('reference_rate_id', 'parent_rate_id');
            }
            
            // Rename base_rate to base (if source exists and target doesn't)
            if (Schema::hasColumn('rates', 'base_rate') && 
                !Schema::hasColumn('rates', 'base')) {
                $table->renameColumn('base_rate', 'base');
            }
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
