<?php

use App\Mcp\Servers\Poe2Server;
use App\Mcp\Tools\Poe2\GetBuildTool;
use App\Mcp\Tools\Poe2\SaveBuildTool;
use App\Models\Build;
use App\Models\User;
use Tests\Fixtures\Poe2Seeder;

beforeEach(function () {
    Poe2Seeder::seed();
});

/**
 * A payload that passes every pre-flight check.
 *
 * @return array<string, mixed>
 */
function publishableBuild(): array
{
    return [
        'class' => 'Witch',
        'ascendancy' => 'Infernalist',
        'level' => 90,
        'skills' => [['gem' => 'Spark', 'supports' => ['Pierce']]],
        'passives' => ['points_used' => 100, 'keystones' => ['Chaos Inoculation']],
        'gear' => [
            ['slot' => 'body', 'rarity' => 'rare', 'name' => 'Storm Weave'],
            ['slot' => 'weapon1', 'rarity' => 'rare', 'name' => 'Zap Stick'],
        ],
        'dps' => 4_100_000,
        'ehp' => 18_900,
    ];
}

test('save_build defaults to a draft', function () {
    Poe2Server::actingAs(User::factory()->create())->tool(SaveBuildTool::class, [
        'name' => 'Draft Build',
        'build' => ['skills' => [['gem' => 'Spark']]],
    ])
        ->assertOk()
        ->assertSee('draft');

    expect(Build::sole()->visibility)->toBe('draft')
        ->and(Build::sole()->isDraft())->toBeTrue();
});

test('save_build refuses to publish a build that fails the pre-flight', function () {
    Poe2Server::actingAs(User::factory()->create())->tool(SaveBuildTool::class, [
        'name' => 'Half Finished',
        'visibility' => 'public',
        'build' => ['skills' => [['gem' => 'Spark']]],
    ])
        ->assertHasErrors()
        ->assertSee('Stats present')
        ->assertSee('Gear list complete')
        ->assertSee('Passive budget');

    expect(Build::count())->toBe(0);
});

test('save_build publishes a build that passes the pre-flight', function () {
    Poe2Server::actingAs(User::factory()->create())->tool(SaveBuildTool::class, [
        'name' => 'Finished Build',
        'visibility' => 'public',
        'build' => publishableBuild(),
    ])
        ->assertOk()
        ->assertSee('public');

    expect(Build::sole()->visibility)->toBe('public')
        ->and(Build::sole()->isPublic())->toBeTrue();
});

test('save_build keeps the existing visibility when updating without one', function () {
    $user = User::factory()->create();

    Poe2Server::actingAs($user)->tool(SaveBuildTool::class, [
        'name' => 'Finished Build',
        'visibility' => 'public',
        'build' => publishableBuild(),
    ])->assertOk();

    $build = Build::sole();

    Poe2Server::actingAs($user)->tool(SaveBuildTool::class, [
        'id' => $build->public_id,
        'name' => 'Finished Build, revised',
        'build' => publishableBuild(),
    ])->assertOk();

    expect($build->refresh()->visibility)->toBe('public');
});

test('a draft is hidden from everyone but its owner', function () {
    $owner = User::factory()->create();

    // Built directly rather than through save_build so the request starts out
    // as a guest: actingAs on the MCP server authenticates the whole test.
    $build = Build::factory()->draft()->for($owner)->create([
        'name' => 'Secret Draft',
        'build' => ['class' => 'Witch', 'skills' => [['gem' => 'Spark']]],
    ]);

    // Guests first: authenticating either client sticks for the whole test.
    $this->get($build->url())->assertNotFound();
    Poe2Server::tool(GetBuildTool::class, ['id' => $build->public_id])->assertHasErrors();

    $this->actingAs($owner)->get($build->url())->assertOk();
    Poe2Server::actingAs($owner)->tool(GetBuildTool::class, ['id' => $build->public_id])->assertOk();
});

test('the promoted columns are derived from the payload on save', function () {
    Poe2Server::actingAs(User::factory()->create())->tool(SaveBuildTool::class, [
        'name' => 'Promoted',
        'build' => publishableBuild() + [
            'tier' => 'S',
            'stage' => 'bossing',
            'cost_divine' => 12.5,
            'hardcore_viable' => true,
        ],
    ])->assertOk();

    $build = Build::sole();

    expect($build->class)->toBe('Witch')
        ->and($build->ascendancy)->toBe('Infernalist')
        ->and($build->level)->toBe(90)
        ->and($build->tier)->toBe('S')
        ->and($build->stage)->toBe('bossing')
        ->and($build->dps)->toBe(4_100_000)
        ->and($build->ehp)->toBe(18_900)
        ->and((float) $build->cost_divine)->toBe(12.5)
        ->and($build->hardcore_viable)->toBeTrue();
});

test('stage is derived from content_tier when no stage is given', function () {
    Poe2Server::actingAs(User::factory()->create())->tool(SaveBuildTool::class, [
        'name' => 'Mapper',
        'build' => ['skills' => [['gem' => 'Spark']]] + ['content_tier' => 'early_endgame'],
    ])->assertOk();

    expect(Build::sole()->stage)->toBe('mapping');
});
