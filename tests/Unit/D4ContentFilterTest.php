<?php

use App\Domain\D4\Import\ContentFilter;

test('the exact bad-data placeholder is junk', function () {
    expect(new ContentFilter()->isJunkName('Axe Bad Data'))->toBeTrue()
        ->and(new ContentFilter()->isJunkName('Axe'))->toBeFalse();
});

test('scratch-file prefixes are junk', function (string $name) {
    expect(new ContentFilter()->isJunkName($name))->toBeTrue();
})->with([
    'TEST_Legendary_Power1',
    'Test_Something',
    'test_Sorcerer',
    'TESTstephen_Thing',
    'TEMPLATE_WIZARD_Tempered_Affix',
    'AAATestExcelSheet',
    'zzMountArmor',
    'DONOTSHIP_Table',
]);

test('marketing and spreadsheet suffixes are junk', function (string $name) {
    expect(new ContentFilter()->isJunkName($name))->toBeTrue();
})->with([
    'Helm_Unique_Generic_001_Q4BLOG',
    'Chest_Legendary_BlackSabbath',
    'AATestExcelSheet',
    'TestExcelSheet',
]);

test('real content names are not junk', function (string $name) {
    expect(new ContentFilter()->isJunkName($name))->toBeFalse();
})->with([
    'Barbarian_Whirlwind',
    'legendary_barb_001',
    'Paragon_Barb_00',
    'Tempered_AttackSpeed_Sorc_Tag_Pyromancy_Tier1',
    '2HAxe_Unique_Barb_001',
]);

test('a plain definition with a clean name is released', function () {
    expect(new ContentFilter()->isReleased('CritChance', [
        'tRequirementsToBeActive' => ['arSeasons' => []],
        'dwContentLicenseRequirements' => 0,
        'bSeasonItem' => false,
    ]))->toBeTrue();
});

test('structural gates each mark content unreleased', function (array $definition, string $reason) {
    $filter = new ContentFilter;

    expect($filter->isReleased('CleanName', $definition, honourVisibleInUi: true))->toBeFalse()
        ->and($filter->reasons('CleanName', $definition, honourVisibleInUi: true))->toContain($reason);
})->with([
    'ignored on load' => [['bIgnoreOnLoad' => true], 'ignored_on_load'],
    'hidden in ui' => [['bVisibleInUI' => false], 'hidden_in_ui'],
    'season gated' => [['tRequirementsToBeActive' => ['arSeasons' => [7]]], 'season_gated'],
    'season item' => [['bSeasonItem' => true], 'season_item'],
    'license gated' => [['dwContentLicenseRequirements' => 2], 'license_gated'],
]);

test('bVisibleInUI is only honoured for powers', function () {
    $filter = new ContentFilter;
    $definition = ['bVisibleInUI' => false];

    expect($filter->isReleased('CleanName', $definition))->toBeTrue()
        ->and($filter->isReleased('CleanName', $definition, honourVisibleInUi: true))->toBeFalse();
});

test('reasons accumulate every failing gate', function () {
    $reasons = new ContentFilter()->reasons('TEST_Thing', [
        'bIgnoreOnLoad' => true,
        'bSeasonItem' => true,
    ]);

    expect($reasons)->toBe(['junk_name', 'ignored_on_load', 'season_item']);
});

test('an entity with no definition is judged on its name alone', function () {
    $filter = new ContentFilter;

    expect($filter->isReleased('Barbarian'))->toBeTrue()
        ->and($filter->isReleased('Axe Bad Data'))->toBeFalse();
});
