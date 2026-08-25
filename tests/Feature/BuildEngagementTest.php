<?php

use App\Models\Build;
use App\Models\BuildBookmark;
use App\Models\Endorsement;
use App\Models\Game;
use App\Models\GameVote;
use App\Models\User;
use Illuminate\Database\QueryException;

test('a user endorses a build once', function () {
    $user = User::factory()->create();
    $build = Build::factory()->public()->create();

    Endorsement::create(['user_id' => $user->id, 'build_id' => $build->id]);

    expect(fn () => Endorsement::create(['user_id' => $user->id, 'build_id' => $build->id]))
        ->toThrow(QueryException::class);

    expect($build->endorsements()->count())->toBe(1)
        ->and($user->endorsements()->count())->toBe(1);
});

test('a user bookmarks a build once', function () {
    $user = User::factory()->create();
    $build = Build::factory()->public()->create();

    BuildBookmark::create(['user_id' => $user->id, 'build_id' => $build->id]);

    expect(fn () => BuildBookmark::create(['user_id' => $user->id, 'build_id' => $build->id]))
        ->toThrow(QueryException::class);

    expect($build->bookmarks()->count())->toBe(1)
        ->and($user->bookmarkedBuilds()->pluck('builds.id')->all())->toBe([$build->id]);
});

test('an email votes for a game once, case-insensitively', function () {
    $game = Game::factory()->create();

    GameVote::create(['game_id' => $game->id, 'email' => 'Player@Example.com']);

    expect(GameVote::sole()->email)->toBe('player@example.com');

    expect(fn () => GameVote::create(['game_id' => $game->id, 'email' => 'PLAYER@example.com ']))
        ->toThrow(QueryException::class);

    expect($game->votes()->count())->toBe(1)
        ->and(GameVote::sole()->notify_on_launch)->toBeTrue();
});

test('the same email votes for different games', function () {
    $one = Game::factory()->create();
    $two = Game::factory()->create();

    GameVote::create(['game_id' => $one->id, 'email' => 'player@example.com']);
    GameVote::create(['game_id' => $two->id, 'email' => 'player@example.com']);

    expect(GameVote::count())->toBe(2);
});
