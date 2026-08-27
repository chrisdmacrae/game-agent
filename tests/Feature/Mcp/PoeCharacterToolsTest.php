<?php

use App\Mcp\Servers\Poe2Server;
use App\Mcp\Tools\Poe2\CompareCharacterToBuildTool;
use App\Mcp\Tools\Poe2\ConnectPoeAccountTool;
use App\Mcp\Tools\Poe2\GetMyCharacterTool;
use App\Mcp\Tools\Poe2\ImportCharacterAsBuildTool;
use App\Mcp\Tools\Poe2\ListMyCharactersTool;
use App\Models\Build;
use App\Models\PoeAccount;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\Poe2Seeder;

beforeEach(function () {
    Poe2Seeder::seed();

    // The API client caches per account for a minute; the array store outlives
    // a single test.
    Cache::flush();

    config([
        'services.poe.client_id' => 'test-client',
        'services.poe.client_secret' => 'test-secret',
        'services.poe.contact' => 'dev@example.test',
    ]);
});

function fakeGggCharacterApi(): void
{
    Http::fake([
        'api.pathofexile.com/character/poe2/*' => Http::response(
            json_decode(file_get_contents(base_path('tests/Fixtures/ggg/character.json')), true),
        ),
        'api.pathofexile.com/character/poe2' => Http::response(
            json_decode(file_get_contents(base_path('tests/Fixtures/ggg/characters.json')), true),
        ),
    ]);
}

function userWithLinkedPoeAccount(): User
{
    $user = User::factory()->create();

    PoeAccount::create([
        'user_id' => $user->id,
        'ggg_uuid' => 'uuid-1',
        'ggg_name' => 'TestExile',
        'access_token' => 'access-token-value',
        'refresh_token' => 'refresh-token-value',
        'token_expires_at' => now()->addDays(28),
        'connected_at' => now(),
    ]);

    return $user;
}

/** @return list<string> */
function poe2AnonymousToolNames(): array
{
    $names = [];
    $cursor = null;

    do {
        $result = test()->call('POST', '/mcp/poe2', content: json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
            'params' => $cursor !== null ? ['cursor' => $cursor] : [],
        ]), parameters: [])->assertOk()->json('result');

        $names = array_merge($names, array_column($result['tools'], 'name'));
        $cursor = $result['nextCursor'] ?? null;
    } while ($cursor !== null);

    return $names;
}

test('the character tools are hidden from anonymous clients', function () {
    expect(poe2AnonymousToolNames())->not->toContain(
        'connect_poe_account',
        'list_my_characters',
        'get_my_character',
        'compare_character_to_build',
        'import_character_as_build',
    );
});

test('the character tools stay unregistered without GGG credentials, even when signed in', function () {
    Auth::login(User::factory()->create());

    expect(app(ListMyCharactersTool::class)->shouldRegister())->toBeTrue();

    config(['services.poe.client_id' => null]);

    expect(app(ListMyCharactersTool::class)->shouldRegister())->toBeFalse()
        ->and(app(CompareCharacterToBuildTool::class)->shouldRegister())->toBeFalse();
});

test('a signed-in user without a linked account is handed the connect link', function () {
    Poe2Server::actingAs(User::factory()->create())
        ->tool(ListMyCharactersTool::class)
        ->assertHasErrors()
        // The link lands on GGG's consent screen, not a settings page to hunt
        // through, and names the tool that explains the flow.
        ->assertSee('connections/poe/redirect')
        ->assertSee('connect_poe_account');
});

test('connect_poe_account hands back the link that starts the GGG consent flow', function () {
    Poe2Server::actingAs(User::factory()->create())
        ->tool(ConnectPoeAccountTool::class)
        ->assertOk()
        ->assertSee('"connected":false')
        ->assertSee(route('settings.poe.redirect'))
        ->assertSee('account:characters');
});

test('connect_poe_account reports an already-linked account', function () {
    Poe2Server::actingAs(userWithLinkedPoeAccount())
        ->tool(ConnectPoeAccountTool::class)
        ->assertOk()
        ->assertSee('"connected":true')
        ->assertSee('TestExile');
});

test('list_my_characters returns the account\'s PoE2 characters', function () {
    fakeGggCharacterApi();

    Poe2Server::actingAs(userWithLinkedPoeAccount())
        ->tool(ListMyCharactersTool::class)
        ->assertOk()
        ->assertSee('TestWitch')
        ->assertSee('OldRanger')
        ->assertSee('Infernalist');
});

test('get_my_character returns the character in build shape', function () {
    fakeGggCharacterApi();

    Poe2Server::actingAs(userWithLinkedPoeAccount())
        ->tool(GetMyCharacterTool::class, ['name' => 'TestWitch'])
        ->assertOk()
        ->assertSee('Corpse Shroud')
        ->assertSee('Chaos Inoculation')
        ->assertSee('Spark');
});

