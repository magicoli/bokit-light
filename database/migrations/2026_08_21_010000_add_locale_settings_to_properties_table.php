<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Same tier as timezone/logo - dedicated columns, not nested in options
     * (dev/project-tenant-sub-sites.md). locale: this tenant's own default language, null =
     * inherit the app-wide default. locales: the subset of the app-wide list this tenant offers
     * visitors, null/empty = every app-wide locale is available.
     */
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table): void {
            if (! Schema::hasColumn('properties', 'locale')) {
                $table->string('locale')->nullable()->after('logo');
            }
            if (! Schema::hasColumn('properties', 'locales')) {
                $table->json('locales')->nullable()->after('locale');
            }
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table): void {
            if (Schema::hasColumn('properties', 'locales')) {
                $table->dropColumn('locales');
            }
            if (Schema::hasColumn('properties', 'locale')) {
                $table->dropColumn('locale');
            }
        });
    }
};
