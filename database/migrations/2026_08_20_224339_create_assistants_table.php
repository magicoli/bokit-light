<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matches Magicoli\AssistantMcpEngine\Models\Assistant's expected schema exactly (name, slug,
 * owner_id, options) — hand-written here rather than running the engine's own migrations
 * (dont-discover'd, see dev/project-bokit-mcp-server.md) because those also carry a
 * create_users_table/personal_access_tokens pair that collides with bokit's own. The Eloquent
 * model doesn't care which migration created its table, only that the columns match.
 *
 * This is bokit's tenant: one owner account, several properties (property_user still governs
 * per-property staff roles *within* a tenant — unchanged).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistants', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('options')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistants');
    }
};
