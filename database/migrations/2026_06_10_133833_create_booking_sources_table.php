<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per (source, external booking id) pair attached to a booking.
 *
 * A booking has exactly one origin source (is_origin = true) which is
 * authoritative for dates, prices and critical data. Other sources may
 * only complete missing information. Matching by (source_key, external_id)
 * is authoritative: once a pair is stored, it wins over date/name/email
 * heuristics even if those fields change at the source.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('source_type');
            $table->string('source_key');
            $table->string('external_id');
            $table->boolean('is_origin')->default(false);
            $table->boolean('is_placeholder')->default(false);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['source_key', 'external_id']);
            $table->index(['source_type', 'external_id']);
            $table->index('booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_sources');
    }
};
