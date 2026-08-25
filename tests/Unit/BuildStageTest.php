<?php

use App\Domain\Builds\BuildStage;

test('content tiers map onto stages', function (string $contentTier, string $stage) {
    expect(BuildStage::fromContentTier($contentTier)?->value)->toBe($stage);
})->with([
    ['campaign', 'leveling'],
    ['early_endgame', 'mapping'],
    ['endgame', 'endgame'],
    ['pinnacle', 'bossing'],
]);

test('an unknown or missing content tier maps to nothing', function () {
    expect(BuildStage::fromContentTier(null))->toBeNull()
        ->and(BuildStage::fromContentTier('delve'))->toBeNull();
});

test('an explicit stage wins over the content tier', function () {
    expect(BuildStage::fromBuild(['stage' => 'bossing', 'content_tier' => 'campaign'])?->value)->toBe('bossing')
        ->and(BuildStage::fromBuild(['content_tier' => 'campaign'])?->value)->toBe('leveling')
        ->and(BuildStage::fromBuild(['stage' => 'nonsense', 'content_tier' => 'pinnacle'])?->value)->toBe('bossing')
        ->and(BuildStage::fromBuild([]))->toBeNull();
});
