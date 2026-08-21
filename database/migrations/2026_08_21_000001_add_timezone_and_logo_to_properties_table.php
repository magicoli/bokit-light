<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Timezone and logo are first-class property fields, same tier as name/slug — not nested in
     * the options JSON column. Both nullable: null means "inherit the app-wide default"
     * (dev/project-timezone-and-tenant-settings.md).
     */
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table): void {
            if (! Schema::hasColumn('properties', 'timezone')) {
                $table->string('timezone')->nullable()->after('slug');
            }
            if (! Schema::hasColumn('properties', 'logo')) {
                $table->string('logo')->nullable()->after('timezone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table): void {
            if (Schema::hasColumn('properties', 'logo')) {
                $table->dropColumn('logo');
            }
            if (Schema::hasColumn('properties', 'timezone')) {
                $table->dropColumn('timezone');
            }
        });
    }
};
