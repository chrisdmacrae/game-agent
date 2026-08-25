<?php

use App\Models\Build;
use App\Models\Endorsement;
use App\Models\Game;
use App\Models\User;

test('guests are sent to the login page', function () {
    $this->get(route('my-builds'))->assertRedirect(route('login'));
});

test('the old dashboard url redirects to my builds', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('my-builds'));
});

test('my builds lists only the signed-in user\'s builds, grouped by game', function () {
    $poe2 = Game::factory()->live()->create(['slug' => 'poe2', 'name' => 'Path of Exile 2', 'sort_order' => 0]);
    $queued = Game::factory()->create(['slug' => 'wow', 'name' => 'World of Warcraft', 'sort_order' => 3]);

    $user = User::factory()->create();

    $mine = Build::factory()->public()->for($user)->for($poe2)->create(['name' => 'Mine']);
    $onQueued = Build::factory()->public()->for($user)->for($queued)->create();
    Build::factory()->public()->for(User::factory())->for($poe2)->create(['name' => 'Theirs']);

    $this->actingAs($user)
        ->get(route('my-builds'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('MyBuilds')
            ->has('groups', 2)
            ->where('groups.0.game.slug', 'poe2')
            ->has('groups.0.builds', 1)
            ->where('groups.0.builds.0.id', $mine->public_id)
            ->where('groups.1.game.slug', 'wow')
            ->where('groups.1.builds.0.id', $onQueued->public_id)
        );
});

test('drafts are pinned to the top of their group, newest first', function () {
    $game = Game::factory()->live()->create();
    $user = User::factory()->create();

    $oldPublic = Build::factory()->public()->for($user)->for($game)->create();
    $oldDraft = Build::factory()->draft()->for($user)->for($game)->create();
    $newPublic = Build::factory()->public()->for($user)->for($game)->create();
    $newDraft = Build::factory()->draft()->for($user)->for($game)->create();

    $oldPublic->update(['updated_at' => now()->subDays(3)]);
    $oldDraft->update(['updated_at' => now()->subDays(2)]);
    $newPublic->update(['updated_at' => now()->subDay()]);
    $newDraft->update(['updated_at' => now()]);

    $ids = collect(
        $this->actingAs($user)->get(route('my-builds'))->inertiaPage()['props']['groups'][0]['builds']
    )->pluck('id')->all();

    expect($ids)->toBe([
        $newDraft->public_id,
        $oldDraft->public_id,
        $newPublic->public_id,
        $oldPublic->public_id,
    ]);
});

test('live games the user has no builds for still get an empty group', function () {
    Game::factory()->live()->create(['slug' => 'poe2', 'sort_order' => 0]);
    Game::factory()->create(['slug' => 'wow', 'sort_order' => 3]);

    $this->actingAs(User::factory()->create())
        ->get(route('my-builds'))
        ->assertInertia(fn ($page) => $page
            // The queued game the user has nothing in is not listed.
            ->has('groups', 1)
            ->where('groups.0.game.slug', 'poe2')
            ->has('groups.0.builds', 0)
        );
});

test('the header stats count published builds, drafts and endorsements received', function () {
    $game = Game::factory()->live()->create();
    $user = User::factory()->create(['handle' => 'exile']);

    $published = Build::factory()->public()->for($user)->for($game)->create();
    Build::factory()->public()->for($user)->for($game)->create();
    Build::factory()->draft()->for($user)->for($game)->create();

    Endorsement::create(['user_id' => User::factory()->create()->id, 'build_id' => $published->id]);
    $published->increment('endorsements_count');

    $this->actingAs($user)
        ->get(route('my-builds'))
        ->assertInertia(fn ($page) => $page
            ->where('handle', 'exile')
            ->where('stats.published', 2)
            ->where('stats.drafts', 1)
            ->where('stats.endorsements', 1)
            ->where('stats.member_since', now()->format('M Y'))
        );
});
