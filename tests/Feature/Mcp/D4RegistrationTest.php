<?php

use App\Mcp\Servers\D4Server;
use Tests\Fixtures\D4Seeder;

beforeEach(function () {
    D4Seeder::seed();
});

/**
 * Collect every tool name the /mcp/d4 endpoint advertises to an anonymous
 * client, following the tools/list cursor.
 *
 * @return list<string>
 */
function d4ToolNames(): array
{
    $names = [];
    $cursor = null;

    do {
        $response = test()->call('POST', '/mcp/d4', content: json_encode([
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

/**
 * Phase 2 is entirely read-only: nothing on the D4 server is user-gated, so an
 * anonymous client must see the whole toolset.
 */
test('every registered D4 tool is visible to an anonymous client', function () {
    $registered = collect((new ReflectionClass(D4Server::class))->getDefaultProperties()['tools'])
        ->map(fn (string $tool) => (new ReflectionClass($tool))->getDefaultProperties()['name'])
        ->all();

    expect($registered)->not->toBeEmpty()
        ->and(d4ToolNames())->toEqualCanonicalizing($registered);
});

test('the D4 endpoint answers on its own name and does not serve the poe2 toolset', function () {
    expect(route('mcp.d4'))->toEndWith('/mcp/d4');

    expect(d4ToolNames())
        ->toContain('get_meta_context', 'search_skills', 'get_paragon_board', 'search_aspects')
        ->not->toContain('search_gems', 'save_build', 'validate_build');
});
