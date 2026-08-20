<?php

use App\Mcp\Servers\BookingServer;
use Laravel\Mcp\Facades\Mcp;

// {assistant} (a slug, resolved in BookingServer::boot()) is what scopes this to one tenant's
// own properties/bookings instead of the whole install — bokit IS multi-tenant (one owner
// account, several properties; property_user still governs per-property staff roles within a
// tenant). auth:sanctum tries a bearer personal access token first (an admin issues one from
// their own Filament profile). See dev/project-bokit-mcp-server.md.
Mcp::web('/mcp/{assistant}/bookings', BookingServer::class)
    ->middleware('auth:sanctum');

// For an external client that spawns its own subprocess (Claude Desktop's "command" style
// config, the MCP Inspector) rather than connecting over HTTP. No {assistant} context to resolve
// there (no HTTP request) — BookingServer::boot() falls back to null, same as PAM's own local path.
Mcp::local('bokit-bookings', BookingServer::class);
