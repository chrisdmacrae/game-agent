<?php

use App\Models\User;
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
