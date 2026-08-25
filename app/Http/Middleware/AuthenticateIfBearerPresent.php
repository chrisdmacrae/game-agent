<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticate the request only when it carries a bearer token.
 *
 * MCP clients discover OAuth from the well-known metadata routes, which match
 * every path — so a client pointed at the public MCP endpoint can complete the
 * OAuth flow and then send its token to a route with no auth middleware, where
 * it would be silently ignored and user-scoped tools (save_build) would never
 * register. This middleware closes that gap: anonymous requests pass through
 * untouched, while a presented token is authenticated for real — and rejected
 * with a 401 if invalid, so the client knows to refresh it.
 */
class AuthenticateIfBearerPresent
{
    public function __construct(public Authenticate $authenticate) {}

    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        if ($request->bearerToken() === null) {
            return $next($request);
        }

        return $this->authenticate->handle($request, $next, ...$guards);
    }
}
