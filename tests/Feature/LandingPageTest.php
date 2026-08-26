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

    Game::factory()->live()->create([
        'slug' => 'diablo-4',
        'name' => 'Diablo IV',
        'short_name' => 'D4',
        'sort_order' => 1,
    ]);

    $queued = Game::factory()->create([
        'slug' => 'last-epoch',
        'name' => 'Last Epoch',
        'sort_order' => 2,
    ]);

    Build::factory()->count(2)->public()->for($live)->create();
    Build::factory()->draft()->for($live)->create();

    GameVote::create(['game_id' => $queued->id, 'email' => 'exile@example.com']);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Welcome')
            ->where('stats.builds_published', 2)
            ->where('stats.games_live', 2)
            ->has('gameCards', 3)
            ->where('gameCards.0.slug', 'poe2')
            ->where('gameCards.0.is_live', true)
            ->where('gameCards.0.builds', 2)
            ->where('gameCards.2.is_live', false)
            ->where('gameCards.2.votes', 1)
            ->where('gameCards.2.url', route('games.show', 'last-epoch'))
            ->has('toolkits', 2)
        );
});

/**
 * Each live game exposes its own MCP endpoint with its own toolset, so the
 * homepage receives one toolkit per live game rather than a single flat list.
 */
test('the landing page carries one toolkit per live game', function () {
    Game::factory()->live()->create([
        'slug' => 'poe2',
        'name' => 'Path of Exile 2',
        'short_name' => 'PoE 2',
        'sort_order' => 0,
    ]);

    Game::factory()->live()->create([
        'slug' => 'diablo-4',
        'name' => 'Diablo IV',
        'short_name' => 'D4',
        'sort_order' => 1,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Welcome')
            ->has('toolkits', 2)
            ->where('toolkits.0.slug', 'poe2')
            ->where('toolkits.1.slug', 'diablo-4')
            ->where('toolkits.0.tools', fn ($tools) => collect($tools)->pluck('name')->contains('search_gems'))
            ->where('toolkits.1.tools', fn ($tools) => collect($tools)->pluck('name')->contains('search_aspects'))
        );
});
