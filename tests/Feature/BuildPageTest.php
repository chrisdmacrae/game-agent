<?php

use App\Models\Build;
use App\Models\BuildBookmark;
use App\Models\Endorsement;
use App\Models\Game;
use App\Models\User;
use Tests\Fixtures\Poe2Seeder;

beforeEach(function () {
    $this->version = Poe2Seeder::seed();
    $this->game = $this->version->game;
    $this->game->update(['is_live' => true]);
});

function buildFor(Game $game, ?User $owner = null, string $visibility = Build::VISIBILITY_PUBLIC): Build
{
    return Build::factory()
        ->for($owner ?? User::factory())
        ->for($game)
        ->create(['visibility' => $visibility]);
}

test('the canonical build url is namespaced by game', function () {
    $build = buildFor($this->game);

    expect($build->url())->toBe(route('games.builds.show', [$this->game->slug, $build->public_id]));

    $this->get($build->url())
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Builds/Show')
            ->where('build.id', $build->public_id)
            ->where('game.slug', $this->game->slug)
            ->where('viewer.can_edit', false)
        );
});

test('the sidebar lists up to three similar public builds and never the build itself', function () {
    $build = Build::factory()->public()->for($this->game)->for(User::factory())->create([
        'build' => ['class' => 'Witch', 'stage' => 'endgame', 'skills' => [['gem' => 'Spark']]],
    ]);

    foreach (range(1, 4) as $i) {
        Build::factory()->public()->for($this->game)->for(User::factory())->create([
            'build' => ['class' => 'Witch', 'skills' => [['gem' => 'Spark']]],
        ]);
    }

    // A draft and a build from another class/stage must not be suggested.
    Build::factory()->for($this->game)->for(User::factory())->create([
        'visibility' => Build::VISIBILITY_DRAFT,
        'build' => ['class' => 'Witch', 'skills' => [['gem' => 'Spark']]],
    ]);

    $unrelated = Build::factory()->public()->for($this->game)->for(User::factory())->create([
        'build' => ['class' => 'Ranger', 'stage' => 'leveling', 'skills' => [['gem' => 'Spark']]],
    ]);

    $this->get($build->url())
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('similarBuilds', 3)
            ->where('similarBuilds', fn ($similar) => collect($similar)
                ->pluck('id')
                ->doesntContain($build->public_id)
                && collect($similar)->pluck('id')->doesntContain($unrelated->public_id))
        );
});

test('the old build url redirects permanently to the canonical one', function () {
    $build = buildFor($this->game);

    $this->get(route('builds.show', $build->public_id))
        ->assertRedirect($build->url())
        ->assertStatus(301);
});

test('a build reached through the wrong game 404s', function () {
    $build = buildFor($this->game);
    $other = Game::factory()->live()->create();

    $this->get(route('games.builds.show', [$other->slug, $build->public_id]))->assertNotFound();
});

test('a draft is visible to its owner only', function () {
    $owner = User::factory()->create();
    $draft = buildFor($this->game, $owner, Build::VISIBILITY_DRAFT);

    $this->get($draft->url())->assertNotFound();
    $this->get(route('builds.show', $draft->public_id))->assertNotFound();

    $this->actingAs(User::factory()->create())->get($draft->url())->assertNotFound();

    $this->actingAs($owner)
        ->get($draft->url())
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('viewer.can_edit', true));
});

test('the export endpoints still answer on the old urls', function () {
    $build = buildFor($this->game);

    $this->get(route('builds.pob', $build->public_id))->assertOk()->assertJsonStructure(['code']);
    $this->get(route('builds.build-file', $build->public_id))->assertOk();
    $this->get(route('builds.og-image', $build->public_id))->assertOk();
});

test('a signed-in user endorses a build once and can take it back', function () {
    $build = buildFor($this->game);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from($build->url())
        ->post(route('games.builds.endorse', [$this->game->slug, $build->public_id]))
        ->assertRedirect($build->url());

    // A second endorsement is a no-op, not a duplicate row.
    $this->actingAs($user)
        ->from($build->url())
        ->post(route('games.builds.endorse', [$this->game->slug, $build->public_id]));

    expect(Endorsement::count())->toBe(1)
        ->and($build->fresh()->endorsements_count)->toBe(1);

    $this->actingAs($user)
        ->from($build->url())
        ->delete(route('games.builds.endorse', [$this->game->slug, $build->public_id]));

    expect(Endorsement::count())->toBe(0)
        ->and($build->fresh()->endorsements_count)->toBe(0);
});

test('you cannot endorse your own build', function () {
    $owner = User::factory()->create();
    $build = buildFor($this->game, $owner);

    $this->actingAs($owner)
        ->post(route('games.builds.endorse', [$this->game->slug, $build->public_id]))
        ->assertForbidden();

    expect(Endorsement::count())->toBe(0);
});

test('endorsing requires signing in and a visible build', function () {
    $build = buildFor($this->game);

    $this->post(route('games.builds.endorse', [$this->game->slug, $build->public_id]))
        ->assertRedirect(route('login'));

    $draft = buildFor($this->game, null, Build::VISIBILITY_DRAFT);

    $this->actingAs(User::factory()->create())
        ->post(route('games.builds.endorse', [$this->game->slug, $draft->public_id]))
        ->assertNotFound();
});

test('a build is bookmarked once and unbookmarked', function () {
    $build = buildFor($this->game);
    $user = User::factory()->create();

    foreach (range(1, 2) as $i) {
        $this->actingAs($user)
            ->from($build->url())
            ->post(route('games.builds.bookmark', [$this->game->slug, $build->public_id]))
            ->assertRedirect($build->url());
    }

    expect(BuildBookmark::count())->toBe(1);

    $this->actingAs($user)
        ->from($build->url())
        ->delete(route('games.builds.bookmark', [$this->game->slug, $build->public_id]));

    expect(BuildBookmark::count())->toBe(0);
});
