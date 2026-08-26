<?php

use App\Domain\Builds\GameBuildProfile;
use App\Domain\Builds\GameReference;
use App\Domain\Builds\PublishChecklist;
use App\Domain\Seo\OgImageRenderer;
use App\Mcp\Servers\D4Server;
use App\Mcp\Tools\D4\SaveBuildTool;
use App\Models\Build;
use App\Models\Game;
use App\Models\User;
use Tests\Fixtures\D4Seeder;

beforeEach(function () {
    $this->version = D4Seeder::seed();
    $this->game = $this->version->game;
    $this->owner = User::factory()->create();

    $this->build = Build::factory()
        ->for($this->owner)
        ->for($this->game)
        ->create([
            'game_version_id' => $this->version->id,
            'visibility' => Build::VISIBILITY_DRAFT,
            'build' => ['equipped_skills' => [['skill' => 'Whirlwind']]],
        ]);
});

/**
 * A Diablo IV payload that passes the publish pre-flight: headline stats,
 * something equipped, a full action bar entry and a paragon board.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function d4Payload(array $overrides = []): array
{
    return array_merge([
        'class' => 'Barbarian',
        'level' => 70,
        'stage' => 'endgame',
        'tier' => 'S',
        'content_tier' => 'pit_push',
        'dps' => 8_400_000,
        'ehp' => 412_000,
        'hardcore_viable' => true,
        'equipped_skills' => [['skill' => 'Whirlwind', 'role' => 'Main damage', 'rank' => 12]],
        'paragon' => [['board' => 'Start', 'rotation' => 90, 'glyph' => 'Might']],
        'gear' => [
            'helm' => ['name' => 'Soul Onus', 'rarity' => 'legendary'],
            'weapons' => [['name' => "Ancients' Oath", 'rarity' => 'unique']],
        ],
    ], $overrides);
}

/**
 * The editor's request body around a build payload.
 *
 * @param  array<string, mixed>  $build
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function d4EditorRequest(array $build, array $overrides = []): array
{
    return array_merge([
        'name' => 'Whirlwind Dust Devils',
        'summary' => 'Spin to win, with tornadoes.',
        'guide_markdown' => '## Concept',
        'visibility' => Build::VISIBILITY_DRAFT,
        'build' => $build,
    ], $overrides);
}

/*
|--------------------------------------------------------------------------
| Per-game rule dispatch
|--------------------------------------------------------------------------
*/

test('a D4 build saves through the web editor and keeps its D4-only fields', function () {
    $this->actingAs($this->owner)
        ->from(route('games.builds.edit', [$this->game->slug, $this->build->public_id]))
        ->patch(
            route('games.builds.update', [$this->game->slug, $this->build->public_id]),
            d4EditorRequest(d4Payload()),
        )
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('games.builds.edit', [$this->game->slug, $this->build->public_id]));

    $build = $this->build->fresh();

    // The validator only strips what the rules do not name, so paragon and the
    // keyed gear map surviving proves the D4 rules ran, not the PoE 2 ones.
    expect($build->build['paragon'][0]['board'])->toBe('Start')
        ->and($build->build['gear']['helm']['name'])->toBe('Soul Onus')
        ->and($build->build['equipped_skills'][0]['skill'])->toBe('Whirlwind')
        ->and($build->class)->toBe('Barbarian')
        ->and($build->tier)->toBe('S')
        ->and($build->stage)->toBe('endgame')
        ->and($build->validation)->toHaveKey('valid');
});

test('a PoE 2 shaped payload is rejected by the D4 route', function () {
    $this->actingAs($this->owner)
        ->from(route('games.builds.edit', [$this->game->slug, $this->build->public_id]))
        ->patch(
            route('games.builds.update', [$this->game->slug, $this->build->public_id]),
            d4EditorRequest([
                'class' => 'Witch',
                'ascendancy' => 'Infernalist',
                'skills' => [['gem' => 'Spark', 'supports' => ['Pierce']]],
                'gear' => [['slot' => 'body', 'rarity' => 'rare', 'name' => 'Chest']],
            ]),
        )
        ->assertSessionHasErrors(['build.equipped_skills', 'build.class']);
});

