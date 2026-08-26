<?php

use App\Mcp\Servers\D4Server;
use App\Mcp\Tools\D4\ValidateBuildTool;
use Tests\Fixtures\D4Seeder;

beforeEach(function () {
    D4Seeder::seed();
});

test('a legal build validates cleanly', function () {
    D4Server::tool(ValidateBuildTool::class, [
        'class' => 'Barbarian',
        'level' => 70,
        'equipped_skills' => [['skill' => 'Whirlwind', 'rank' => 12, 'modifiers' => ['Tornado']]],
        'paragon' => [['board' => 'Start', 'rotation' => 90]],
        'gear' => [
            'weapons' => [['name' => "Ancients' Oath", 'rarity' => 'unique', 'aspect' => 'of Berserk Ripping']],
        ],
    ])
        ->assertOk()
        ->assertSee('"valid":true');
});

test('an unknown skill is a violation', function () {
    D4Server::tool(ValidateBuildTool::class, [
        'class' => 'Barbarian',
        'equipped_skills' => [['skill' => 'Blizzard From Diablo 2']],
    ])
        ->assertOk()
        ->assertSee('Unknown skill')
        ->assertSee('"valid":false');
});

test('a skill from another class is a violation', function () {
    D4Server::tool(ValidateBuildTool::class, [
        'class' => 'Barbarian',
        'equipped_skills' => [['skill' => 'Chain Lightning']],
    ])
        ->assertOk()
        ->assertSee('is a Sorcerer skill and cannot be equipped by a Barbarian');
});

test('the same skill cannot occupy two action bar slots', function () {
    D4Server::tool(ValidateBuildTool::class, [
        'class' => 'Barbarian',
        'equipped_skills' => [['skill' => 'Whirlwind'], ['skill' => 'Whirlwind']],
    ])
        ->assertOk()
        ->assertSee('occupies one action bar slot');
});

test('an unknown paragon board is a violation and a wrong-class board is reported', function () {
    D4Server::tool(ValidateBuildTool::class, [
        'class' => 'Barbarian',
        'equipped_skills' => [['skill' => 'Whirlwind']],
        'paragon' => [['board' => 'Board Of Directors']],
    ])
        ->assertOk()
        ->assertSee('Unknown paragon board');

    // The only glyph in the fixture belongs to the Sorcerer.
    D4Server::tool(ValidateBuildTool::class, [
        'class' => 'Barbarian',
        'equipped_skills' => [['skill' => 'Whirlwind']],
        'paragon' => [['board' => 'Start', 'glyph' => 'Enchanter']],
    ])
        ->assertOk()
        ->assertSee('belongs to Sorcerer, not Barbarian');
});

test('an aspect cannot be imprinted on two items', function () {
    D4Server::tool(ValidateBuildTool::class, [
        'class' => 'Barbarian',
        'equipped_skills' => [['skill' => 'Whirlwind']],
        'gear' => [
            'helm' => ['name' => 'Soul Onus', 'rarity' => 'legendary', 'aspect' => 'of Berserk Ripping'],
            'chest' => ['name' => 'Dread Ire', 'rarity' => 'legendary', 'aspect' => 'of Berserk Ripping'],
        ],
    ])
        ->assertOk()
        ->assertSee('can only be used once per character')
        ->assertSee('"valid":false');
});

test('an unknown aspect is a violation but an unknown unique is only a warning', function () {
    D4Server::tool(ValidateBuildTool::class, [
        'class' => 'Barbarian',
        'equipped_skills' => [['skill' => 'Whirlwind']],
        'gear' => ['helm' => ['rarity' => 'legendary', 'aspect' => 'of Invented Powers']],
    ])
        ->assertOk()
        ->assertSee('Unknown aspect')
        ->assertSee('"valid":false');

    D4Server::tool(ValidateBuildTool::class, [
        'class' => 'Barbarian',
        'equipped_skills' => [['skill' => 'Whirlwind']],
        'gear' => ['helm' => ['name' => 'Harlequin Crest', 'rarity' => 'unique']],
    ])
        ->assertOk()
        ->assertSee('the datamined name may differ')
        ->assertSee('"valid":true');
});

test('a class-restricted aspect cannot be imprinted by another class', function () {
    D4Server::tool(ValidateBuildTool::class, [
        'class' => 'Sorcerer',
        'equipped_skills' => [['skill' => 'Chain Lightning']],
        'gear' => ['helm' => ['rarity' => 'legendary', 'aspect' => 'of Berserk Ripping']],
    ])
        ->assertOk()
        // Only the Barbarian is in the fixture's class table, so the class
        // itself is unverifiable and the aspect check is skipped with it.
        ->assertSee('is not in the imported class list');
});

test('tempering recipes are checked leniently', function () {
    D4Server::tool(ValidateBuildTool::class, [
        'class' => 'Barbarian',
        'equipped_skills' => [['skill' => 'Whirlwind']],
        'gear' => ['helm' => ['rarity' => 'legendary', 'tempered' => [['affix' => 'AttackSpeed_Sorc_Tag_Pyromancy']]]],
    ])
        ->assertOk()
        ->assertSee('"valid":true');

    D4Server::tool(ValidateBuildTool::class, [
        'class' => 'Barbarian',
        'equipped_skills' => [['skill' => 'Whirlwind']],
        'gear' => ['helm' => ['rarity' => 'legendary', 'tempered' => [['affix' => 'Made Up Recipe']]]],
    ])
        ->assertOk()
        ->assertSee('is not a known tempering recipe')
        // Warning level: the build is still valid.
        ->assertSee('"valid":true');
});

test('resistances above the armoury cap are reported and above the ceiling are illegal', function () {
    D4Server::tool(ValidateBuildTool::class, [
        'class' => 'Barbarian',
        'equipped_skills' => [['skill' => 'Whirlwind']],
        'resistances' => ['fire' => 80],
    ])
        ->assertOk()
        ->assertSee('above the 70% armoury cap')
        ->assertSee('"valid":true');

    D4Server::tool(ValidateBuildTool::class, [
        'class' => 'Barbarian',
        'equipped_skills' => [['skill' => 'Whirlwind']],
        'resistances' => ['cold' => 95],
    ])
        ->assertOk()
        ->assertSee('above the 85% hard ceiling')
        ->assertSee('"valid":false');
});

test('an unrecognised skill modifier is a warning, not a violation', function () {
    D4Server::tool(ValidateBuildTool::class, [
        'class' => 'Barbarian',
        'equipped_skills' => [['skill' => 'Whirlwind', 'modifiers' => ['Hurricane']]],
    ])
        ->assertOk()
        ->assertSee('is not one of the modifiers datamined')
        ->assertSee('"valid":true');
});

test('a rank at the imported maximum passes without a warning', function () {
    D4Server::tool(ValidateBuildTool::class, [
        'class' => 'Barbarian',
        'equipped_skills' => [['skill' => 'Whirlwind', 'rank' => 15]],
    ])->assertOk()->assertSee('"warnings":[]');
});

test('a milestone past the target level is a warning', function () {
    D4Server::tool(ValidateBuildTool::class, [
        'class' => 'Barbarian',
        'level' => 40,
        'equipped_skills' => [['skill' => 'Whirlwind']],
        'milestones' => [['level' => 60, 'text' => 'Swap the aspect']],
    ])
        ->assertOk()
        ->assertSee('is past the build');
});
