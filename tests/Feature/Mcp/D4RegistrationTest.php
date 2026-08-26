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
 * Every D4 tool except the two gated ones is read-only and anonymous:
 * save_build needs a signed-in user and import_build is off unless the
 * deployment opts into reading Maxroll.
 */
test('every ungated D4 tool is visible to an anonymous client', function () {
    $registered = collect((new ReflectionClass(D4Server::class))->getDefaultProperties()['tools'])
        ->map(fn (string $tool) => (new ReflectionClass($tool))->getDefaultProperties()['name'])
        ->reject(fn (string $name) => in_array($name, ['save_build', 'import_build'], true))
        ->values()
        ->all();

    expect($registered)->not->toBeEmpty()
        ->and(d4ToolNames())->toEqualCanonicalizing($registered);
});

test('the D4 endpoint answers on its own name and does not serve the poe2 toolset', function () {
    expect(route('mcp.d4'))->toEndWith('/mcp/d4');

    expect(d4ToolNames())
        ->toContain('get_meta_context', 'search_skills', 'get_paragon_board', 'search_aspects', 'validate_build', 'get_build')
        ->not->toContain('search_gems', 'plan_tree_path');
});

test('save_build is hidden from anonymous clients and import_build is hidden unless enabled', function () {
    expect(d4ToolNames())->not->toContain('save_build', 'import_build');

    config()->set('games.diablo-4.maxroll_import_enabled', true);

    expect(d4ToolNames())->toContain('import_build')->not->toContain('save_build');
});
