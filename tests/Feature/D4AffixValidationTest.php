<?php

use App\Domain\D4\D4Context;
use App\Domain\D4\Validation\D4BuildValidator;
use App\Models\D4\Affix;
use Tests\Fixtures\D4Seeder;

beforeEach(function () {
    D4Seeder::seed();
});

function validateD4Gear(array $gear): array
{
    return (new D4BuildValidator(app(D4Context::class)))->validate([
        'equipped_skills' => [['skill' => 'Whirlwind']],
        'gear' => $gear,
    ]);
}

test('a structured affix with a plausible roll passes quietly', function () {
    $crit = Affix::where('key', 'CritChance')->sole();

    $result = validateD4Gear([
        'helm' => ['affixes' => [
            ['affix' => 'CritChance', 'value' => $crit->value_range['min']],
        ]],
    ]);

    expect(collect($result['warnings'])->filter(fn (string $warning) => str_contains($warning, 'Affix')))->toHaveCount(0);
});

test('an unresolvable affix key warns and points at search_affixes', function () {
    $result = validateD4Gear([
        'helm' => ['affixes' => [['affix' => 'No_Such_Affix', 'value' => 5]]],
    ]);

    expect(collect($result['warnings'])->filter(fn (string $warning) => str_contains($warning, 'does not resolve')))->toHaveCount(1);
});

test('a roll outside the datamined range warns, accepting either percent scale', function () {
    $crit = Affix::where('key', 'CritChance')->sole();
    $absurd = ((float) $crit->value_range['max']) * 100 * 50;

    $outside = validateD4Gear([
        'helm' => ['affixes' => [['affix' => 'CritChance', 'value' => $absurd]]],
    ]);

    expect(collect($outside['warnings'])->filter(fn (string $warning) => str_contains($warning, 'outside the datamined roll range')))->toHaveCount(1);

    // The same roll written as the displayed percentage (x100) is fine.
    $percent = validateD4Gear([
        'helm' => ['affixes' => [['affix' => 'CritChance', 'value' => ((float) $crit->value_range['max']) * 100]]],
    ]);

    expect(collect($percent['warnings'])->filter(fn (string $warning) => str_contains($warning, 'outside the datamined roll range')))->toHaveCount(0);
});

test('items carrying only unstructured affix text get one summary warning', function () {
    $result = validateD4Gear([
        'helm' => ['affixes' => ['+845 Maximum Life']],
        'chest' => ['affixes' => ['+90 Dexterity', '+12% Damage Reduction']],
        'gloves' => ['affixes' => [['affix' => 'CritChance', 'value' => 0.05]]],
    ]);

    $summaries = collect($result['warnings'])->filter(fn (string $warning) => str_contains($warning, 'unstructured affix text'));

    expect($summaries)->toHaveCount(1)
        ->and($summaries->first())->toContain('helm', 'chest')
        ->and($summaries->first())->not->toContain('gloves');
});
