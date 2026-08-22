<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // if (Schema::hasIndex('properties', ['is_active'], 'unique')) {
        //     Schema::table('properties', function (Blueprint $table) {
        //         $table->dropIndex(['is_active']);
        //     });
        // }
        // Schema::dropIfExists('properties');
        // Schema::create('properties', function (Blueprint $table) {
        //     $table->id();
        //     $table->string('slug')->unique();
        //     $table->string('name');
        //     $table->json('settings')->nullable();
        //     $table->boolean('is_active')->default(true);
        //     $table->timestamps();

        //     $table->index('is_active');
        // });
    }

    public function down(): void
    {
        // Schema::dropIfExists('properties');
    }
};
