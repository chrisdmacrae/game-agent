<?php

use App\Models\Build;
use App\Models\User;

test('the factory produces drafts by default and syncs the promoted columns', function () {
    $build = Build::factory()->create();

    expect($build->visibility)->toBe('draft')
        ->and($build->class)->toBe('Witch')
        ->and($build->ascendancy)->toBe('Infernalist')
        ->and($build->level)->toBe(90);
});

test('the withStats state promotes the headline numbers', function () {
    $build = Build::factory()->public()->withStats()->create();

    expect($build->visibility)->toBe('public')
        ->and($build->stage)->toBe('endgame')
        ->and($build->tier)->toBe('A')
        ->and($build->dps)->toBe(4_100_000)
        ->and($build->ehp)->toBe(18_900)
        ->and((float) $build->cost_divine)->toBe(12.5)
        ->and($build->hardcore_viable)->toBeTrue();
});

test('rewriting the payload re-derives the promoted columns', function () {
    $build = Build::factory()->create();

    $build->update(['build' => ['class' => 'Ranger', 'level' => 42, 'content_tier' => 'campaign', 'skills' => []]]);

    expect($build->refresh()->class)->toBe('Ranger')
        ->and($build->ascendancy)->toBeNull()
        ->and($build->level)->toBe(42)
        ->and($build->stage)->toBe('leveling');
});

test('the visibility scopes hide drafts from everyone but their owner', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();

    $draft = Build::factory()->draft()->for($owner)->create();
    $published = Build::factory()->public()->for($owner)->create();

    expect(Build::query()->public()->pluck('id')->all())->toBe([$published->id])
        ->and(Build::query()->visibleTo(null)->pluck('id')->all())->toBe([$published->id])
        ->and(Build::query()->visibleTo($stranger)->pluck('id')->all())->toBe([$published->id])
        ->and(Build::query()->visibleTo($owner)->orderBy('id')->pluck('id')->all())
        ->toBe([$draft->id, $published->id]);
});
