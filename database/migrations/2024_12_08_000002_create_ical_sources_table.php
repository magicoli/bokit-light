<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create("ical_sources", function (Blueprint $table) {
            $table->id();
            $table->foreignId("unit_id")->constrained()->onDelete("cascade");
            $table->string("name");
            $table->string("type")->default("ical");
            $table->string("url");
            $table->boolean("sync_enabled")->default(true);
            $table->timestamps();
            $table->timestamp("last_synced_at")->nullable();
            $table->text("last_error")->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists("ical_sources");
    }
};
