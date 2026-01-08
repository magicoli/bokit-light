<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('model_type'); // Booking, Property, etc.
            $table->unsignedBigInteger('model_id');
            $table->string('source'); // 'airbnb_ical', 'beds24_api', 'user:email@example.com', etc.
            $table->string('field'); // Field name that changed
            $table->text('old_value')->nullable(); // Previous value
            $table->text('new_value')->nullable(); // New value
            $table->timestamp('created_at');
            
            // Indexes for performance
            $table->index(['model_type', 'model_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
