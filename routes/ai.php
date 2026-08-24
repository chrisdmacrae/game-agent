<?php

use App\Mcp\Servers\Poe2Server;
use Laravel\Mcp\Facades\Mcp;

// Public, read-only MCP server. Rate-limited per IP; auth comes later with
// user-specific features (saved builds).
Mcp::web('/mcp/poe2', Poe2Server::class)
    ->middleware(['throttle:mcp'])
    ->name('mcp.poe2');

// Local stdio server for development (`php artisan mcp:start poe2`).
Mcp::local('poe2', Poe2Server::class);
