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

test('save_build stores the build and returns a shareable url', function () {
    Poe2Server::actingAs(User::factory()->create())->tool(SaveBuildTool::class, [
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
        ->assertSee('poe2');

    $build = Build::sole();

    expect($build->public_id)->toHaveLength(12)
        ->and($build->name)->toBe('Spark Starter')
        ->and($build->validation['valid'])->toBeTrue()
        ->and($build->game_version_id)->not->toBeNull();
});

test('save_build stores validation violations for a flawed build', function () {
    Poe2Server::actingAs(User::factory()->create())->tool(SaveBuildTool::class, [
        'name' => 'Broken Build',
        'build' => [
            'skills' => [
                ['gem' => 'Spark', 'supports' => ['Pierce']],
                ['gem' => 'Arctic Armour', 'supports' => ['Pierce']],
            ],
        ],
    ])->assertOk();

    expect(Build::sole()->validation['valid'])->toBeFalse();
});

test('get_build returns a saved build by public id', function () {
    Poe2Server::actingAs(User::factory()->create())->tool(SaveBuildTool::class, [
        'name' => 'Spark Starter',
        'build' => ['skills' => [['gem' => 'Spark']]],
    ])->assertOk();

    Poe2Server::tool(GetBuildTool::class, ['id' => Build::sole()->public_id])
        ->assertOk()
        ->assertSee('Spark Starter');

    Poe2Server::tool(GetBuildTool::class, ['id' => 'nonexistent42'])
        ->assertHasErrors();
});

test('the build page renders with escaped markdown guide and hover entities', function () {
    Poe2Server::actingAs(User::factory()->create())->tool(SaveBuildTool::class, [
        'name' => 'Spark Starter',
        'guide_markdown' => "## Concept\n\n<script>alert(1)</script> Roll and zap with Spark. Grab Astramentis and the Chaos Inoculation keystone.",
        'build' => [
            'class' => 'Witch',
            'skills' => [['gem' => 'Spark', 'supports' => ['Pierce']]],
            'passives' => ['keystones' => ['Chaos Inoculation']],
            'resistances' => ['fire' => 75],
        ],
    ])->assertOk();

    $build = Build::sole();

    $response = $this->get($build->url())->assertOk();

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

test('save_build associates the build with the signed-in user', function () {
    $user = User::factory()->create();

    Poe2Server::actingAs($user)->tool(SaveBuildTool::class, [
        'name' => 'Owned Build',
        'build' => ['skills' => [['gem' => 'Spark']]],
    ])->assertOk();

    expect(Build::sole()->user_id)->toBe($user->id);
});

test('save_build without an authenticated user returns an error', function () {
    Poe2Server::tool(SaveBuildTool::class, [
        'name' => 'Anonymous Build',
        'build' => ['skills' => [['gem' => 'Spark']]],
    ])->assertHasErrors();

    expect(Build::count())->toBe(0);
});

test('save_build with an id updates the existing build in place', function () {
    $user = User::factory()->create();

    Poe2Server::actingAs($user)->tool(SaveBuildTool::class, [
        'name' => 'First Draft',
        'build' => ['skills' => [['gem' => 'Spark']]],
    ])->assertOk();

    $build = Build::sole();

    Poe2Server::actingAs($user)->tool(SaveBuildTool::class, [
        'id' => $build->public_id,
        'name' => 'Second Draft',
        'summary' => 'Now with pierce.',
        'build' => ['skills' => [['gem' => 'Spark', 'supports' => ['Pierce']]]],
    ])->assertOk()->assertSee($build->public_id);

    expect(Build::count())->toBe(1)
        ->and($build->refresh()->name)->toBe('Second Draft')
        ->and($build->summary)->toBe('Now with pierce.')
        // Supports are normalised to objects on save.
        ->and($build->build['skills'][0]['supports'])->toBe([['name' => 'Pierce', 'effect' => null]]);
});

test('save_build cannot update another users build', function () {
    $owner = User::factory()->create();

    Poe2Server::actingAs($owner)->tool(SaveBuildTool::class, [
        'name' => 'Owned Build',
        'build' => ['skills' => [['gem' => 'Spark']]],
    ])->assertOk();

    $build = Build::sole();

    Poe2Server::actingAs(User::factory()->create())->tool(SaveBuildTool::class, [
        'id' => $build->public_id,
        'name' => 'Hijacked',
        'build' => ['skills' => [['gem' => 'Spark']]],
    ])->assertHasErrors();

    expect(Build::count())->toBe(1)
        ->and($build->refresh()->name)->toBe('Owned Build');
});