test('the D4 rules reject values the D4 game does not have', function () {
    $this->actingAs($this->owner)
        ->from(route('games.builds.edit', [$this->game->slug, $this->build->public_id]))
        ->patch(
            route('games.builds.update', [$this->game->slug, $this->build->public_id]),
            d4EditorRequest(d4Payload([
                'tier' => 'Z',
                'equipped_skills' => array_fill(0, 7, ['skill' => 'Whirlwind']),
            ])),
        )
        ->assertSessionHasErrors(['build.tier', 'build.equipped_skills']);
});

/*
|--------------------------------------------------------------------------
| Publish pre-flight
|--------------------------------------------------------------------------
*/

test('a complete D4 build publishes through the web editor', function () {
    $this->actingAs($this->owner)
        ->patch(
            route('games.builds.update', [$this->game->slug, $this->build->public_id]),
            d4EditorRequest(d4Payload(), ['visibility' => Build::VISIBILITY_PUBLIC]),
        )
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('games.builds.show', [$this->game->slug, $this->build->public_id]));

    expect($this->build->fresh()->visibility)->toBe('public');
});

test('the same complete D4 build publishes through save_build', function () {
    D4Server::actingAs($this->owner)->tool(SaveBuildTool::class, [
        'name' => 'Whirlwind Dust Devils',
        'guide_markdown' => '## Concept',
        'visibility' => Build::VISIBILITY_PUBLIC,
        'build' => d4Payload(),
    ])->assertOk();

    expect(Build::query()->where('name', 'Whirlwind Dust Devils')->sole()->visibility)
        ->toBe(Build::VISIBILITY_PUBLIC);
});

test('the pre-flight reports D4 anatomy, never passive points or body armour', function () {
    $build = Build::factory()
        ->for($this->owner)
        ->for($this->game)
        ->create([
            'game_version_id' => $this->version->id,
            'build' => ['class' => 'Barbarian', 'level' => 70],
        ]);

    $checks = collect(app(PublishChecklist::class)->for($build));

    expect($checks->pluck('key')->all())->toBe(['stats', 'gear', 'skills', 'paragon', 'computed', 'patch']);

    $details = $checks->pluck('detail')->filter()->join(' ');

    expect($details)->toContain('action bar')
        ->toContain('paragon board')
        ->toContain('helm')
        ->not->toContain('passive points')
        ->not->toContain('body armour');
});

test('publishing an incomplete D4 build comes back as a visibility error', function () {
    $payload = d4Payload();
    unset($payload['paragon'], $payload['gear'], $payload['dps'], $payload['ehp']);

    $this->actingAs($this->owner)
        ->from(route('games.builds.edit', [$this->game->slug, $this->build->public_id]))
        ->patch(
            route('games.builds.update', [$this->game->slug, $this->build->public_id]),
            d4EditorRequest($payload, ['visibility' => Build::VISIBILITY_PUBLIC]),
        )
        ->assertSessionHasErrors('visibility');

    expect($this->build->fresh()->visibility)->toBe('draft');
});

test('a leveling D4 build publishes without a paragon plan', function () {
    $payload = d4Payload(['level' => 45, 'stage' => 'leveling', 'content_tier' => 'leveling']);
    unset($payload['paragon']);

    $this->actingAs($this->owner)
        ->patch(
            route('games.builds.update', [$this->game->slug, $this->build->public_id]),
            d4EditorRequest($payload, ['visibility' => Build::VISIBILITY_PUBLIC]),
        )
        ->assertSessionHasNoErrors();

    expect($this->build->fresh()->visibility)->toBe('public');
});

/*
|--------------------------------------------------------------------------
| Pages, exports and reference data
|--------------------------------------------------------------------------
*/

test('a D4 build page ships its entity dictionary, with the PoE 2-only props empty', function () {
    $build = Build::factory()
        ->public()
        ->for($this->owner)
        ->for($this->game)
        ->create(['game_version_id' => $this->version->id, 'build' => d4Payload()]);

    $this->get($build->url())
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Builds/Show')
            ->where('game.slug', 'diablo-4')
            ->where('build.definition.paragon.0.board', 'Start')
            ->where('entities.Whirlwind.kind', 'skill')
            ->where('gearView', ['slots' => [], 'jewels' => []])
            ->where('ascendancyPathIds', [])
            ->where('spriteUrl', null)
            ->where('treeUrl', null)
            ->where('ascendancyKey', null)
            ->has('similarBuilds')
            ->has('viewer')
        );
});

