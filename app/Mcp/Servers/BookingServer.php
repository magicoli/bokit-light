<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\GetBookingTool;
use App\Mcp\Tools\ListBookingsTool;
use App\Models\Property;
use App\Models\User;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;

/**
 * Booking tools for bokit-light (dev/project-bokit-mcp-server.md) — usable standalone (a bare
 * MCP client pointed at bokit) or alongside personal-assistant-mcp (an assistant there gets this
 * server added as one of its own connected tools).
 *
 * Property is bokit's tenant (dev/project-app-panel-tenancy.md) — "chaque propriétaire ne peut
 * voir que ses propres propriétés" — the same boundary the App panel itself now uses
 * (Panel::tenant(Property::class)). assistant-mcp-engine's own Assistant model is one optional
 * feature a property can have, unrelated to which tenant this server scopes to.
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
        $property = $this->resolveProperty();
        $user = $property ? $this->authorizedUser($property) : null;

        $this->tools = [
            new ListBookingsTool($property, $user),
            new GetBookingTool($property, $user),
        ];
    }

    /**
     * The {property} route parameter routes/mcp.php's Mcp::web() registration declares — absent
     * for the stdio/local path (Mcp::local(), no HTTP request to have a route on).
     */
    private function resolveProperty(): ?Property
    {
        $slug = request()?->route('property');

        return $slug ? Property::where('slug', $slug)->firstOrFail() : null;
    }

    /**
     * auth:sanctum (routes/mcp.php) already required a valid bearer token to reach here at all —
     * this is the authorization half: the token's owner must actually have access to THIS
     * property (User::canAccessTenant(), the same check the App panel's own tenancy uses).
     */
    private function authorizedUser(Property $property): User
    {
        $user = request()->user();

        abort_if(! $user instanceof User || ! $user->canAccessTenant($property), 403);

        return $user;
    }
}
