<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create("ical_sources", function (Blueprint $table) {
            $table->id();
            $table->foreignId("unit_id")->constrained()->onDelete("cascade");
            $table->string("name"); // Ex: "Booking.com", "Airbnb"
            $table->text("url"); // URL du flux iCal
            $table->boolean("sync_enabled")->default(true);
            $table->timestamp("last_synced_at")->nullable();
            $table->text("last_error")->nullable();
            $table->timestamps();

            $table->index(["unit_id", "sync_enabled"]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("ical_sources");
    }
};
