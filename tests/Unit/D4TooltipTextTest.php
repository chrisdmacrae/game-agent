<?php

use App\Domain\D4\TooltipText;

function tooltips(): TooltipText
{
    return new TooltipText;
}

test('cosmetic markup is stripped and the whitespace it leaves is tidied', function (string $raw, string $expected) {
    expect(tooltips()->render($raw))->toBe($expected);
})->with([
    'colour spans' => ['{c_important}Whirlwind{/c} hits hard.', 'Whirlwind hits hard.'],
    'named closer' => ['{c_label}Fury Cost:{/c_label} a lot', 'Fury Cost: a lot'],
    'underline' => ['deals {c_important}{u}Bleeding{/u}{/c} damage', 'deals Bleeding damage'],
    'bold' => ['{b}Careful{/b}', 'Careful'],
    'icons' => ['{icon:bullet, 1.2} Damage {icon:arrow, 1.2} up', 'Damage up'],
    'hex colour' => ['{c:FF00FF}pink{/c}', 'pink'],
    'carriage returns' => ["one\r\n\r\ntwo", "one\n\ntwo"],
]);

test('an empty or all-markup string renders as nothing at all', function (?string $raw) {
    expect(tooltips()->render($raw))->toBeNull();
})->with([null, '', '   ', '{c_number}{/c}']);

test('a display expression is evaluated and formatted for the value it is given', function (string $raw, string $expected) {
    expect(tooltips()->render($raw, ['SF_35' => 0.12, 'SF_49' => 25, 'VALUE' => 0.075]))->toBe($expected);
})->with([
    'multiplicative percent' => ['[{SF_35}*100|x%|]', '12%[x]'],
    'the other spelling of it' => ['[{SF_35}*100|%x|]', '12%[x]'],
    'plain percent' => ['[{SF_35}*100|%|]', '12%'],
    'one decimal percent' => ['[{VALUE}*100|1%|]', '7.5%'],
    'signed percent' => ['[{SF_35}*100|%+|]', '+12%'],
    'signed with decimals' => ['[{SF_35}*100|1%+|]', '+12.0%'],
    'rounded, no suffix' => ['[{SF_49}|~|]', '25'],
    'bare number' => ['[{SF_49}]', '25'],
    'two decimals' => ['[{SF_35}|2|]', '0.12'],
    'unbraced reference' => ['[SF_35 * 100|%|]', '12%'],
    'arithmetic across tokens' => ['[{SF_35}*{SF_49}*100|%|]', '300%'],
]);

test('a roll range renders as a bracketed range with the suffix attached once', function () {
    $values = ['VALUE' => ['min' => 0.03, 'max' => 0.08]];

    expect(tooltips()->render('+[{VALUE}*100|1%|] Critical Strike Chance', $values))
        ->toBe('+[3.0 – 8.0]% Critical Strike Chance')
        ->and(tooltips()->render('[Affix_Value_1*100|%x|] damage', ['Affix_Value_1' => ['min' => 0.45, 'max' => 0.6]]))
        ->toBe('[45 – 60]%[x] damage');
});

test('a bare token is substituted with a readable number', function () {
    expect(tooltips()->render('Gain {SF_10} Fury, or {SF_12} against Elites.', ['SF_10' => 0.61875, 'SF_12' => 2.0]))
        ->toBe('Gain 0.62 Fury, or 2 against Elites.');
});

test('a token with no value stays a token so no number is invented', function (string $raw, string $expected) {
    expect(tooltips()->render($raw, ['SF_35' => 0.12]))->toBe($expected);
})->with([
    'payload' => ['deals {payload:DAMAGE_TOOLTIP} damage', 'deals {payload:DAMAGE_TOOLTIP} damage'],
    'resource cost' => ['Fury Cost: {Resource Cost} per second', 'Fury Cost: {Resource Cost} per second'],
    'damage over time' => ['{dot:UPGRADEB_FIRE_DOT} burning', '{dot:UPGRADEB_FIRE_DOT} burning'],
    'buff duration' => ['over {buffduration:X} seconds', 'over {buffduration:X} seconds'],
    'unknown script formula' => ['{SF_9} attacks', '{SF_9} attacks'],
    'unevaluable expression' => ['[{Combat Effect Chance}|%|]', '[{Combat Effect Chance}|%|]'],
    'unresolved affix roll' => ['[Affix_Value_1*100|%|] bleed', '[Affix_Value_1*100|%|] bleed'],
    'template parameter' => ['+[{SF_35}*100|%|] to {VALUE1}', '+12% to {VALUE1}'],
]);

test('the advanced tooltip branch is kept, because it is the detail a theorycrafter wants', function () {
    $raw = "Cost: 25\r\n{if:ADVANCED_TOOLTIP}Lucky Hit Chance: [{SF_22}|%|]\r\n{/if}Spin.";

    expect(tooltips()->render($raw, ['SF_22' => 20]))->toBe("Cost: 25\nLucky Hit Chance: 20%\nSpin.");
});

test('a conditional on state we do not model keeps its branch but says what it depends on', function () {
    $raw = 'Damage up{if:Mod.UpgradeA} and {c_number}[{SF_35}*100|%|]{/c} Dust Devil damage{/if}.';

    expect(tooltips()->render($raw, ['SF_35' => 0.12]))
        ->toBe('Damage up [Mod.UpgradeA: and 12% Dust Devil damage].');
});

test('a conditional whose branches are only markup collapses away', function () {
    $raw = '{if:SF.IsMythic}{c_mythic}{/if}Enemies take {if:SF.IsMythic}{c_number}{else}{c_random}{/if}'
        .'[{SF_35}*100|%x|]{/c} more damage.{if:SF.IsMythic}{/c_mythic}{/if}';

    expect(tooltips()->render($raw, ['SF_35' => 0.12]))->toBe('Enemies take 12%[x] more damage.');
});

test('an empty true branch falls back to the else branch', function () {
    expect(tooltips()->render('{if:Mod.X}{else}plain text{/if}'))->toBe('plain text');
});

test('nested conditionals resolve innermost first', function () {
    $raw = '{if:Mod.UpgradeA}outer {if:ADVANCED_TOOLTIP}inner{/if}{/if}';

    expect(tooltips()->render($raw))->toBe('[Mod.UpgradeA: outer inner]');
});

test('a dangling conditional tag is dropped rather than left in the text', function () {
    expect(tooltips()->render('Damage up{if:Mod.UpgradeA} and more'))->toBe('Damage up and more');
});

test('script formula values are keyed back to their tokens', function () {
    expect(TooltipText::scriptFormulaValues(['0' => 1.65, '35' => 0.12, '9' => ['min' => 1, 'max' => 3]]))
        ->toBe([
            'SF_0' => 1.65,
            'SF_35' => 0.12,
            'SF_9' => ['min' => 1.0, 'max' => 3.0],
        ])
        ->and(TooltipText::scriptFormulaValues(null))->toBe([])
        ->and(TooltipText::scriptFormulaValues(['1' => 'not a number']))->toBe([]);
});
