<?php

namespace App\Mcp\Servers;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

/**
 * Booking tools for bokit-light (dev/project-bokit-mcp-server.md) — usable standalone (a bare
 * MCP client pointed at bokit) or alongside personal-assistant-mcp (an assistant there gets this
 * server added as one of its own connected tools). Starts with an empty tool list so the
 * endpoint itself is verifiable before any booking logic exists; tools land in later commits.
 *
 * Deliberately not built on assistant-mcp-engine's ToolRegistry/PersonalAssistantServer: those
 * exist for PAM's multi-tenant Assistant/mail/skill/memory shape, which bokit doesn't have — see
 * the plan doc for the reasoning. Tool constructors here type-hint App\Models\User directly.
 */
#[Name('Bokit Booking MCP')]
class BookingServer extends Server
{
    protected string $instructions = <<<'MARKDOWN'
        Booking and property-management tools for bokit-light. Reads bokit's own already-synced
        local records — never calls a channel-manager API directly.
        MARKDOWN;

    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        //
    ];
}
