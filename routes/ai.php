<?php

use App\Http\Middleware\AuthenticateIfBearerPresent;
use App\Mcp\Servers\D4Server;
use App\Mcp\Servers\Poe2Server;
use Laravel\Mcp\Facades\Mcp;

// OAuth 2.1 discovery + dynamic client registration routes for MCP clients.
Mcp::oauthRoutes();

// One MCP endpoint per game. Rate-limited per IP; anonymous requests get the
// read-only toolset, while a bearer token (obtained via OAuth: browser login
// with a magic link, then approve) is authenticated and unlocks the
// user-scoped tools (save_build). An invalid token gets a 401, not silent
// read-only mode.
Mcp::web('/mcp/poe2', Poe2Server::class)
    ->middleware(['throttle:mcp', AuthenticateIfBearerPresent::class.':api'])
    ->name('mcp.poe2');

Mcp::web('/mcp/d4', D4Server::class)
    ->middleware(['throttle:mcp', AuthenticateIfBearerPresent::class.':api'])
    ->name('mcp.d4');

// Local stdio servers for development (`php artisan mcp:start poe2`).
Mcp::local('poe2', Poe2Server::class);
Mcp::local('d4', D4Server::class);
