<?php

use App\Domain\D4\Import\MaxrollPlanner;
use App\Mcp\Servers\D4Server;
use App\Mcp\Tools\D4\ImportBuildTool;
use App\Models\Build;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request;
use Tests\Fixtures\D4Seeder;

/**
 * The recorded response of https://planners.maxroll.gg/profiles/d4/rf5dmg0x,
 * trimmed to two variants and the items they reference.
 *
 * @return array<string, mixed>
 */
function maxrollFixture(): array
{
    return json_decode(file_get_contents(base_path('tests/Fixtures/maxroll/d4-profile.json')), true);
}

/**
 * A planner envelope in the same shape as the recorded one, built out of the
 * entities the D4 test fixture actually imports so the resolution paths can be
 * exercised end to end.
 *
 * @return array<string, mixed>
 */
function barbarianPlannerEnvelope(): array
{
    return [
        'id' => 'barb0001',
        'name' => 'Spin To Win',
        'class' => 'Barbarian',
        'category' => 'endgame',
        'data' => json_encode([
            'profiles' => [[
                'name' => 'Endgame',
                'class' => 2,
                'level' => 70,
                'items' => ['4' => 1, '7' => 2],
                'skillBar' => ['Barbarian_Whirlwind', 'Barbarian_NotARealPower'],
                'skillTree' => ['steps' => [['name' => '1', 'data' => ['47' => 1]]]],
                'paragon' => ['steps' => [['name' => 'final', 'data' => [
                    ['id' => 'Paragon_Barb_0', 'nodes' => ['10' => 1], 'rotation' => 1, 'glyph' => 'Rare_001_Intelligence_Main', 'glyphLevel' => 100],
                    ['id' => 'Paragon_Barb_99', 'nodes' => [], 'rotation' => 0],
                ]]]],
                'mercenary' => ['id' => 'MercenaryClass_BountyHunter', 'support' => 'MercenaryClass_ShieldBearer'],
            ]],
            'items' => [
                '1' => [
                    'id' => 'Helm_Legendary_Generic_053',
                    'name' => 'Soul Onus',
                    'aspects' => [['nid' => 1203037]],
                    'tempered' => [['nid' => 1942702, 'greater' => true]],
                    'sockets' => ['Rune_Condition_CastRepeatSkill', 'Rune_Effect_Spiritborn_Vortex', 'Gem_Emerald_05'],
                ],
                '2' => [
                    'id' => '2HAxe_Unique_Barb_001_x1',
                    'aspects' => [],
                    'sockets' => ['Gem_Emerald_05'],
                ],
            ],
        ]),
    ];
}

beforeEach(function () {
    D4Seeder::seed();
});

test('import_build is off by default: hidden from the toolset and refusing to run', function () {
    expect(config('games.diablo-4.maxroll_import_enabled'))->toBeFalse()
        ->and(app(ImportBuildTool::class)->shouldRegister())->toBeFalse();

    Http::fake();

    // The server never advertises it, so a client asking for it by name is
    // told the tool does not exist.
    D4Server::tool(ImportBuildTool::class, ['planner' => 'rf5dmg0x'])
        ->assertHasErrors(['Tool [import_build] not found.']);

    // And the handler itself refuses, so no other entry point can reach out.
    $response = app(ImportBuildTool::class)->handle(
        new Request(['planner' => 'rf5dmg0x']),
        app(MaxrollPlanner::class),
    );

    expect($response->isError())->toBeTrue()
        ->and(json_encode($response->content()->toArray()))->toContain('disabled on this deployment');

    Http::assertNothingSent();
});

test('import_build rejects anything that is not a planner id', function () {
    config()->set('games.diablo-4.maxroll_import_enabled', true);
    Http::fake();

    D4Server::tool(ImportBuildTool::class, ['planner' => 'https://maxroll.gg/d4/planner/'])
        ->assertHasErrors(['not a Maxroll planner URL or id']);

    Http::assertNothingSent();
});

