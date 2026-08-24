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

test('the build page renders with escaped markdown guide', function () {
    Poe2Server::tool(SaveBuildTool::class, [
        'name' => 'Spark Starter',
        'guide_markdown' => "## Concept\n\n<script>alert(1)</script> Roll and zap.",
        'build' => [
            'class' => 'Witch',
            'skills' => [['gem' => 'Spark', 'supports' => ['Pierce']]],
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
});

test('unknown build ids 404', function () {
    $this->get('/builds/doesnotexist1')->assertNotFound();
});
