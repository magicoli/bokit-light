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
        // Rename settings to options in properties table
        if (Schema::hasColumn('properties', 'settings')) {
            // If options already exists, just drop settings
            if (Schema::hasColumn('properties', 'options')) {
                Schema::table('properties', function (Blueprint $table) {
                    $table->dropColumn('settings');
                });
            } else {
                Schema::table('properties', function (Blueprint $table) {
                    $table->renameColumn('settings', 'options');
                });
            }
        }

        // Rename settings to options in rates table
        if (Schema::hasColumn('rates', 'settings')) {
            // If options already exists, just drop settings
            if (Schema::hasColumn('rates', 'options')) {
                Schema::table('rates', function (Blueprint $table) {
                    $table->dropColumn('settings');
                });
            } else {
                Schema::table('rates', function (Blueprint $table) {
                    $table->renameColumn('settings', 'options');
                });
            }
        }

        // Rename settings to options in units table
        if (Schema::hasColumn('units', 'settings')) {
            // If options already exists, just drop settings
            if (Schema::hasColumn('units', 'options')) {
                Schema::table('units', function (Blueprint $table) {
                    $table->dropColumn('settings');
                });
            } else {
                Schema::table('units', function (Blueprint $table) {
                    $table->renameColumn('settings', 'options');
                });
            }
        }

        // Add options column to users table if it doesn't exist
        if (!Schema::hasColumn('users', 'options')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('options')->nullable()->after('roles');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rename options back to settings in properties table
        if (Schema::hasColumn('properties', 'options')) {
            Schema::table('properties', function (Blueprint $table) {
                $table->renameColumn('options', 'settings');
            });
        }

        // Rename options back to settings in rates table
        if (Schema::hasColumn('rates', 'options')) {
            Schema::table('rates', function (Blueprint $table) {
                $table->renameColumn('options', 'settings');
            });
        }

        // Rename options back to settings in units table
        if (Schema::hasColumn('units', 'options')) {
            Schema::table('units', function (Blueprint $table) {
                $table->renameColumn('options', 'settings');
            });
        }

        // Remove options column from users table if it exists
        if (Schema::hasColumn('users', 'options')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('options');
            });
        }
    }
};