test('import_build maps a real planner response onto the build payload without saving', function () {
    config()->set('games.diablo-4.maxroll_import_enabled', true);

    Http::fake(['planners.maxroll.gg/*' => Http::response(maxrollFixture())]);

    D4Server::tool(ImportBuildTool::class, ['planner' => 'https://maxroll.gg/d4/planner/rf5dmg0x'])
        ->assertOk()
        ->assertSee('https://planners.maxroll.gg/profiles/d4/rf5dmg0x')
        ->assertSee('Nothing has been saved');

    expect(Build::count())->toBe(0);

    $mapped = app(MaxrollPlanner::class)->map(maxrollFixture());

    expect($mapped['variants'])->toBe(['Starter', 'Endgame'])
        ->and($mapped['variant'])->toBe(['index' => 0, 'name' => 'Starter'])
        ->and($mapped['payload']['class'])->toBe('Spiritborn')
        ->and($mapped['payload']['level'])->toBe(70)
        ->and($mapped['payload']['mercenary'])->toBe(['hired' => 'Bounty Hunter', 'reinforcement' => 'Shield Bearer']);

    $gear = $mapped['payload']['gear'];

    expect(array_keys($gear))->toBe(['helm', 'chest', 'gloves', 'pants', 'boots', 'amulet', 'ring_1', 'ring_2', 'weapons'])
        ->and($gear['helm']['rarity'])->toBe('legendary')
        ->and($gear['helm']['name'])->toBe('Soul Onus')
        // Only runes make the runeword; gems in the same sockets are ignored.
        ->and($gear['helm']['runes'])->toBe(['Rune_Condition_CastRepeatSkill', 'Rune_Effect_Spiritborn_ConcussiveStomp'])
        ->and($gear['weapons'][0]['item_type'])->toBe('2HPolearm')
        ->and($gear['ring_1']['rarity'])->toBe('unique');

    // Nothing Spiritborn is in the test fixture, so every id it could not
    // resolve is reported rather than invented.
    expect($mapped['payload'])->not->toHaveKey('equipped_skills')
        ->and(collect($mapped['unmapped'])->pluck('kind')->unique()->sort()->values()->all())
        ->toBe(['affix', 'aspect', 'item', 'paragon_board', 'skill', 'unique']);
});

test('the mapper resolves skills, boards, glyphs, aspects, uniques and tempering against the imported data', function () {
    $mapped = app(MaxrollPlanner::class)->map(barbarianPlannerEnvelope());
    $payload = $mapped['payload'];

    expect($payload['class'])->toBe('Barbarian')
        ->and($payload['level'])->toBe(70)
        ->and($payload['content_tier'])->toBe('endgame')
        ->and($payload['equipped_skills'])->toBe([['skill' => 'Whirlwind']])
        // Maxroll drops the leading zero on some board ids; the mapper retries padded.
        ->and($payload['paragon'])->toBe([[
            'board' => 'Start',
            'rotation' => 90,
            'glyph' => 'Enchanter',
            'glyph_level' => 100,
        ]])
        ->and($payload['gear']['helm']['aspect'])->toBe('of Berserk Ripping')
        ->and($payload['gear']['helm']['tempered'])->toBe([['affix' => 'Tempered_AttackSpeed_Sorc_Tag_Pyromancy_Tier1']])
        ->and($payload['gear']['helm']['greater_affixes'])->toBe(1)
        // The "_x1" planner suffix is stripped before the key lookup.
        ->and($payload['gear']['weapons'][0])->toMatchArray([
            'name' => "Ancients' Oath",
            'rarity' => 'unique',
            'item_type' => 'Axe2H',
        ]);

    expect(collect($mapped['unmapped'])->pluck('source')->all())
        ->toBe(['Barbarian_NotARealPower', 'Paragon_Barb_99']);
});

test('import_build maps the chosen variant and caches the fetch for a day', function () {
    config()->set('games.diablo-4.maxroll_import_enabled', true);

    Http::fake(['planners.maxroll.gg/*' => Http::response(maxrollFixture())]);

    D4Server::tool(ImportBuildTool::class, ['planner' => 'rf5dmg0x', 'variant' => 1])
        ->assertOk()
        ->assertSee('"name":"Endgame"');

    D4Server::tool(ImportBuildTool::class, ['planner' => 'rf5dmg0x'])->assertOk();

    Http::assertSentCount(1);
});

test('import_build reports a fetch failure instead of throwing', function () {
    config()->set('games.diablo-4.maxroll_import_enabled', true);

    Http::fake(['planners.maxroll.gg/*' => Http::response('', 404)]);

    D4Server::tool(ImportBuildTool::class, ['planner' => 'nosuchid1'])
        ->assertHasErrors(['HTTP 404']);
});

test('the planner id parser accepts urls, bare ids and nothing else', function () {
    $planner = app(MaxrollPlanner::class);

    expect($planner->parseId('rf5dmg0x'))->toBe('rf5dmg0x')
        ->and($planner->parseId('https://maxroll.gg/d4/planner/rf5dmg0x'))->toBe('rf5dmg0x')
        ->and($planner->parseId('https://planners.maxroll.gg/profiles/d4/rf5dmg0x'))->toBe('rf5dmg0x')
        ->and($planner->parseId('https://maxroll.gg/d4/planner/rf5dmg0x#2'))->toBe('rf5dmg0x')
        ->and($planner->parseId('https://maxroll.gg/d4/planner/'))->toBeNull()
        ->and($planner->parseId('https://example.com/d4/build-guides'))->toBeNull()
        ->and($planner->parseId('not a planner'))->toBeNull()
        ->and($planner->parseId(''))->toBeNull();
});