test('the D4 build page ships the grids of the boards the build attaches, and no others', function () {
    $build = Build::factory()
        ->public()
        ->for($this->owner)
        ->for($this->game)
        ->create(['game_version_id' => $this->version->id, 'build' => d4Payload()]);

    $this->get($build->url())
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            // Only the named board comes back — a full board table is cells
            // the page would never draw.
            ->has('paragonBoards', 1)
            ->where('paragonBoards.0.name', 'Start')
            ->where('paragonBoards.0.grid', fn ($grid) => $grid->count() > 0)
        );
});

test('a D4 build naming a board the import does not have gets no grids', function () {
    $payload = d4Payload(['paragon' => [['board' => 'Ancestral Guidance']]]);

    $build = Build::factory()
        ->public()
        ->for($this->owner)
        ->for($this->game)
        ->create(['game_version_id' => $this->version->id, 'build' => $payload]);

    $this->get($build->url())
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('paragonBoards', []));
});

test('the D4 editor gets the same board grids the build page does', function () {
    $this->build->update(['build' => d4Payload()]);

    $this->actingAs($this->owner)
        ->get(route('games.builds.edit', [$this->game->slug, $this->build->public_id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Builds/Edit')
            ->where('paragonBoards.0.name', 'Start')
        );
});

test('a PoE 2 build carries the paragon prop empty, the way D4 carries the tree props', function () {
    $poe2 = Game::factory()->create(['slug' => 'poe2']);

    $build = Build::factory()
        ->public()
        ->for($this->owner)
        ->for($poe2)
        ->create(['build' => ['class' => 'Witch']]);

    // Both games send the same prop keys so the page shell never has to
    // special-case a missing one.
    expect(GameBuildProfile::for($poe2)->treeProps($build)['paragonBoards'])->toBe([]);
});

test('the D4 hub is live and offers the D4 class filter', function () {
    $this->game->update(['is_live' => true]);

    Build::factory()
        ->public()
        ->for($this->owner)
        ->for($this->game)
        ->create(['game_version_id' => $this->version->id, 'build' => d4Payload()]);

    $this->get(route('games.show', $this->game->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Games/Hub')
            ->where('game.is_live', true)
            ->where('options.ascendancies', [])
            ->where('options.classes', fn ($classes) => $classes->contains('Barbarian'))
            ->has('builds', 1)
        );
});

test('the D4 editor offers D4 classes, no ascendancies and the shared tiers', function () {
    $this->actingAs($this->owner)
        ->get(route('games.builds.edit', [$this->game->slug, $this->build->public_id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Builds/Edit')
            ->where('options.ascendancies', [])
            ->where('options.tiers', ['S', 'A', 'B', 'C'])
            ->where('options.classes', fn ($classes) => $classes->contains('Barbarian'))
            ->has('checklist', 6)
        );
});

test('GameReference reads the D4 class roster and has no ascendancies for it', function () {
    $reference = app(GameReference::class);

    expect($reference->classes($this->game))->toContain('Barbarian')
        ->and($reference->ascendancies($this->game))->toBe([])
        ->and($reference->tiers($this->game))->toBe(['S', 'A', 'B', 'C']);
});

test('the Path of Building exports 404 for a D4 build', function () {
    $build = Build::factory()
        ->public()
        ->for($this->owner)
        ->for($this->game)
        ->create(['game_version_id' => $this->version->id, 'build' => d4Payload()]);

    $this->get(route('builds.pob', $build->public_id))->assertNotFound();
    $this->get(route('builds.build-file', $build->public_id))->assertNotFound();
});

test('a D4 share card is titled for Diablo IV, not Path of Exile 2', function () {
    $build = Build::factory()
        ->public()
        ->for($this->owner)
        ->for($this->game)
        ->create([
            'game_version_id' => $this->version->id,
            'summary' => null,
            'build' => d4Payload(),
        ]);

    $renderer = Mockery::mock(OgImageRenderer::class);
    $renderer->shouldReceive('render')
        ->once()
        ->withArgs(fn (string $kicker, string $title, ?string $subtitle, array $badges) => $kicker === 'D4 Theorycrafter'
            && str_contains($subtitle, 'Diablo IV')
            && ! str_contains($subtitle, 'Path of Exile'))
        ->andReturn('png-bytes');

    app()->instance(OgImageRenderer::class, $renderer);

    $this->get(route('builds.og-image', $build->public_id))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});
