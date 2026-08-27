<?php

use App\Domain\Poe2\Ggg\CharacterNormalizer;
use Tests\Fixtures\Poe2Seeder;

beforeEach(function () {
    Poe2Seeder::seed();
});

/** @return array<string, mixed> */
function gggCharacterFixture(): array
{
    return json_decode(file_get_contents(base_path('tests/Fixtures/ggg/character.json')), true)['character'];
}

test('a GGG character normalises into the build payload shape', function () {
    $normalized = app(CharacterNormalizer::class)->normalize(gggCharacterFixture());

    expect($normalized['name'])->toBe('TestWitch')
        ->and($normalized['league'])->toBe('Test League')
        ->and($normalized['is_current'])->toBeTrue();

    $build = $normalized['build'];

    // `class` came back as the ascendancy name, so both fields are recovered.
    expect($build['class'])->toBe('Witch')
        ->and($build['ascendancy'])->toBe('Infernalist')
        ->and($build['level'])->toBe(78);
});

test('skill gems carry their level, quality and socketed supports', function () {
    $build = app(CharacterNormalizer::class)->normalize(gggCharacterFixture())['build'];

    $spark = collect($build['skills'])->firstWhere('gem', 'Spark');

    expect($spark['level'])->toBe(19)
        ->and($spark['quality'])->toBe(18)
        // Supports normalise to {name, effect} like everywhere else, and a
        // PoE1-style " Support" suffix is stripped.
        ->and(collect($spark['supports'])->pluck('name')->all())->toBe(['Pierce', 'Martial Tempo'])
        ->and(collect($build['skills'])->pluck('gem')->all())->toContain('Flame Wall');
});

test('equipment maps to build gear slots with display markup stripped', function () {
    $build = app(CharacterNormalizer::class)->normalize(gggCharacterFixture())['build'];

    $gear = collect($build['gear'])->keyBy('slot');

    expect($gear->keys()->all())->toBe(['body', 'helmet', 'ring1'])
        // <<set:MS>>-style markup would break every name match against our data.
        ->and($gear['body']['name'])->toBe('Corpse Shroud')
        ->and($gear['body']['base'])->toBe('Silk Robe')
        ->and($gear['body']['rarity'])->toBe('rare')
        ->and($gear['helmet']['rarity'])->toBe('unique')
        // Implicits and explicits both land in mods.
        ->and($gear['body']['mods'])->toContain('+89 to maximum Life', '10% increased Energy Shield')
        // Two sockets, one filled: the empty one stays as null.
        ->and($gear['body']['runes'])->toBe(['Iron Rune', null]);

    // Flasks are not part of a build payload's gear.
    expect($gear->has('flask'))->toBeFalse();
});

test('allocated passive hashes become node_ids with names resolved', function () {
    $build = app(CharacterNormalizer::class)->normalize(gggCharacterFixture())['build'];

    expect($build['passives']['node_ids'])->toBe([1000, 1001, 2001])
        ->and($build['passives']['points_used'])->toBe(3)
        ->and($build['passives']['keystones'])->toBe(['Chaos Inoculation'])
        ->and($build['passives']['ascendancy_nodes'])->toBe(['Infernal Flame'])
        // Arcane Path is a small passive, not a notable.
        ->and($build['passives'])->not->toHaveKey('notables');
});
