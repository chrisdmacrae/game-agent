<?php

use App\Domain\Poe2\PobExporter;
use App\Mcp\Servers\Poe2Server;
use App\Mcp\Tools\Poe2\GetBuildTool;
use App\Mcp\Tools\Poe2\SaveBuildTool;
use App\Mcp\Tools\Poe2\ValidateBuildTool;
use App\Models\Build;
use App\Models\User;
use Tests\Fixtures\Poe2Seeder;

beforeEach(function () {
    Poe2Seeder::seed();
});

test('the extended build payload round-trips through save_build and get_build', function () {
    $user = User::factory()->create();

    $definition = [
        'class' => 'Witch',
        'ascendancy' => 'Infernalist',
        'level' => 92,
        'skills' => [[
            'gem' => 'Spark',
            'role' => 'Main damage',
            'level' => 20,
            'quality' => 20,
            'cost' => '38 mana',
            'tags' => ['Spell', 'Projectile', 'Lightning'],
            'reported' => '4.1M dps against a level 84 boss',
            'supports' => [['name' => 'Pierce', 'effect' => 'Projectiles pierce an extra enemy']],
        ]],
        'gear' => [[
            'slot' => 'body',
            'rarity' => 'rare',
            'name' => 'Storm Weave',
            'runes' => ['Iron Rune', null, ''],
        ]],
        'charms' => [['name' => 'Stone Charm', 'note' => 'Stun immunity']],
        'flasks' => [['name' => 'Ultimate Life Flask', 'note' => 'Instant recovery']],
        'milestones' => [
            ['level' => 12, 'text' => 'Swap to Spark'],
            ['level' => 38, 'text' => 'Take the ascendancy'],
        ],
        'stats' => [
            'offence' => [['label' => 'Total DPS', 'value' => '4.1M']],
            'defence' => [['label' => 'Effective HP', 'value' => '18.9k']],
        ],
        'how_it_plays' => ['Roll in, drop Spark, roll out.'],
        'works_because' => ['Shock stacking multiplies the base damage.'],
        'watch_out_for' => ['Untested on 0.5.2.'],
        'stage' => 'endgame',
        'tier' => 'A',
        'dps' => 4_100_000,
        'ehp' => 18_900,
        'cost_divine' => 12.5,
        'hardcore_viable' => false,
    ];

    Poe2Server::actingAs($user)->tool(SaveBuildTool::class, [
        'name' => 'Everything Build',
        'build' => $definition,
    ])->assertOk();

    $stored = Build::sole()->build;

    expect($stored['skills'][0]['role'])->toBe('Main damage')
        ->and($stored['skills'][0]['level'])->toBe(20)
        ->and($stored['skills'][0]['quality'])->toBe(20)
        ->and($stored['skills'][0]['cost'])->toBe('38 mana')
        ->and($stored['skills'][0]['tags'])->toBe(['Spell', 'Projectile', 'Lightning'])
        ->and($stored['skills'][0]['reported'])->toContain('4.1M dps')
        ->and($stored['skills'][0]['supports'])->toBe([['name' => 'Pierce', 'effect' => 'Projectiles pierce an extra enemy']])
        // An empty socket survives as a null entry.
        ->and($stored['gear'][0]['runes'])->toBe(['Iron Rune', null, ''])
        ->and($stored['charms'])->toBe([['name' => 'Stone Charm', 'note' => 'Stun immunity']])
        ->and($stored['flasks'][0]['name'])->toBe('Ultimate Life Flask')
        ->and($stored['milestones'])->toHaveCount(2)
        ->and($stored['stats']['offence'][0])->toBe(['label' => 'Total DPS', 'value' => '4.1M'])
        ->and($stored['stats']['defence'][0]['value'])->toBe('18.9k')
        ->and($stored['how_it_plays'])->toBe(['Roll in, drop Spark, roll out.'])
        ->and($stored['works_because'])->toHaveCount(1)
        ->and($stored['watch_out_for'])->toBe(['Untested on 0.5.2.'])
        ->and($stored['stage'])->toBe('endgame')
        ->and($stored['tier'])->toBe('A')
        ->and($stored['hardcore_viable'])->toBeFalse();

    Poe2Server::actingAs($user)->tool(GetBuildTool::class, ['id' => Build::sole()->public_id])
        ->assertOk()
        ->assertSee('Main damage')
        ->assertSee('Stone Charm')
        ->assertSee('Iron Rune');
});

test('supports accept both a name and a name-with-effect object and are stored as objects', function () {
    Poe2Server::actingAs(User::factory()->create())->tool(SaveBuildTool::class, [
        'name' => 'Mixed Supports',
        'build' => [
            'skills' => [[
                'gem' => 'Spark',
                'supports' => ['Pierce', ['name' => 'Heavy Swing', 'effect' => 'More melee damage']],
            ]],
        ],
    ])->assertOk();

    expect(Build::sole()->build['skills'][0]['supports'])->toBe([
        ['name' => 'Pierce', 'effect' => null],
        ['name' => 'Heavy Swing', 'effect' => 'More melee damage'],
    ]);
});

test('validate_build reads object supports as gem names', function () {
    Poe2Server::tool(ValidateBuildTool::class, [
        'skills' => [
            ['gem' => 'Spark', 'supports' => [['name' => 'Heavy Swing']]],
        ],
    ])
        ->assertOk()
        // Heavy Swing is melee-only, so it must still be rejected for Spark.
        ->assertSee('cannot support');
});

test('the build page renders a build carrying object supports', function () {
    $user = User::factory()->create();

    Poe2Server::actingAs($user)->tool(SaveBuildTool::class, [
        'name' => 'Object Supports',
        'build' => [
            'class' => 'Witch',
            'skills' => [['gem' => 'Spark', 'supports' => [['name' => 'Pierce', 'effect' => 'Pierces one more']]]],
        ],
    ])->assertOk();

    $page = $this->actingAs($user)
        ->get(Build::sole()->url())
        ->assertOk()
        ->inertiaPage();

    expect($page['props']['entities'])->toHaveKeys(['Spark', 'Pierce']);
});

test('the pob export includes supports given as objects', function () {
    Poe2Server::actingAs(User::factory()->create())->tool(SaveBuildTool::class, [
        'name' => 'Object Supports',
        'build' => [
            'class' => 'Witch',
            'skills' => [['gem' => 'Spark', 'supports' => [['name' => 'Pierce', 'effect' => 'Pierces one more']]]],
        ],
    ])->assertOk();

    $xml = app(PobExporter::class)->xml(Build::sole());

    expect($xml)->toContain('nameSpec="Spark"')
        ->and($xml)->toContain('nameSpec="Pierce"');
});

test('the validator warns about implausible rune sockets and late milestones', function () {
    Poe2Server::tool(ValidateBuildTool::class, [
        'level' => 60,
        'skills' => [['gem' => 'Spark']],
        'gear' => [['slot' => 'ring1', 'rarity' => 'rare', 'runes' => ['Iron Rune']]],
        'milestones' => [['level' => 90, 'text' => 'Swap the amulet']],
    ])
        ->assertOk()
        ->assertSee('jewellery has no rune sockets')
        ->assertSee('past the build');
});
