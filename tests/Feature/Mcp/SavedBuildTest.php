<?php

use App\Mcp\Servers\Poe2Server;
use App\Mcp\Tools\Poe2\GetBuildTool;
use App\Mcp\Tools\Poe2\SaveBuildTool;
use App\Models\SavedBuild;
use Tests\Fixtures\Poe2Seeder;

beforeEach(function () {
    Poe2Seeder::seed();
});

test('save_build stores the build and returns a shareable url', function () {
    Poe2Server::tool(SaveBuildTool::class, [
        'name' => 'Spark Starter',
        'summary' => 'A budget-friendly lightning caster.',
        'guide_markdown' => "## Concept\n\nRoll and **zap**.",
        'build' => [
            'class' => 'Witch',
            'ascendancy' => 'Infernalist',
            'skills' => [
                ['gem' => 'Spark', 'supports' => ['Pierce']],
            ],
        ],
    ])
        ->assertOk()
        ->assertSee('"url"')
        ->assertSee('builds');

    $build = SavedBuild::sole();

    expect($build->public_id)->toHaveLength(12)
        ->and($build->name)->toBe('Spark Starter')
        ->and($build->validation['valid'])->toBeTrue()
        ->and($build->game_version_id)->not->toBeNull();
});

test('save_build stores validation violations for a flawed build', function () {
    Poe2Server::tool(SaveBuildTool::class, [
        'name' => 'Broken Build',
        'build' => [
            'skills' => [
                ['gem' => 'Spark', 'supports' => ['Pierce']],
                ['gem' => 'Arctic Armour', 'supports' => ['Pierce']],
            ],
        ],
    ])->assertOk();

    expect(SavedBuild::sole()->validation['valid'])->toBeFalse();
});

test('get_build returns a saved build by public id', function () {
    Poe2Server::tool(SaveBuildTool::class, [
        'name' => 'Spark Starter',
        'build' => ['skills' => [['gem' => 'Spark']]],
    ])->assertOk();

    Poe2Server::tool(GetBuildTool::class, ['id' => SavedBuild::sole()->public_id])
        ->assertOk()
        ->assertSee('Spark Starter');

    Poe2Server::tool(GetBuildTool::class, ['id' => 'nonexistent42'])
        ->assertHasErrors();
});

test('the build page renders with escaped markdown guide and hover entities', function () {
    Poe2Server::tool(SaveBuildTool::class, [
        'name' => 'Spark Starter',
        'guide_markdown' => "## Concept\n\n<script>alert(1)</script> Roll and zap with Spark. Grab Astramentis and the Chaos Inoculation keystone.",
        'build' => [
            'class' => 'Witch',
            'skills' => [['gem' => 'Spark', 'supports' => ['Pierce']]],
            'passives' => ['keystones' => ['Chaos Inoculation']],
            'resistances' => ['fire' => 75],
        ],
    ])->assertOk();

    $build = SavedBuild::sole();

    $response = $this->get("/builds/{$build->public_id}")->assertOk();

    $page = $response->inertiaPage();

    expect($page['component'])->toBe('Builds/Show')
        ->and($page['props']['build']['name'])->toBe('Spark Starter')
        ->and($page['props']['build']['guide_html'])->toContain('<h2>Concept</h2>')
        ->and($page['props']['build']['guide_html'])->not->toContain('<script>alert(1)</script>');

    // Hover-card entity dictionary covers referenced gems/passives and
    // guide-mentioned uniques; guide mentions are wrapped in entity spans.
    $entities = $page['props']['entities'];

    expect($entities)->toHaveKeys(['Spark', 'Pierce', 'Chaos Inoculation', 'Astramentis'])
        ->and($entities['Spark']['kind'])->toBe('gem')
        ->and($entities['Pierce']['kind'])->toBe('support')
        ->and($entities['Chaos Inoculation']['passive_kind'])->toBe('keystone')
        ->and($entities['Astramentis']['mods'])->toContain('+(50-100) to all Attributes')
        ->and($page['props']['build']['guide_html'])->toContain('data-entity="Astramentis"')
        ->and($page['props']['build']['guide_html'])->toContain('data-entity="Spark"');
});

test('unknown build ids 404', function () {
    $this->get('/builds/doesnotexist1')->assertNotFound();
});