test('an unknown character name is an actionable error, not a crash', function () {
    Http::fake(['api.pathofexile.com/*' => Http::response(status: 404)]);

    Poe2Server::actingAs(userWithLinkedPoeAccount())
        ->tool(GetMyCharacterTool::class, ['name' => 'Nobody'])
        ->assertHasErrors()
        ->assertSee('list_my_characters');
});

test('a rate-limited API answers with how long to wait', function () {
    Http::fake([
        'api.pathofexile.com/*' => Http::response(status: 429, headers: ['Retry-After' => '45']),
    ]);

    Poe2Server::actingAs(userWithLinkedPoeAccount())
        ->tool(ListMyCharactersTool::class)
        ->assertHasErrors()
        ->assertSee('45 seconds');
});

test('compare_character_to_build diffs the character against a saved build', function () {
    fakeGggCharacterApi();

    $user = userWithLinkedPoeAccount();

    $build = Build::factory()->create([
        'user_id' => $user->id,
        'visibility' => Build::VISIBILITY_PUBLIC,
        'build' => [
            'class' => 'Witch',
            'level' => 85,
            'skills' => [['gem' => 'Spark', 'supports' => ['Pierce', 'Considered Casting']]],
            'passives' => ['node_ids' => [1000, 1001, 1002]],
            'gear' => [['slot' => 'gloves', 'rarity' => 'rare', 'mods' => ['+# to maximum Life']]],
        ],
    ]);

    Poe2Server::actingAs($user)
        ->tool(CompareCharacterToBuildTool::class, ['name' => 'TestWitch', 'build_id' => $build->public_id])
        ->assertOk()
        // The build wants Heightened Curses and Considered Casting, and has a
        // gloves slot the character has not filled.
        ->assertSee('Heightened Curses')
        ->assertSee('Considered Casting')
        ->assertSee('empty_slot')
        ->assertSee('below_build_level');
});

test('compare_character_to_build works against an inline build definition', function () {
    fakeGggCharacterApi();

    Poe2Server::actingAs(userWithLinkedPoeAccount())
        ->tool(CompareCharacterToBuildTool::class, [
            'name' => 'TestWitch',
            'build' => [
                'skills' => [['gem' => 'Frost Bomb']],
                'passives' => ['node_ids' => [1000, 1002]],
            ],
        ])
        ->assertOk()
        ->assertSee('Frost Bomb')
        ->assertSee('Heightened Curses');
});

test('compare_character_to_build needs something to compare against', function () {
    fakeGggCharacterApi();

    Poe2Server::actingAs(userWithLinkedPoeAccount())
        ->tool(CompareCharacterToBuildTool::class, ['name' => 'TestWitch'])
        ->assertHasErrors()
        ->assertSee('build_id');
});

test('import_character_as_build saves a draft owned by the caller', function () {
    fakeGggCharacterApi();

    $user = userWithLinkedPoeAccount();

    Poe2Server::actingAs($user)
        ->tool(ImportCharacterAsBuildTool::class, ['name' => 'TestWitch'])
        ->assertOk()
        ->assertSee('draft');

    $build = Build::sole();

    expect($build->user_id)->toBe($user->id)
        // Never public: an import carries no guide, stats or reasoning.
        ->and($build->visibility)->toBe(Build::VISIBILITY_DRAFT)
        ->and($build->name)->toBe('TestWitch')
        ->and($build->ascendancy)->toBe('Infernalist')
        ->and($build->level)->toBe(78)
        ->and($build->build['passives']['node_ids'])->toBe([1000, 1001, 2001])
        ->and(collect($build->build['skills'])->pluck('gem')->all())->toContain('Spark');
});

test('an expired access token is refreshed before the API is called', function () {
    Http::fake([
        'www.pathofexile.com/oauth/token' => Http::response([
            'access_token' => 'refreshed-token',
            'refresh_token' => 'new-refresh-token',
            'expires_in' => 2419200,
        ]),
        'api.pathofexile.com/character/poe2' => Http::response(
            json_decode(file_get_contents(base_path('tests/Fixtures/ggg/characters.json')), true),
        ),
    ]);

    $user = userWithLinkedPoeAccount();
    $user->poeAccount->update(['token_expires_at' => now()->subHour()]);

    Poe2Server::actingAs($user)
        ->tool(ListMyCharactersTool::class)
        ->assertOk()
        ->assertSee('TestWitch');

    expect($user->poeAccount()->sole()->access_token)->toBe('refreshed-token');
});

test('a dead refresh token unlinks the account and says to reconnect', function () {
    Http::fake([
        'www.pathofexile.com/oauth/token' => Http::response(['error' => 'invalid_grant'], 400),
    ]);

    $user = userWithLinkedPoeAccount();
    $user->poeAccount->update(['token_expires_at' => now()->subHour()]);

    Poe2Server::actingAs($user)
        ->tool(ListMyCharactersTool::class)
        ->assertHasErrors()
        ->assertSee('Reconnect');

    expect(PoeAccount::count())->toBe(0);
});
