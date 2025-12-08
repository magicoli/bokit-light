<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->onDelete('cascade');
            $table->string('uid')->nullable(); // UID iCal unique (détection doublons)
            $table->string('source_name'); // Source prioritaire si dédupliqué
            $table->string('guest_name');
            $table->date('check_in');
            $table->date('check_out');
            
            // Phase 2 fields (nullable pour l'instant)
            $table->integer('adults')->nullable();
            $table->integer('children')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('commission', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_manual')->default(false);
            $table->unsignedBigInteger('group_id')->nullable(); // Pour grouper plusieurs réservations
            
            $table->json('raw_data')->nullable(); // Données brutes iCal
            $table->timestamps();

            // Indexes pour performance
            $table->index(['property_id', 'check_in', 'check_out']);
            $table->index('uid');
            $table->index('is_manual');
            $table->index('group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
