<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrects 2026_08_20_224342_add_assistant_id_to_properties_table: the tenant is Property (one
 * property/its owner is the tenant boundary — "chaque propriétaire ne peut voir que ses propres
 * propriétés"), not Assistant. Assistant (assistant-mcp-engine's AI-chat/MCP feature) is one
 * optional feature a property can have — assistants.property_id, not properties.assistant_id.
 * See dev/project-app-panel-tenancy.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Whatever Assistant the earlier migration backfilled becomes the AI-chat feature of the
        // specific property it shares a name with ("Gîtes Mosaïques" is both the install's first
        // property and that Assistant's own name) — the one case this install actually has data
        // for; a fresh install has neither row yet, so this is a no-op there.
        $assistantId = DB::table('assistants')->value('id');
        $matchingPropertyId = $assistantId
            ? DB::table('properties')
                ->join('assistants', 'properties.name', '=', 'assistants.name')
                ->value('properties.id')
            : null;

        Schema::table('assistants', function (Blueprint $table): void {
            $table->foreignId('property_id')->nullable()->after('id')
                ->constrained('properties')->cascadeOnDelete();
        });

        if ($assistantId && $matchingPropertyId) {
            DB::table('assistants')->where('id', $assistantId)->update(['property_id' => $matchingPropertyId]);
        }

        Schema::table('properties', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('assistant_id');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table): void {
            $table->foreignId('assistant_id')->nullable()->after('id')
                ->constrained('assistants')->nullOnDelete();
        });

        Schema::table('assistants', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('property_id');
        });
    }
};
