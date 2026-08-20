<?php

use App\Mcp\Servers\BookingServer;
use Laravel\Mcp\Facades\Mcp;

// Single-tenant, no {assistant} slug and no Mcp::oauthRoutes() (no Passport dynamic client
// registration) — bokit isn't multi-tenant SaaS like personal-assistant-mcp, an admin issues a
// token with `php artisan bokit:issue-api-token` instead. See dev/project-bokit-mcp-server.md.
Mcp::web('/mcp/bookings', BookingServer::class)
    ->middleware('auth:sanctum');

// For an external client that spawns its own subprocess (Claude Desktop's "command" style
// config, the MCP Inspector) rather than connecting over HTTP.
Mcp::local('bokit-bookings', BookingServer::class);
