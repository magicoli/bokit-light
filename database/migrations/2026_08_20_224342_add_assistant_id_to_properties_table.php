<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Backfills every pre-existing property into one Assistant (bokit's tenant unit) representing
 * the single business this install already runs — there was no tenancy before this, so there is
 * nothing to split. New tenants are created going forward as new Assistant records; this
 * migration exists once, for this one-time transition.
 *
 * Raw DB::table() throughout, not the Assistant Eloquent model: Assistant declares its fillable
 * fields via the #[Fillable(...)] PHP attribute, which this app's installed Laravel version does
 * not read (the attribute class itself isn't even shipped in vendor/laravel/framework here) —
 * Assistant::create([...]) always throws MassAssignmentException as a result. Confirmed live,
 * not assumed. Bokit's own future Assistant-creation code needs to route around the same way
 * (set attributes then save(), or Model::unguarded()) until/unless the engine changes this.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table): void {
            $table->foreignId('assistant_id')->nullable()->after('id')
                ->constrained('assistants')->nullOnDelete();
        });

        if (DB::table('properties')->whereNull('assistant_id')->exists()) {
            $ownerId = DB::table('users')->where('is_admin', true)->orderBy('id')->value('id');

            $assistantId = DB::table('assistants')->insertGetId([
                'name' => $name = 'Gîtes Mosaïques',
                'slug' => Str::slug($name),
                'owner_id' => $ownerId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('properties')->whereNull('assistant_id')->update(['assistant_id' => $assistantId]);
        }
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('assistant_id');
        });
    }
};
