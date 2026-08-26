<?php

use App\Domain\Builds\PublishChecklist;
use App\Domain\D4\Calc\ComputedStats;
use App\Domain\D4\Calc\D4BuildComputer;
use App\Models\Build;
use App\Models\User;
use Tests\Fixtures\D4Seeder;

beforeEach(function () {
    $this->version = D4Seeder::seed();
});

/** A structured build the fixture data can fully compute. */
function computableD4Build(array $overrides = []): array
{
    return array_merge([
        'class' => 'Barbarian',
        'level' => 60,
        'equipped_skills' => [['skill' => 'Whirlwind', 'rank' => 5]],
        'gear' => [
            'weapons' => [['name' => 'Fixture Axe', 'item_type' => 'Axe', 'rarity' => 'legendary']],
        ],
    ], $overrides);
}

test('a structured build computes weapon dps, skill dps, life and ehp from the data', function () {
    $computed = app(D4BuildComputer::class)->compute(computableD4Build());

    // The fixture axe is a 1H axe: +10% innate speed => 1.1 APS => the
    // "fast" damage table, at the level-60 loot item power of 750.
    expect($computed['weapon']['speed_class'])->toBe('fast')
        ->and($computed['weapon']['item_power'])->toBe(750)
        ->and($computed['weapon']['attacks_per_second'])->toBe(1.1)
        ->and($computed['weapon']['dps'])->toBeGreaterThan(0);

    expect($computed['dps'])->toBeInt()->toBeGreaterThan(0)
        ->and($computed['skills'][0]['skill'])->toBe('Whirlwind')
        ->and($computed['skills'][0]['rank'])->toBe(5)
        ->and($computed['skills'][0]['weapon_damage_percent'])->toBeGreaterThan(0);

    expect($computed['life'])->toBeGreaterThan(0)
        ->and($computed['ehp'])->toBeGreaterThanOrEqual($computed['life'])
        ->and($computed['offence_rows'])->not->toBeEmpty()
        ->and($computed['defence_rows'])->not->toBeEmpty()
        // The calibration curve is always named as an assumption.
        ->and(collect($computed['assumptions'])->filter(fn (string $a) => str_contains($a, 'calibration')))->not->toBeEmpty();
});

test('structured affix rolls raise the computed numbers', function () {
    $bare = app(D4BuildComputer::class)->compute(computableD4Build());

    $crit = app(D4BuildComputer::class)->compute(computableD4Build([
        'gear' => [
            'weapons' => [['item_type' => 'Axe', 'rarity' => 'legendary']],
            'gloves' => ['affixes' => [['affix' => 'CritChance', 'value' => 0.08]]],
        ],
    ]));

    expect($crit['dps'])->toBeGreaterThan($bare['dps'])
        ->and($crit['coverage']['structured_affixes'])->toBe(1);
});

test('computed stats fill absent dps and ehp, and never clobber explicit numbers', function () {
    $filled = ComputedStats::apply(computableD4Build());

    expect($filled['dps'])->toBeInt()
        ->and($filled['ehp'])->toBeInt()
        ->and($filled['computed']['wrote'])->toContain('dps', 'ehp', 'stats')
        ->and($filled['stats']['offence'])->not->toBeEmpty();

    // Recomputation keeps updating its own numbers.
    $refilled = ComputedStats::apply($filled);

    expect($refilled['computed']['wrote'])->toContain('dps');

    // A hand-entered sheet number stands.
    $hand = ComputedStats::apply(computableD4Build(['dps' => 123456789, 'ehp' => 987654]));

    expect($hand['dps'])->toBe(123456789)
        ->and($hand['ehp'])->toBe(987654)
        ->and($hand['computed']['wrote'])->not->toContain('dps')
        ->and($hand['computed']['dps'])->not->toBe(123456789);
});

test('computed values satisfy the publish stats gate', function () {
    $definition = ComputedStats::apply(computableD4Build());

    $build = Build::factory()
        ->public()
        ->for(User::factory()->create())
        ->for($this->version->game)
        ->create(['game_version_id' => $this->version->id, 'build' => $definition]);

    $stats = collect(app(PublishChecklist::class)->for($build))->firstWhere('key', 'stats');
    $computedCheck = collect(app(PublishChecklist::class)->for($build))->firstWhere('key', 'computed');

    expect($stats['passed'])->toBeTrue()
        ->and($computedCheck['passed'])->toBeTrue();
});
