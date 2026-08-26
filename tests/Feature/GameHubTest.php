<?php

use App\Models\Build;
use App\Models\Game;
use App\Models\GameVote;
use App\Models\User;
use Tests\Fixtures\Poe2Seeder;

/**
 * @param  array<string, mixed>  $payload
 */
function hubBuild(Game $game, array $payload, array $attributes = []): Build
{
    return Build::factory()
        ->public()
        ->for($game)
        ->create(array_merge(['build' => array_merge(['skills' => [['gem' => 'Spark']]], $payload)], $attributes));
}

test('a live game renders the hub with its published builds', function () {
    $game = Game::factory()->live()->create(['slug' => 'poe2', 'name' => 'Path of Exile 2']);

    $listed = hubBuild($game, ['class' => 'Witch']);
    Build::factory()->draft()->for($game)->create();

    $this->get(route('games.show', $game->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Games/Hub')
            ->where('game.slug', 'poe2')
            ->has('builds', 1)
            ->where('builds.0.id', $listed->public_id)
            ->where('filters.sort', 'updated')
            ->where('view', 'grid')
        );
});

test('an unknown game slug 404s', function () {
    $this->get('/not-a-game')->assertNotFound();
});

test('the hub filters on class and reports facet counts that ignore the class filter', function () {
    $game = Game::factory()->live()->create();

    hubBuild($game, ['class' => 'Witch']);
    hubBuild($game, ['class' => 'Witch']);
    hubBuild($game, ['class' => 'Ranger']);

    // A draft and another game's build never count.
    Build::factory()->draft()->for($game)->create(['build' => ['class' => 'Witch', 'skills' => []]]);
    hubBuild(Game::factory()->live()->create(), ['class' => 'Witch']);

    $this->get(route('games.show', [$game->slug, 'classes' => ['Witch']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('builds', 2)
            ->where('filters.classes', ['Witch'])
            // Facets still show what picking Ranger instead would return.
            ->where('facets.classes', ['Ranger' => 1, 'Witch' => 2])
        );
});

test('the hub filters on stage, cost, hardcore and the current patch', function () {
    $version = Poe2Seeder::seed();
    $game = $version->game;
    $game->update(['is_live' => true]);

    $cheap = hubBuild($game, ['stage' => 'mapping', 'cost_divine' => 2, 'hardcore_viable' => true], [
        'game_version_id' => $version->id,
    ]);
    $expensive = hubBuild($game, ['stage' => 'endgame', 'cost_divine' => 40, 'hardcore_viable' => false]);

    $this->get(route('games.show', [$game->slug, 'stage' => 'mapping']))
        ->assertInertia(fn ($page) => $page->has('builds', 1)->where('builds.0.id', $cheap->public_id));

    $this->get(route('games.show', [$game->slug, 'min_divine' => 10]))
        ->assertInertia(fn ($page) => $page->has('builds', 1)->where('builds.0.id', $expensive->public_id));

    $this->get(route('games.show', [$game->slug, 'max_divine' => 10]))
        ->assertInertia(fn ($page) => $page->has('builds', 1)->where('builds.0.id', $cheap->public_id));

    $this->get(route('games.show', [$game->slug, 'hardcore_viable' => 1]))
        ->assertInertia(fn ($page) => $page->has('builds', 1)->where('builds.0.id', $cheap->public_id));

    $this->get(route('games.show', [$game->slug, 'current_patch_only' => 1]))
        ->assertInertia(fn ($page) => $page->has('builds', 1)->where('builds.0.id', $cheap->public_id));

    // A junk stage is ignored rather than erroring.
    $this->get(route('games.show', [$game->slug, 'stage' => 'nonsense']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('builds', 2)->where('filters.stage', null));
});

test('the hub sorts on the requested column', function () {
    $game = Game::factory()->live()->create();

    $strong = hubBuild($game, ['dps' => 9_000_000, 'cost_divine' => 40]);
    $cheap = hubBuild($game, ['dps' => 100, 'cost_divine' => 1]);
    $loved = hubBuild($game, []);
    $loved->update(['endorsements_count' => 12]);

    $ids = fn (string $sort) => collect(
        $this->get(route('games.show', [$game->slug, 'sort' => $sort]))->inertiaPage()['props']['builds']
    )->pluck('id')->all();

    expect($ids('dps')[0])->toBe($strong->public_id)
        ->and($ids('cost')[0])->toBe($cheap->public_id)
        ->and($ids('endorsements')[0])->toBe($loved->public_id);
});

test('the ascendancy options follow the selected classes', function () {
    $version = Poe2Seeder::seed();
    $game = $version->game;
    $game->update(['is_live' => true]);

    $this->get(route('games.show', $game->slug))
        ->assertInertia(fn ($page) => $page
            ->where('options.classes', ['Ranger', 'Witch'])
            ->has('options.ascendancies', 2)
        );

    $this->get(route('games.show', [$game->slug, 'classes' => ['Witch']]))
        ->assertInertia(fn ($page) => $page
            ->has('options.ascendancies', 1)
            ->where('options.ascendancies.0.name', 'Infernalist')
        );
});

test('the filter rail is per game: PoE 2 offers ascendancy, budget and hardcore, Diablo IV does not', function () {
    $poe2 = Game::factory()->live()->create(['slug' => 'poe2']);
    $d4 = Game::factory()->live()->create(['slug' => 'diablo-4']);

    $keys = fn (Game $game) => collect(
        $this->get(route('games.show', $game->slug))->inertiaPage()['props']['filterRail']
    )->pluck('key')->all();

    expect($keys($poe2))->toBe([
        'classes', 'ascendancy', 'stage', 'budget', 'current_patch_only', 'hardcore_viable',
    ])->and($keys($d4))->toBe(['classes', 'stage', 'current_patch_only']);

    // Cheapest-first sorts on divine orbs, so it is PoE 2 only.
    $this->get(route('games.show', $poe2->slug))
        ->assertInertia(fn ($page) => $page->where('options.sorts', ['updated', 'endorsements', 'dps', 'cost']));

    $this->get(route('games.show', $d4->slug))
        ->assertInertia(fn ($page) => $page->where('options.sorts', ['updated', 'endorsements', 'dps']));
});

test('a filter the game does not offer is ignored rather than applied', function () {
    $d4 = Game::factory()->live()->create(['slug' => 'diablo-4']);

    hubBuild($d4, ['class' => 'Barbarian', 'cost_divine' => 2, 'hardcore_viable' => true]);
    hubBuild($d4, ['class' => 'Sorcerer', 'cost_divine' => 40]);

    $this->get(route('games.show', [
        $d4->slug,
        'ascendancy' => 'Infernalist',
        'min_divine' => 10,
        'max_divine' => 20,
        'hardcore_viable' => 1,
        'sort' => 'cost',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('builds', 2)
            ->where('filters.ascendancy', null)
            ->where('filters.min_divine', null)
            ->where('filters.max_divine', null)
            ->where('filters.hardcore_viable', false)
            ->where('filters.sort', 'updated')
        );

    // The filters it does offer still work.
    $this->get(route('games.show', [$d4->slug, 'classes' => ['Barbarian']]))
        ->assertInertia(fn ($page) => $page->has('builds', 1)->where('filters.classes', ['Barbarian']));
});

test('signed-in visitors get a strip of their three most recent builds for the game', function () {
    $game = Game::factory()->live()->create();
    $user = User::factory()->create();

    Build::factory()->count(4)->for($user)->for($game)->create();
    Build::factory()->for($user)->for(Game::factory()->live()->create())->create();

    $this->get(route('games.show', $game->slug))
        ->assertInertia(fn ($page) => $page->has('yourBuilds', 0));

    $this->actingAs($user)
        ->get(route('games.show', $game->slug))
        ->assertInertia(fn ($page) => $page->has('yourBuilds', 3));
});

test('a queued game renders the waitlist with its place in the queue', function () {
    $second = Game::factory()->create(['slug' => 'last-epoch', 'name' => 'Last Epoch', 'sort_order' => 1]);
    $first = Game::factory()->create(['slug' => 'diablo-4', 'name' => 'Diablo IV', 'sort_order' => 2]);
    Game::factory()->live()->create(['slug' => 'poe2']);

    GameVote::create(['game_id' => $first->id, 'email' => 'a@example.com']);
    GameVote::create(['game_id' => $first->id, 'email' => 'b@example.com']);
    GameVote::create(['game_id' => $second->id, 'email' => 'c@example.com']);

    $this->get(route('games.show', $second->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Games/Waitlist')
            ->where('game.slug', 'last-epoch')
            ->where('votes', 1)
            ->where('queuePosition', 2)
            ->where('patch', null)
            ->has('queue', 2)
            ->where('queue.0.slug', 'diablo-4')
            ->where('queue.0.votes', 2)
        );
});

test('every page shares the ordered game list and the mcp url', function () {
    Game::factory()->live()->create(['slug' => 'poe2', 'name' => 'Path of Exile 2', 'sort_order' => 0]);
    Game::factory()->create(['slug' => 'wow', 'name' => 'World of Warcraft', 'sort_order' => 3]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('games', 2)
            ->where('games.0.slug', 'poe2')
            ->where('games.1.slug', 'wow')
            ->where('mcpUrl', route('mcp.poe2'))
        );
});

test('the landing page carries the game grid counts and the stat strip', function () {
    $version = Poe2Seeder::seed();
    $poe2 = $version->game;
    $poe2->update(['is_live' => true, 'sort_order' => 0]);

    hubBuild($poe2, ['class' => 'Witch']);
    Build::factory()->draft()->for($poe2)->create();

    $queued = Game::factory()->create(['slug' => 'wow', 'sort_order' => 3]);
    GameVote::create(['game_id' => $queued->id, 'email' => 'a@example.com']);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.builds_published', 1)
            ->where('stats.games_live', 1)
            ->where('stats.patch', '0.5.2-test')
            ->has('stats.data_refreshed_at')
            ->has('gameCards', 2)
            ->where('gameCards.0.builds', 1)
            ->where('gameCards.0.votes', null)
            ->where('gameCards.1.votes', 1)
            ->where('gameCards.1.builds', null)
        );
});
