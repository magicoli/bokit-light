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
        $column = config('filament-edit-profile.avatar_column', 'avatar_url');

        Schema::table('properties', function (Blueprint $table) use ($column): void {
            if (! Schema::hasColumn('properties', $column)) {
                $table->string($column)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $column = config('filament-edit-profile.avatar_column', 'avatar_url');

        Schema::table('properties', function (Blueprint $table) use ($column): void {
            if (Schema::hasColumn('properties', $column)) {
                $table->dropColumn($column);
            }
        });
    }
};
