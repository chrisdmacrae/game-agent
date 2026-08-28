<?php

use App\Domain\D4\D4Context;
use App\Domain\D4\Validation\D4BuildValidator;
use Tests\Fixtures\D4Seeder;

beforeEach(function () {
    D4Seeder::seed();
});

function validateD4SkillPoints(array $skillPoints, ?string $class = 'Barbarian'): array
{
    return (new D4BuildValidator(app(D4Context::class)))->validate(array_filter([
        'class' => $class,
        'equipped_skills' => [['skill' => 'Whirlwind']],
        'skill_points' => $skillPoints,
    ]));
}

test('a real skill point spend for the class passes', function () {
    $result = validateD4SkillPoints([['skill' => 'Whirlwind', 'points' => 5]]);

    expect($result['violations'])->toBe([]);
});

test('an invented skill_points name is a violation', function () {
    $result = validateD4SkillPoints([['skill' => 'Zzzz Not A Real Node', 'points' => 3]]);

    expect(collect($result['violations'])->filter(
        fn (string $violation) => str_contains($violation, 'Zzzz Not A Real Node')
            && str_contains($violation, 'skill_points'),
    ))->toHaveCount(1);
});

test('another class\'s skill in skill_points is a violation', function () {
    $result = validateD4SkillPoints([['skill' => 'Chain Lightning', 'points' => 1]]);

    expect(collect($result['violations'])->filter(
        fn (string $violation) => str_contains($violation, 'Chain Lightning')
            && str_contains($violation, 'Sorcerer'),
    ))->toHaveCount(1);
});

test('duplicates and over-cap points are reported', function () {
    $result = validateD4SkillPoints([
        ['skill' => 'Whirlwind', 'points' => 99],
        ['skill' => 'whirlwind', 'points' => 1],
    ]);

    expect(collect($result['violations'])->filter(fn (string $v) => str_contains($v, 'appears 2 times')))->toHaveCount(1)
        ->and(collect($result['warnings'])->filter(fn (string $w) => str_contains($w, 'above the imported maximum')))->toHaveCount(1);
});
