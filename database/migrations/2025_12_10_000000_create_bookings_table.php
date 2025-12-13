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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('unit_id');
            $table->unsignedBigInteger('property_id')->nullable();
            $table->string('uid')->nullable();
            $table->string('source_name')->nullable();
            $table->string('status')->default('undefined');
            $table->string('guest_name');
            $table->date('check_in');
            $table->date('check_out');
            $table->integer('adults')->nullable();
            $table->integer('children')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('commission', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_manual')->default(false);
            $table->unsignedBigInteger('group_id')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('unit_id')->references('id')->on('units');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
