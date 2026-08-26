<?php

use App\Domain\D4\Import\FormulaEvaluator;

/**
 * Row 34 of PowerFormulaTables is SkillRankBonus, indexed by skill rank: the
 * first two entries are 1.0 and the curve climbs from there.
 *
 * @return array<int, list<float>>
 */
function formulaTables(): array
{
    return [
        34 => [1.0, 1.0, 1.1, 1.2, 1.3, 1.45],
        35 => [0.0, 2.0],
    ];
}

function evaluator(): FormulaEvaluator
{
    return new FormulaEvaluator(formulaTables());
}

test('arithmetic honours precedence and parentheses', function (string $formula, float $expected) {
    expect(evaluator()->value($formula))->toBe($expected);
})->with([
    ['1', 1.0],
    ['0.25', 0.25],
    ['.5', 0.5],
    ['2 + 3 * 4', 14.0],
    ['(2 + 3) * 4', 20.0],
    ['2 - 3 - 4', -5.0],
    ['100 / 5 / 2', 10.0],
    ['-3 + 1', -2.0],
    ['- (2 * 3)', -6.0],
    ['2 * -3', -6.0],
    ['(13 / 30) / 2', 13 / 60],
    ['(0.5 + 2) / 100', 0.025],
]);

test('the rounding and extremum functions work on either casing', function (string $formula, float $expected) {
    expect(evaluator()->value($formula))->toBe($expected);
})->with([
    ['Round(2.4)', 2.0],
    ['ROUND(2.5)', 3.0],
    ['Floor(2.9)', 2.0],
    ['Ceil(2.1)', 3.0],
    ['Min(1.5, 2)', 1.5],
    ['Max(1.5, 2, 7)', 7.0],
    ['Abs(0 - 4)', 4.0],
]);

test('Table reads the formula tables positionally and indexes them by skill rank', function () {
    expect(evaluator()->value('Table(34,sLevel)', ['sLevel' => 1]))->toBe(1.0)
        ->and(evaluator()->value('Table(34,sLevel)', ['sLevel' => 3]))->toBe(1.2)
        ->and(evaluator()->value('1.65 * Table(34,sLevel)', ['sLevel' => 1]))->toBe(1.65)
        ->and(evaluator()->value('0.5 * Table(35, 1)', []))->toBe(1.0);
});

test('a table lookup outside the sheet evaluates to nothing', function (string $formula) {
    expect(evaluator()->value($formula, ['sLevel' => 900]))->toBeNull();
})->with([
    'Table(99, 1)',
    'Table(34, sLevel)',
]);

test('RandomInt and the unique roll functions evaluate to a range', function (string $formula, float $min, float $max) {
    expect(evaluator()->evaluate($formula))->toBe(['min' => $min, 'max' => $max])
        ->and(evaluator()->value($formula))->toBeNull();
})->with([
    ['RandomInt(2, 5)', 2.0, 5.0],
    ['FloatRandomRangeWithInterval(5, 10, 15)', 10.0, 15.0],
    ['FloatRandomRangeWithIntervalUniqueAffixPityBonus(10, 0.45, 0.6)', 0.45, 0.6],
    ['FloatRangeWithIntervalUniqueAffixPityBonus(SharedRandomFloat(), 10, 100, 120)', 100.0, 120.0],
]);

test('a range carries through the arithmetic wrapped around it', function () {
    // The live AffixCritChance formula at its top item-power breakpoint.
    expect(evaluator()->evaluate('(2.5 + (0.5 * RandomInt(1, 11))) /  100'))
        ->toBe(['min' => 0.03, 'max' => 0.08]);

    expect(evaluator()->evaluate('RandomInt(1, 3) * -2'))->toBe(['min' => -6.0, 'max' => -2.0])
        ->and(evaluator()->evaluate('10 - RandomInt(1, 3)'))->toBe(['min' => 7.0, 'max' => 9.0])
        ->and(evaluator()->evaluate('Round(RandomInt(1, 3) / 2)'))->toBe(['min' => 1.0, 'max' => 2.0]);
});

test('IPower reads the supplied item power and fails without one', function () {
    $formula = '0.5 + ROUND((2/510)*(IPower()-10))';

    expect(evaluator()->value($formula, ['ItemPower' => 750]))->toBe(3.5)
        ->and(evaluator()->value($formula))->toBeNull();
});

test('script formula references resolve through the power that owns them', function () {
    $formulas = [
        0 => '1.65 * Table(34,sLevel)',
        3 => '2',
        4 => 'SF_0 / SF_3',
        12 => '',
        13 => 'SF_12 + 1',
    ];

    expect(evaluator()->value('SF_4', ['sLevel' => 1], $formulas))->toBe(0.825)
        ->and(evaluator()->value('SF_0 / SF_40 * 0.25', ['sLevel' => 1], $formulas))->toBeNull()
        ->and(evaluator()->value('SF_13', [], $formulas))->toBeNull();
});

test('a cycle between script formulas terminates instead of recursing', function () {
    $formulas = [1 => 'SF_2 + 1', 2 => 'SF_1 * 2'];

    expect(evaluator()->value('SF_1', [], $formulas))->toBeNull();
});

test("whirlwind's movement speed formula evaluates to the twelve percent the game shows", function () {
    // Barbarian_Whirlwind ships SF_35 as the literal 0.12 and renders it
    // through "[{SF_35}*100|x%|] increased Movement Speed".
    $formulas = [35 => '0.12'];

    expect(evaluator()->value('SF_35', [], $formulas))->toBe(0.12)
        ->and(evaluator()->value('SF_35 * 100', [], $formulas))->toBe(12.0);
});

test('anything the parser does not understand evaluates to null rather than throwing', function (string $formula) {
    expect(evaluator()->evaluate($formula, ['sLevel' => 1]))->toBeNull();
})->with([
    'empty' => '',
    'blank' => '   ',
    'unknown variable' => 'Attacks_Per_Second_Total',
    'unknown scoped variable' => 'AoE_Size_Bonus_Per_Power#Barbarian_Whirlwind / 2',
    'unknown quoted lookup' => 'Affix.Gloves_Unique_Barb_001."Static Value 2"',
    'unknown function' => 'CurrentLegendaryRank() * 0.01',
    'ternary' => 'Mod.Upgrade1 ? SF_35 : 0',
    'comparison' => 'Mod.UpgradeB > 0',
    'unbalanced parens' => '(1 + 2',
    'trailing operator' => '1 +',
    'stray text' => '1 2',
    'division by zero' => '1 / 0',
    'division by a range spanning zero' => '1 / RandomInt(-1, 1)',
    'missing arguments' => 'Min()',
    'range used as a table index' => 'Table(RandomInt(1, 2), 1)',
]);

test('identifier lookups ignore case and whitespace', function () {
    expect(evaluator()->value('slevel + 1', ['sLevel' => 4]))->toBe(5.0)
        ->and(evaluator()->value('Affix."Static Value 0"', ['Affix."Static Value 0"' => 5]))->toBe(5.0)
        ->and(FormulaEvaluator::normalizeName('Affix. "Static Value 0"'))->toBe('affix."staticvalue0"');
});

test('a supplied value may itself be a range', function () {
    expect(evaluator()->evaluate('VALUE * 100', ['VALUE' => ['min' => 0.03, 'max' => 0.08]]))
        ->toBe(['min' => 3.0, 'max' => 8.0]);
});
