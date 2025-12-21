<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Only add columns if they don't exist
        Schema::table('rates', function (Blueprint $table) {
            if (!Schema::hasColumn('rates', 'reference_rate_id')) {
                $table->foreignId('reference_rate_id')->nullable()->constrained('rates')->onDelete('set null');
            }
            if (!Schema::hasColumn('rates', 'priority')) {
                $table->string('priority')->default('normal');
            }
            if (!Schema::hasColumn('rates', 'booking_from')) {
                $table->date('booking_from')->nullable();
            }
            if (!Schema::hasColumn('rates', 'booking_to')) {
                $table->date('booking_to')->nullable();
            }
            if (!Schema::hasColumn('rates', 'stay_from')) {
                $table->date('stay_from')->nullable();
            }
            if (!Schema::hasColumn('rates', 'stay_to')) {
                $table->date('stay_to')->nullable();
            }
            if (!Schema::hasColumn('rates', 'conditions')) {
                $table->json('conditions')->nullable();
            }
            
            // Rename base_amount to base_rate if needed
            if (Schema::hasColumn('rates', 'base_amount')) {
                $table->renameColumn('base_amount', 'base_rate');
            }
        });

        // Create coupons table if it doesn't exist
        if (!Schema::hasTable('coupons')) {
            Schema::create('coupons', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->foreignId('property_id')->constrained()->onDelete('cascade');
                $table->decimal('discount_amount', 10, 2);
                $table->string('discount_type')->default('percentage');
                $table->json('conditions')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['property_id', 'is_active']);
            });
        }

        // Update existing data to set default values for new columns
        \Illuminate\Support\Facades\DB::table('rates')
            ->whereNull('priority')
            ->update(['priority' => 'normal']);
    }

    public function down(): void
    {
        Schema::table('rates', function (Blueprint $table) {
            $table->dropForeign(['reference_rate_id']);
            $table->dropColumn([
                'reference_rate_id',
                'priority',
                'booking_from',
                'booking_to', 
                'stay_from',
                'stay_to',
                'conditions'
            ]);
        });

        Schema::dropIfExists('coupons');
    }
};