<?php

use App\Domain\D4\D4BuildPayload;

test('normalize trims strings, drops empties and orders the gear map', function () {
    $payload = D4BuildPayload::normalize([
        'class' => '  Barbarian ',
        'level' => '70',
        'equipped_skills' => [
            ['skill' => ' Whirlwind ', 'rank' => '12', 'role' => '', 'modifiers' => ['Tornado', '  ', null]],
            ['skill' => '   '],
            'Rallying Cry',
        ],
        'paragon' => [
            ['board' => ' Start ', 'rotation' => '90', 'glyph' => '', 'notables' => []],
            ['glyph' => 'Enchanter'],
        ],
        'gear' => [
            'boots' => ['name' => ' Corpse Ward '],
            'helm' => ' Soul Onus ',
            'unknown_slot' => ['name' => 'Nope'],
        ],
        'resistances' => ['fire' => '70', 'arcane' => 50],
        'mercenary' => ['hired' => ' Raheir ', 'reinforcement' => ''],
        'seasonal_power' => '   ',
    ]);

    expect($payload['class'])->toBe('Barbarian')
        ->and($payload['equipped_skills'])->toBe([
            ['skill' => 'Whirlwind', 'rank' => 12, 'modifiers' => ['Tornado']],
            ['skill' => 'Rallying Cry'],
        ])
        ->and($payload['paragon'])->toBe([['board' => 'Start', 'rotation' => 90]])
        // Gear keeps slot order and drops keys that are not real slots.
        ->and(array_keys($payload['gear']))->toBe(['helm', 'boots'])
        ->and($payload['gear']['helm'])->toBe(['name' => 'Soul Onus'])
        ->and($payload['resistances'])->toBe(['fire' => 70])
        ->and($payload['mercenary'])->toBe(['hired' => 'Raheir'])
        ->and($payload)->not->toHaveKey('seasonal_power');
});

test('normalize folds gear slot aliases onto the canonical keys', function () {
    $payload = D4BuildPayload::normalize([
        'equipped_skills' => [['skill' => 'Whirlwind']],
        'gear' => [
            'helmet' => ['name' => 'Soul Onus'],
            'body' => ['name' => 'Dread Ire'],
            'legs' => ['name' => 'Rune Wrath'],
            'ring1' => ['name' => 'Sinister Onus'],
            'weapon' => [['name' => "Ancients' Oath"]],
        ],
    ]);

    expect(array_keys($payload['gear']))->toBe(['helm', 'chest', 'pants', 'ring_1', 'weapons'])
        ->and($payload['gear']['weapons'])->toBe([['name' => "Ancients' Oath"]]);
});

test('normalize accepts tempered affixes as bare strings and keeps false flags', function () {
    $payload = D4BuildPayload::normalize([
        'equipped_skills' => [['skill' => 'Whirlwind']],
        'hardcore_viable' => false,
        'gear' => ['helm' => ['tempered' => ['AttackSpeed_Sorc_Tag_Pyromancy', ['affix' => 'Crit', 'tier' => '2']]]],
    ]);

    expect($payload['gear']['helm']['tempered'])->toBe([
        ['affix' => 'AttackSpeed_Sorc_Tag_Pyromancy'],
        ['affix' => 'Crit', 'tier' => 2],
    ])->and($payload['hardcore_viable'])->toBeFalse();
});
