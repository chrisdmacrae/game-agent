<?php

use App\Models\Game;
use App\Models\GameVote;
use App\Models\User;

test('anyone can vote for a queued game without an account', function () {
    $game = Game::factory()->create(['name' => 'Last Epoch']);

    $this->from(route('games.show', $game->slug))
        ->post(route('games.vote', $game->slug), ['email' => 'Player@Example.com'])
        ->assertRedirect(route('games.show', $game->slug))
        ->assertSessionHasNoErrors();

    expect(GameVote::sole()->email)->toBe('player@example.com');
});

test('a second vote from the same address is friendly, not an error', function () {
    $game = Game::factory()->create();

    GameVote::create(['game_id' => $game->id, 'email' => 'player@example.com']);

    $this->from(route('games.show', $game->slug))
        ->post(route('games.vote', $game->slug), ['email' => 'PLAYER@example.com'])
        ->assertRedirect(route('games.show', $game->slug))
        ->assertSessionHasNoErrors();

    expect($game->votes()->count())->toBe(1);
});

test('a signed-in user votes the same way', function () {
    $game = Game::factory()->create();

    $this->actingAs(User::factory()->create())
        ->from(route('games.show', $game->slug))
        ->post(route('games.vote', $game->slug), ['email' => 'player@example.com'])
        ->assertRedirect(route('games.show', $game->slug));

    expect(GameVote::count())->toBe(1);
});

test('an invalid email is rejected', function () {
    $game = Game::factory()->create();

    $this->from(route('games.show', $game->slug))
        ->post(route('games.vote', $game->slug), ['email' => 'nope'])
        ->assertSessionHasErrors('email');

    expect(GameVote::count())->toBe(0);
});

test('a live game does not take votes', function () {
    $game = Game::factory()->live()->create();

    $this->post(route('games.vote', $game->slug), ['email' => 'player@example.com'])
        ->assertNotFound();
});

test('votes are rate limited', function () {
    $game = Game::factory()->create();

    foreach (range(1, 5) as $i) {
        $this->from(route('games.show', $game->slug))
            ->post(route('games.vote', $game->slug), ['email' => 'player@example.com'])
            ->assertRedirect();
    }

    $this->post(route('games.vote', $game->slug), ['email' => 'player@example.com'])
        ->assertStatus(429);
});
