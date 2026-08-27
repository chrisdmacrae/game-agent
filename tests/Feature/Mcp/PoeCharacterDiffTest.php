<?php

use App\Domain\Poe2\Ggg\CharacterBuildDiff;
use App\Domain\Poe2\Ggg\CharacterNormalizer;
use Tests\Fixtures\Poe2Seeder;

beforeEach(function () {
    Poe2Seeder::seed();
});

/**
 * A build that deliberately differs from the fixture character in every way
 * the diff reports on.
 *
 * @return array<string, mixed>
 */
function targetBuildPayload(): array
{
    return [
        'class' => 'Witch',
        'ascendancy' => 'Infernalist',
        'level' => 85,
        'skills' => [
            ['gem' => 'Spark', 'supports' => ['Pierce', 'Considered Casting']],
            ['gem' => 'Frost Bomb'],
        ],
        'passives' => ['node_ids' => [1000, 1001, 1002]],
        'gear' => [
            [
                'slot' => 'body',
                'rarity' => 'rare',
                'mods' => ['+# to maximum Life', '+#% to Lightning Resistance'],
            ],
            ['slot' => 'helmet', 'rarity' => 'unique', 'name' => 'Goldrim'],
            ['slot' => 'gloves', 'rarity' => 'rare', 'mods' => ['+# to maximum Life']],
        ],
    ];
}

/** @return array<string, mixed> */
function diffFixtureCharacter(): array
{
    $character = json_decode(file_get_contents(base_path('tests/Fixtures/ggg/character.json')), true)['character'];

    return app(CharacterBuildDiff::class)->compare(
        app(CharacterNormalizer::class)->normalize($character),
        targetBuildPayload(),
    );
}

test('a character below the build level is reported with the gap', function () {
    $identity = collect(diffFixtureCharacter()['identity']);

    expect($identity->pluck('kind')->all())->toBe(['below_build_level'])
        ->and($identity->first()['character'])->toBe(78)
        ->and($identity->first()['build'])->toBe(85);
});

test('the passive diff names missing and extra nodes', function () {
    $passives = diffFixtureCharacter()['passives'];

    expect($passives['comparable'])->toBeTrue()
        ->and($passives['character_points_used'])->toBe(3)
        ->and(collect($passives['missing'])->pluck('name')->all())->toBe(['Heightened Curses'])
        ->and(collect($passives['extra'])->pluck('name')->all())->toBe(['Infernal Flame']);
});

test('a build with no node_ids says so instead of diffing nothing', function () {
    $character = json_decode(file_get_contents(base_path('tests/Fixtures/ggg/character.json')), true)['character'];

    $passives = app(CharacterBuildDiff::class)->compare(
        app(CharacterNormalizer::class)->normalize($character),
        ['skills' => [['gem' => 'Spark']]],
    )['passives'];

    expect($passives['comparable'])->toBeFalse()
        ->and($passives['character_points_used'])->toBe(3);
});

test('missing gems and swapped supports are reported per skill', function () {
    $skills = diffFixtureCharacter()['skills'];

    expect($skills['missing_gems'])->toBe(['Frost Bomb'])
        ->and($skills['extra_gems'])->toBe(['Flame Wall']);

    $spark = collect($skills['supports'])->firstWhere('gem', 'Spark');

    expect($spark['missing'])->toBe(['Considered Casting'])
        ->and($spark['extra'])->toBe(['Martial Tempo']);
});

test('gear reports empty slots, wrong uniques and missing rare mods', function () {
    $gear = collect(diffFixtureCharacter()['gear'])->keyBy('slot');

    expect($gear['gloves']['kind'])->toBe('empty_slot')
        ->and($gear['helmet']['kind'])->toBe('different_item')
        ->and($gear['helmet']['character'])->toBe('Wanderlust')
        ->and($gear['helmet']['build'])->toBe('Goldrim')
        // "+# to maximum Life" is satisfied by the rolled "+89 to maximum
        // Life"; the lightning resistance the build wants is genuinely absent.
        ->and($gear['body']['kind'])->toBe('missing_mods')
        ->and($gear['body']['missing'])->toBe(['+#% to Lightning Resistance']);
});

test('the diff refuses to imply a stat comparison the API cannot support', function () {
    expect(diffFixtureCharacter()['not_comparable'][0])->toContain('resistances, DPS, EHP');
});
