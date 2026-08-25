<?php

use App\Models\Build;
use App\Models\Game;
use App\Models\User;
use Tests\Fixtures\Poe2Seeder;

beforeEach(function () {
    $this->version = Poe2Seeder::seed();
    $this->game = $this->version->game;
    $this->game->update(['is_live' => true]);
    $this->owner = User::factory()->create();

    $this->build = Build::factory()
        ->for($this->owner)
        ->for($this->game)
        ->create([
            'game_version_id' => $this->version->id,
            'visibility' => Build::VISIBILITY_DRAFT,
        ]);
});

/**
 * A payload that passes the publish pre-flight: headline stats, a body armour
 * and a weapon, and passives inside the level's budget.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function publishablePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Spark Roll-Caster',
        'summary' => 'A budget lightning caster.',
        'guide_markdown' => '## Concept',
        'visibility' => Build::VISIBILITY_DRAFT,
        'build' => [
            'class' => 'Witch',
            'ascendancy' => 'Infernalist',
            'level' => 90,
            'stage' => 'endgame',
            'tier' => 'A',
            'dps' => 4_100_000,
            'ehp' => 18_900,
            'cost_divine' => 12.5,
            'hardcore_viable' => true,
            'skills' => [['gem' => 'Spark', 'supports' => ['Pierce']]],
            'passives' => ['points_used' => 100],
            'gear' => [
                ['slot' => 'body', 'rarity' => 'rare', 'name' => 'Chest'],
                ['slot' => 'weapon1', 'rarity' => 'unique', 'name' => 'Wand'],
            ],
        ],
    ], $overrides);
}

test('the edit page is owner-only', function () {
    $url = route('games.builds.edit', [$this->game->slug, $this->build->public_id]);

    $this->get($url)->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())->get($url)->assertNotFound();

    $public = Build::factory()->public()->for($this->game)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('games.builds.edit', [$this->game->slug, $public->public_id]))
        ->assertForbidden();
});

test('the owner sees the editor with reference options and the checklist', function () {
    $this->actingAs($this->owner)
        ->get(route('games.builds.edit', [$this->game->slug, $this->build->public_id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Builds/Edit')
            ->where('build.id', $this->build->public_id)
            ->where('options.classes', ['Ranger', 'Witch'])
            ->has('options.ascendancies', 2)
            ->where('options.stages', ['leveling', 'mapping', 'endgame', 'bossing'])
            ->where('options.tiers', ['S', 'A', 'B', 'C'])
            ->has('checklist', 4)
        );
});

test('saving a draft keeps it owner-only and re-derives the promoted columns', function () {
    $url = route('games.builds.update', [$this->game->slug, $this->build->public_id]);

    $this->actingAs($this->owner)
        ->from(route('games.builds.edit', [$this->game->slug, $this->build->public_id]))
        ->patch($url, publishablePayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('games.builds.edit', [$this->game->slug, $this->build->public_id]));

    $build = $this->build->fresh();

    expect($build->visibility)->toBe('draft')
        ->and($build->name)->toBe('Spark Roll-Caster')
        ->and($build->class)->toBe('Witch')
        ->and($build->stage)->toBe('endgame')
        ->and($build->tier)->toBe('A')
        ->and($build->dps)->toBe(4_100_000)
        ->and((float) $build->cost_divine)->toBe(12.5)
        ->and($build->validation)->toHaveKey('valid');
});

test('support gems given as names are normalised to objects, as the tool does', function () {
    $this->actingAs($this->owner)
        ->patch(
            route('games.builds.update', [$this->game->slug, $this->build->public_id]),
            publishablePayload(),
        );

    expect($this->build->fresh()->build['skills'][0]['supports'])
        ->toBe([['name' => 'Pierce', 'effect' => null]]);
});

test('publishing runs the pre-flight and lists what is missing', function () {
    $payload = publishablePayload(['visibility' => Build::VISIBILITY_PUBLIC]);
    unset($payload['build']['gear'], $payload['build']['dps'], $payload['build']['ehp']);

    $this->actingAs($this->owner)
        ->from(route('games.builds.edit', [$this->game->slug, $this->build->public_id]))
        ->patch(route('games.builds.update', [$this->game->slug, $this->build->public_id]), $payload)
        ->assertSessionHasErrors('visibility');

    expect($this->build->fresh()->visibility)->toBe('draft');
});

test('publishing a complete build lists it and lands on the build page', function () {
    $this->actingAs($this->owner)
        ->patch(
            route('games.builds.update', [$this->game->slug, $this->build->public_id]),
            publishablePayload(['visibility' => Build::VISIBILITY_PUBLIC]),
        )
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('games.builds.show', [$this->game->slug, $this->build->public_id]));

    expect($this->build->fresh()->visibility)->toBe('public');
});

test('the payload is validated with the same rules the mcp tool uses', function () {
    $this->actingAs($this->owner)
        ->from(route('games.builds.edit', [$this->game->slug, $this->build->public_id]))
        ->patch(
            route('games.builds.update', [$this->game->slug, $this->build->public_id]),
            publishablePayload(['build' => array_merge(publishablePayload()['build'], [
                'tier' => 'Z',
                'skills' => [],
            ])]),
        )
        ->assertSessionHasErrors(['build.tier', 'build.skills']);
});

test('only the owner can save, and never through another game', function () {
    $url = route('games.builds.update', [$this->game->slug, $this->build->public_id]);

    $this->patch($url, publishablePayload())->assertRedirect(route('login'));

    $public = Build::factory()->public()->for($this->game)->create();

    $this->actingAs(User::factory()->create())
        ->patch(
            route('games.builds.update', [$this->game->slug, $public->public_id]),
            publishablePayload(),
        )
        ->assertForbidden();

    $other = Game::factory()->live()->create();

    $this->actingAs($this->owner)
        ->patch(
            route('games.builds.update', [$other->slug, $this->build->public_id]),
            publishablePayload(),
        )
        ->assertNotFound();
});
