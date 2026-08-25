<?php

use App\Domain\Poe2\BuildPlannerExporter;
use App\Mcp\Servers\Poe2Server;
use App\Mcp\Tools\Poe2\SaveBuildTool;
use App\Models\Build;
use App\Models\User;
use Tests\Fixtures\Poe2Seeder;

beforeEach(function () {
    Poe2Seeder::seed();
});

function savePlannerTestBuild(): Build
{
    Poe2Server::actingAs(User::factory()->create())->tool(SaveBuildTool::class, [
        'name' => 'Spark Starter',
        'summary' => 'A lightning caster starter.',
        'build' => [
            'class' => 'Witch',
            'ascendancy' => 'Infernalist',
            'level' => 90,
            'skills' => [['gem' => 'Spark', 'supports' => ['Pierce']]],
            'passives' => [
                // 1003 is a jewel socket with no GGG id and must be skipped.
                'node_ids' => [1000, 1001, 1003],
                'ascendancy_nodes' => ['Infernal Flame'],
            ],
        ],
    ])->assertOk();

    return Build::sole();
}

test('the build planner export maps nodes to ggg passive ids', function () {
    $build = savePlannerTestBuild();

    $file = app(BuildPlannerExporter::class)->build($build);

    expect($file['name'])->toBe('Spark Starter')
        ->and($file['description'])->toBe('A lightning caster starter.')
        ->and($file['ascendancy'])->toBe('Witch1')
        ->and($file['link'])->toContain($build->public_id)
        ->and($file['passives'])->toBe([
            'witch_arcane_path1',
            'keystone_chaos_inoculation',
            'AscendancyWitch1Notable1',
        ]);
});

test('the build planner json is a valid build object', function () {
    $build = savePlannerTestBuild();

    $decoded = json_decode(app(BuildPlannerExporter::class)->json($build), true);

    expect($decoded)->toBeArray()
        ->and($decoded['name'])->toBe('Spark Starter')
        ->and($decoded['passives'])->toContain('keystone_chaos_inoculation');
});

test('the build-file endpoint serves a .build download', function () {
    $build = savePlannerTestBuild();

    $response = $this->get("/builds/{$build->public_id}/build-file")
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'application/json')
        ->assertHeader('Content-Disposition', 'attachment; filename="spark-starter.build"');

    $decoded = json_decode($response->getContent(), true);

    expect($decoded['ascendancy'])->toBe('Witch1')
        ->and($decoded['passives'])->toBe([
            'witch_arcane_path1',
            'keystone_chaos_inoculation',
            'AscendancyWitch1Notable1',
        ]);
});

test('the build-file endpoint 404s for unknown builds', function () {
    $this->get('/builds/does-not-exist/build-file')->assertNotFound();
});
