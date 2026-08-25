<?php

use App\Mcp\Servers\Poe2Server;
use Laravel\Mcp\Facades\Mcp;

// OAuth 2.1 discovery + dynamic client registration routes for MCP clients.
Mcp::oauthRoutes();

// Public, read-only endpoint. Rate-limited per IP; no user, so tools that
// require an authenticated user (save_build) are not registered here.
Mcp::web('/mcp/poe2', Poe2Server::class)
    ->middleware(['throttle:mcp'])
    ->name('mcp.poe2');

// Authenticated endpoint: same server plus the user-scoped tools. MCP clients
// connect via OAuth (browser login with a magic link, then approve).
Mcp::web('/mcp/poe2/user', Poe2Server::class)
    ->middleware(['throttle:mcp', 'auth:api'])
    ->name('mcp.poe2.user');

// Local stdio server for development (`php artisan mcp:start poe2`).
Mcp::local('poe2', Poe2Server::class);
