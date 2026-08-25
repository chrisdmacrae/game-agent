<?php

use App\Models\Build;
use App\Models\Game;
use App\Models\GameVote;

/**
 * The root landing page (scope §3.1). The page renders the hero stat strip and
 * the game grid straight from these props, so they are the contract.
 */
test('the landing page carries the hero stats and the game grid', function () {
    $live = Game::factory()->live()->create([
        'slug' => 'poe2',
        'name' => 'Path of Exile 2',
        'short_name' => 'PoE 2',
        'sort_order' => 0,
    ]);

    $queued = Game::factory()->create([
        'slug' => 'last-epoch',
        'name' => 'Last Epoch',
        'sort_order' => 1,
    ]);

    Build::factory()->count(2)->public()->for($live)->create();
    Build::factory()->draft()->for($live)->create();

    GameVote::create(['game_id' => $queued->id, 'email' => 'exile@example.com']);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Welcome')
            ->where('stats.builds_published', 2)
            ->where('stats.games_live', 1)
            ->has('gameCards', 2)
            ->where('gameCards.0.slug', 'poe2')
            ->where('gameCards.0.is_live', true)
            ->where('gameCards.0.builds', 2)
            ->where('gameCards.1.is_live', false)
            ->where('gameCards.1.votes', 1)
            ->where('gameCards.1.url', route('games.show', 'last-epoch'))
            ->has('tools')
            ->has('models')
        );
});
