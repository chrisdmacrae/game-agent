<?php

use App\Domain\D4\Validation\D4BuildRules;
use Illuminate\Support\Facades\Validator;

/**
 * The request-validation shape shared by the D4 save_build and validate_build
 * tools. These are pure rule assertions, so they need no imported game data.
 *
 * @param  array<string, mixed>  $build
 */
function d4RuleErrors(array $build): array
{
    return Validator::make($build, D4BuildRules::rules())->errors()->keys();
}

test('a full build passes the rules', function () {
    expect(d4RuleErrors([
        'class' => 'Barbarian',
        'level' => 70,
        'armor' => 12400,
        'resistances' => ['fire' => 70, 'cold' => 70, 'lightning' => 70, 'poison' => 70, 'shadow' => 70],
        'content_tier' => 'pit_push',
        'stage' => 'endgame',
        'tier' => 'S',
        'dps' => 8400000,
        'ehp' => 412000,
        'hardcore_viable' => true,
        'equipped_skills' => [
            ['skill' => 'Whirlwind', 'rank' => 15, 'role' => 'Main damage', 'modifiers' => ['Tornado'], 'reported' => '8.4M per tick'],
            ['skill' => 'Rallying Cry'],
            ['skill' => 'Challenging Shout'],
            ['skill' => 'War Cry'],
            ['skill' => 'Charge'],
            ['skill' => 'Call of the Ancients'],
        ],
        'skill_points' => [['skill' => 'Aggressive Resistance', 'points' => 3]],
        'paragon' => [
            ['board' => 'Start', 'rotation' => 90, 'glyph' => 'Enchanter', 'glyph_level' => 100, 'notables' => ['Blood Rage']],
        ],
        'gear' => [
            'helm' => ['name' => 'Soul Onus', 'rarity' => 'legendary', 'aspect' => 'of Berserk Ripping', 'masterwork_level' => 12],
            'chest' => [
                'name' => 'Dread Ire',
                'rarity' => 'legendary',
                'affixes' => ['+12% Damage Reduction'],
                'greater_affixes' => 2,
                'tempered' => [['affix' => 'AttackSpeed_Sorc_Tag_Pyromancy', 'tier' => 1]],
                'runes' => ['Rune_Condition_CastRepeatSkill', 'Rune_Effect_Spiritborn_Vortex'],
            ],
            'weapons' => [
                ['name' => "Ancients' Oath", 'item_type' => 'Axe2H', 'rarity' => 'unique'],
            ],
        ],
        'seasonal_power' => 'Witchcraft: Aura of Lament',
        'mercenary' => ['hired' => 'Raheir', 'reinforcement' => 'Subo'],
        'milestones' => [['level' => 50, 'text' => 'Swap to Whirlwind']],
        'stats' => [
            'offence' => [['label' => 'Total DPS', 'value' => '8.4M']],
            'defence' => [['label' => 'Effective HP', 'value' => '412k']],
        ],
        'how_it_plays' => ['Shout, charge in, spin.'],
        'works_because' => ['Dust devils inherit the whole damage bucket.'],
        'watch_out_for' => ['Fury starved before the ranks come online.'],
    ]))->toBe([]);
});

test('a minimal build is just the equipped skills', function () {
    expect(d4RuleErrors(['equipped_skills' => [['skill' => 'Whirlwind']]]))->toBe([]);
});

test('equipped_skills is the one required field', function () {
    expect(d4RuleErrors(['class' => 'Barbarian']))->toContain('equipped_skills');
});

test('more than six equipped skills is rejected', function () {
    $skills = array_fill(0, 7, ['skill' => 'Whirlwind']);

    expect(d4RuleErrors(['equipped_skills' => $skills]))->toContain('equipped_skills');
});

test('the level cap and the skill rank ceiling are enforced', function () {
    expect(d4RuleErrors(['level' => 71, 'equipped_skills' => [['skill' => 'Whirlwind']]]))
        ->toContain('level')
        ->and(d4RuleErrors(['equipped_skills' => [['skill' => 'Whirlwind', 'rank' => 16]]]))
        ->toContain('equipped_skills.0.rank');
});

test('an unknown class is rejected outright', function () {
    expect(d4RuleErrors(['class' => 'Crusader', 'equipped_skills' => [['skill' => 'Whirlwind']]]))
        ->toContain('class');
});

test('nonsense resistances are rejected', function () {
    expect(d4RuleErrors([
        'equipped_skills' => [['skill' => 'Whirlwind']],
        'resistances' => ['fire' => 900, 'shadow' => -500],
    ]))->toContain('resistances.fire', 'resistances.shadow');
});

test('paragon rotations must be quarter turns and boards need a name', function () {
    expect(d4RuleErrors([
        'equipped_skills' => [['skill' => 'Whirlwind']],
        'paragon' => [['board' => 'Start', 'rotation' => 45]],
    ]))->toContain('paragon.0.rotation');

    expect(d4RuleErrors([
        'equipped_skills' => [['skill' => 'Whirlwind']],
        'paragon' => [['glyph' => 'Enchanter']],
    ]))->toContain('paragon.0.board');
});

test('gear rarity, greater affix count and rune sockets are bounded', function () {
    expect(d4RuleErrors([
        'equipped_skills' => [['skill' => 'Whirlwind']],
        'gear' => [
            'helm' => [
                'rarity' => 'ancestral',
                'greater_affixes' => 5,
                'runes' => ['a', 'b', 'c'],
                'masterwork_level' => 20,
            ],
        ],
    ]))->toContain(
        'gear.helm.rarity',
        'gear.helm.greater_affixes',
        'gear.helm.runes',
        'gear.helm.masterwork_level',
    );
});

test('the weapons list is capped and validated like any other item', function () {
    expect(d4RuleErrors([
        'equipped_skills' => [['skill' => 'Whirlwind']],
        'gear' => ['weapons' => array_fill(0, 5, ['name' => 'Axe'])],
    ]))->toContain('gear.weapons');

    expect(d4RuleErrors([
        'equipped_skills' => [['skill' => 'Whirlwind']],
        'gear' => ['weapons' => [['name' => 'Axe', 'rarity' => 'shiny']]],
    ]))->toContain('gear.weapons.0.rarity');
});
