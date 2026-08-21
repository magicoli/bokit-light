<?php

use App\Mcp\Servers\BookingServer;
use Laravel\Mcp\Facades\Mcp;

// {property} (a slug, resolved in BookingServer::boot()) scopes this to one tenant's own
// bookings — Property is the App panel's own tenant (dev/project-app-panel-tenancy.md), and this
// server reads the exact same boundary. auth:sanctum tries a bearer personal access token first
// (a user issues one from their own Filament profile — shouldShowSanctumTokens()). See
// dev/project-bokit-mcp-server.md.
Mcp::web('/mcp/{property}/bookings', BookingServer::class)
    ->middleware('auth:sanctum');

// For an external client that spawns its own subprocess (Claude Desktop's "command" style
// config, the MCP Inspector) rather than connecting over HTTP. No {property} context to resolve
// there (no HTTP request) — BookingServer::boot() falls back to null, same as PAM's own local path.
Mcp::local('bokit-bookings', BookingServer::class);
