<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create("properties", function (Blueprint $table) {
            $table->id();
            $table->string("slug")->unique();
            $table->string("name");
            // $table->string('color', 7)->default('#3B82F6'); // Hex color
            // $table->integer('capacity')->nullable();
            $table->json("settings")->nullable(); // Pour métadonnées futures
            $table->boolean("is_active")->default(true);
            $table->timestamps();

            $table->index("is_active");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("properties");
    }
};
