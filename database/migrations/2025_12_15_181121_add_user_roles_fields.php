<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Conserver is_admin pour compatibilité existante
            if (!Schema::hasColumn('users', 'is_admin')) {
                $table->boolean('is_admin')->default(false);
            }
            
            // Ajouter le champ roles pour la gestion fine des permissions
            if (!Schema::hasColumn('users', 'roles')) {
                $table->json('roles')->nullable()->after('is_admin');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('users', 'is_admin')) {
                $columns[] = 'is_admin';
            }
            if (Schema::hasColumn('users', 'roles')) {
                $columns[] = 'roles';
            }
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};