<?php

use App\Http\Middleware\AuthenticateIfBearerPresent;
use App\Mcp\Servers\Poe2Server;
use Laravel\Mcp\Facades\Mcp;

// OAuth 2.1 discovery + dynamic client registration routes for MCP clients.
Mcp::oauthRoutes();

// The one MCP endpoint. Rate-limited per IP; anonymous requests get the
// read-only toolset, while a bearer token (obtained via OAuth: browser login
// with a magic link, then approve) is authenticated and unlocks the
// user-scoped tools (save_build). An invalid token gets a 401, not silent
// read-only mode.
Mcp::web('/mcp/poe2', Poe2Server::class)
    ->middleware(['throttle:mcp', AuthenticateIfBearerPresent::class.':api'])
    ->name('mcp.poe2');

// Local stdio server for development (`php artisan mcp:start poe2`).
Mcp::local('poe2', Poe2Server::class);
