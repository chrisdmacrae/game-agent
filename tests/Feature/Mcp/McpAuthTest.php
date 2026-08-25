<?php

use App\Models\LoginLink;
use App\Models\User;
use Laravel\Passport\Client;
use League\OAuth2\Server\ResourceServer;
use Psr\Http\Message\ServerRequestInterface;
use Tests\Fixtures\Poe2Seeder;

beforeEach(function () {
    Poe2Seeder::seed();
});

/**
 * Collect every tool name from the (paginated) tools/list endpoint.
 *
 * @return list<string>
 */
function mcpToolNames(string $endpoint): array
{
    $names = [];
    $cursor = null;

    do {
        $response = test()->call('POST', $endpoint, content: json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
            'params' => $cursor !== null ? ['cursor' => $cursor] : [],
        ]), parameters: [])->assertOk();

        $result = $response->json('result');

        $names = array_merge($names, array_column($result['tools'], 'name'));
        $cursor = $result['nextCursor'] ?? null;
    } while ($cursor !== null);

    return $names;
}

test('the public endpoint does not expose save_build', function () {
    $names = mcpToolNames('/mcp/poe2');

    expect($names)->not->toContain('save_build')
        ->and($names)->toContain('get_meta_context', 'validate_build', 'get_build');
});

/**
 * MCP clients complete the OAuth flow via the well-known discovery routes even
 * when they were configured with the main endpoint URL, then send their token
 * there. The token must actually authenticate them, or they end up silently
 * stuck with the read-only toolset.
 */
test('the main endpoint exposes save_build when the request carries a valid bearer token', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();

    // Stand in for a verified access token: the real ResourceServer would
    // decode the JWT and hand these attributes to Passport's token guard.
    $server = Mockery::mock(ResourceServer::class);
    $server->shouldReceive('validateAuthenticatedRequest')->andReturnUsing(
        fn (ServerRequestInterface $request) => $request
            ->withAttribute('oauth_client_id', $client->getKey())
            ->withAttribute('oauth_user_id', (string) $user->getKey())
            ->withAttribute('oauth_scopes', ['mcp:use'])
    );
    app()->instance(ResourceServer::class, $server);

    test()->withServerVariables(['HTTP_AUTHORIZATION' => 'Bearer valid-token']);

    expect(mcpToolNames('/mcp/poe2'))->toContain('save_build');
});

test('the main endpoint rejects an invalid bearer token instead of downgrading to read-only', function () {
    $this->withHeader('Authorization', 'Bearer expired-or-garbage')
        ->postJson('/mcp/poe2', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
            'params' => [],
        ])->assertUnauthorized();
});

/**
 * The browser half of the MCP handshake: an OAuth client sends the user to
 * /oauth/authorize, which bounces a guest to the magic link form and then has
 * to get them back to a consent screen that is rendered in Blade, not Inertia.
 */
test('a magic link sign-in lands an oauth client on the consent screen', function () {
    $client = Client::factory()->create([
        'name' => 'Claude',
        'redirect_uris' => ['https://claude.test/callback'],
    ]);

    $authorizeUrl = '/oauth/authorize?'.http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://claude.test/callback',
        'response_type' => 'code',
    ]);

    // A guest is sent to the login form with the consent URL held in session.
    $this->get($authorizeUrl)->assertRedirect(route('login'));

    expect(session('url.intended'))->toBe(url($authorizeUrl));

    ['plainToken' => $token] = LoginLink::generateFor('exile@example.com');

    // auth/Verify posts over XHR. A plain redirect would make that XHR fetch
    // the Blade consent screen and choke on the HTML; a location makes the
    // browser navigate to it for real.
    $response = $this->post(route('login.verify.store', $token), [], ['X-Inertia' => 'true'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', url($authorizeUrl));

    $this->assertAuthenticated();

    $this->get($response->headers->get('X-Inertia-Location'))
        ->assertOk()
        ->assertViewIs('mcp.authorize')
        ->assertViewHas('client');
});
