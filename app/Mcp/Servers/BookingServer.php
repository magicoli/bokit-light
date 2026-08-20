<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\GetBookingTool;
use App\Mcp\Tools\ListBookingsTool;
use App\Models\User;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use Magicoli\AssistantMcpEngine\Models\Assistant;

/**
 * Booking tools for bokit-light (dev/project-bokit-mcp-server.md) — usable standalone (a bare
 * MCP client pointed at bokit) or alongside personal-assistant-mcp (an assistant there gets this
 * server added as one of its own connected tools).
 *
 * Bokit IS multi-tenant (one owner account = one Assistant, several properties under it), so
 * this deliberately mirrors PersonalAssistantServer's own {assistant}-resolution shape rather
 * than avoiding it — that apparatus is exactly why the app depends on assistant-mcp-engine
 * instead of just laravel/mcp directly. What's still not adopted: Passport/Reverb/mail/skill/
 * memory, none of which bokit has a use for.
 */
#[Name('Bokit Booking MCP')]
class BookingServer extends Server
{
    protected string $instructions = <<<'MARKDOWN'
        Booking and property-management tools for bokit-light. Reads bokit's own already-synced
        local records — never calls a channel-manager API directly.
        MARKDOWN;

    protected function boot(): void
    {
        $assistant = $this->resolveAssistant();
        $user = $assistant ? $this->authorizedUser($assistant) : null;

        $this->tools = [
            new ListBookingsTool($assistant, $user),
            new GetBookingTool($assistant, $user),
        ];
    }

    /**
     * The {assistant} route parameter routes/mcp.php's Mcp::web() registration declares — absent
     * for the stdio/local path (Mcp::local(), no HTTP request to have a route on).
     */
    private function resolveAssistant(): ?Assistant
    {
        $slug = request()?->route('assistant');

        return $slug ? Assistant::where('slug', $slug)->firstOrFail() : null;
    }

    /**
     * auth:sanctum (routes/mcp.php) already required a valid bearer token to reach here at all —
     * this is the authorization half: the token's owner must actually belong to THIS tenant
     * (User::canAccessTenant()), the same check the Filament panel itself will use once tenancy
     * reaches it.
     */
    private function authorizedUser(Assistant $assistant): User
    {
        $user = request()->user();

        abort_if(! $user instanceof User || ! $user->canAccessTenant($assistant), 403);

        return $user;
    }
}
