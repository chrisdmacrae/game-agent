<?php

use App\Mcp\Servers\D4Server;
use App\Mcp\Tools\D4\GetBuildTool;
use App\Mcp\Tools\D4\SaveBuildTool;
use App\Models\Build;
use App\Models\User;
use Tests\Fixtures\D4Seeder;

beforeEach(function () {
    D4Seeder::seed();
});

test('save_build stores a D4 draft and returns a shareable url', function () {
    D4Server::actingAs(User::factory()->create())->tool(SaveBuildTool::class, [
        'name' => 'Whirlwind Dust Devils',
        'summary' => 'Spin to win, with tornadoes.',
        'guide_markdown' => "## Concept\n\nSpin.",
        'build' => [
            'class' => 'Barbarian',
            'level' => 70,
            'tier' => 'S',
            'dps' => 8400000,
            'ehp' => 412000,
            'stage' => 'endgame',
            'hardcore_viable' => true,
            'equipped_skills' => [['skill' => 'Whirlwind', 'role' => 'Main damage', 'rank' => 12]],
            'paragon' => [['board' => 'Start', 'rotation' => 90]],
            'gear' => ['weapons' => [['name' => "Ancients' Oath", 'rarity' => 'unique']]],
        ],
    ])
        ->assertOk()
        ->assertSee('"url"')
        ->assertSee('diablo-4');

    $build = Build::sole();

    expect($build->public_id)->toHaveLength(12)
        ->and($build->name)->toBe('Whirlwind Dust Devils')
        ->and($build->visibility)->toBe(Build::VISIBILITY_DRAFT)
        ->and($build->validation['valid'])->toBeTrue()
        ->and($build->game_version_id)->not->toBeNull()
        ->and($build->game->slug)->toBe('diablo-4');
});

test('save_build syncs the promoted columns off the D4 payload', function () {
    D4Server::actingAs(User::factory()->create())->tool(SaveBuildTool::class, [
        'name' => 'Promoted',
        'build' => [
            'class' => 'Barbarian',
            'level' => 68,
            'tier' => 'A',
            'dps' => 1234,
            'ehp' => 5678,
            'content_tier' => 'leveling',
            'hardcore_viable' => false,
            'equipped_skills' => [['skill' => 'Whirlwind']],
        ],
    ])->assertOk();

    $build = Build::sole();

    expect($build->class)->toBe('Barbarian')
        ->and($build->level)->toBe(68)
        ->and($build->tier)->toBe('A')
        ->and($build->dps)->toBe(1234)
        ->and($build->ehp)->toBe(5678)
        ->and($build->hardcore_viable)->toBeFalse()
        // content_tier "leveling" has no BuildStage mapping of its own; the
        // stage tag is only set when the payload carries `stage`.
        ->and($build->ascendancy)->toBeNull();
});

test('save_build normalises the payload before storing it', function () {
    D4Server::actingAs(User::factory()->create())->tool(SaveBuildTool::class, [
        'name' => 'Messy',
        'build' => [
            'class' => 'Barbarian',
            'equipped_skills' => [['skill' => '  Whirlwind  ', 'modifiers' => ['Tornado', '']]],
            'gear' => ['helm' => ['name' => ' Soul Onus ', 'rarity' => 'legendary', 'affixes' => []]],
        ],
    ])->assertOk();

    $payload = Build::sole()->build;

    expect($payload['equipped_skills'][0]['skill'])->toBe('Whirlwind')
        ->and($payload['equipped_skills'][0]['modifiers'])->toBe(['Tornado'])
        ->and($payload['gear']['helm'])->toBe(['name' => 'Soul Onus', 'rarity' => 'legendary']);
});

test('save_build stores validation violations for a flawed build', function () {
    D4Server::actingAs(User::factory()->create())->tool(SaveBuildTool::class, [
        'name' => 'Wrong Class Skill',
        'build' => [
            'class' => 'Barbarian',
            'equipped_skills' => [['skill' => 'Chain Lightning']],
        ],
    ])->assertOk();

    expect(Build::sole()->validation['valid'])->toBeFalse();
});

test('save_build with an id updates the same page in place', function () {
    $user = User::factory()->create();

    D4Server::actingAs($user)->tool(SaveBuildTool::class, [
        'name' => 'First Draft',
        'build' => ['equipped_skills' => [['skill' => 'Whirlwind']]],
    ])->assertOk();

    $build = Build::sole();

    D4Server::actingAs($user)->tool(SaveBuildTool::class, [
        'id' => $build->public_id,
        'name' => 'Second Draft',
        'summary' => 'Now with a board.',
        'build' => [
            'class' => 'Barbarian',
            'equipped_skills' => [['skill' => 'Whirlwind']],
            'paragon' => [['board' => 'Start']],
        ],
    ])->assertOk()->assertSee($build->public_id);

    expect(Build::count())->toBe(1)
        ->and($build->refresh()->name)->toBe('Second Draft')
        ->and($build->summary)->toBe('Now with a board.')
        ->and($build->build['paragon'][0]['board'])->toBe('Start');
});

test('save_build cannot update another users build', function () {
    $owner = User::factory()->create();

    D4Server::actingAs($owner)->tool(SaveBuildTool::class, [
        'name' => 'Owned Build',
        'build' => ['equipped_skills' => [['skill' => 'Whirlwind']]],
    ])->assertOk();

    $build = Build::sole();

    D4Server::actingAs(User::factory()->create())->tool(SaveBuildTool::class, [
        'id' => $build->public_id,
        'name' => 'Hijacked',
        'build' => ['equipped_skills' => [['skill' => 'Whirlwind']]],
    ])->assertHasErrors();

    expect(Build::count())->toBe(1)
        ->and($build->refresh()->name)->toBe('Owned Build');
});

test('save_build is hidden from anonymous clients and refuses to run for one', function () {
    expect(app(SaveBuildTool::class)->shouldRegister())->toBeFalse();

    D4Server::tool(SaveBuildTool::class, [
        'name' => 'Anonymous Build',
        'build' => ['equipped_skills' => [['skill' => 'Whirlwind']]],
    ])->assertHasErrors();

    expect(Build::count())->toBe(0);
});

test('get_build returns a saved D4 build by public id', function () {
    $user = User::factory()->create();

    D4Server::actingAs($user)->tool(SaveBuildTool::class, [
        'name' => 'Whirlwind Dust Devils',
        'build' => [
            'class' => 'Barbarian',
            'equipped_skills' => [['skill' => 'Whirlwind']],
        ],
    ])->assertOk();

    $build = Build::sole();

    D4Server::actingAs($user)->tool(GetBuildTool::class, ['id' => $build->public_id])
        ->assertOk()
        ->assertSee('Whirlwind Dust Devils')
        ->assertSee('equipped_skills')
        // The D4 tool is deliberately not the PoE2 one: no Path of Building code.
        ->assertDontSee('pob_code');

    D4Server::actingAs($user)->tool(GetBuildTool::class, ['id' => 'nonexistent42'])->assertHasErrors();
});

test('get_build hides another users draft', function () {
    D4Server::actingAs(User::factory()->create())->tool(SaveBuildTool::class, [
        'name' => 'Private Draft',
        'build' => ['equipped_skills' => [['skill' => 'Whirlwind']]],
    ])->assertOk();

    D4Server::actingAs(User::factory()->create())
        ->tool(GetBuildTool::class, ['id' => Build::sole()->public_id])
        ->assertHasErrors();
});
