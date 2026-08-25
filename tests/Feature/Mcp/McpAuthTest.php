<?php

use App\Models\LoginLink;
use App\Models\User;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
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

test('the authenticated endpoint rejects requests without a token', function () {
    $this->postJson('/mcp/poe2/user', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
        'params' => [],
    ])->assertUnauthorized();
});

test('the authenticated endpoint exposes save_build to a signed-in user', function () {
    Passport::actingAs(User::factory()->create());

    expect(mcpToolNames('/mcp/poe2/user'))->toContain('save_build');
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
