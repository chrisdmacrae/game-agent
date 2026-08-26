<?php

use App\Domain\D4\Import\D4Importer;
use App\Models\D4\Affix;
use App\Models\D4\Aspect;
use App\Models\D4\CharacterClass;
use App\Models\D4\ItemType;
use App\Models\D4\ParagonBoard;
use App\Models\D4\ParagonGlyph;
use App\Models\D4\Skill;
use App\Models\D4\UniqueItem;
use App\Models\Game;
use App\Models\GameVersion;
use Tests\Fixtures\D4Seeder;

/**
 * The fixture tree mirrors the d4data repo layout with a referentially intact
 * slice of it, so the importer runs end to end without touching the network.
 */
function d4Importer(): D4Importer
{
    return D4Seeder::importer();
}

/** @var array<string, int> */
const D4_FIXTURE_COUNTS = [
    'classes' => 1,
    'skills' => 2,
    'paragon_boards' => 1,
    'paragon_glyphs' => 1,
    'affixes' => 6,
    'aspects' => 1,
    'uniques' => 2,
    'item_types' => 2,
];

test('the importer lands every dataset in the fixture tree', function () {
    $importer = d4Importer();
    $version = $importer->import();

    expect($importer->counts)->toBe(D4_FIXTURE_COUNTS)
        ->and($version->version)->toBe('3.1.3.73224')
        ->and($version->fingerprint)->toBe('fixturecommitsha')
        ->and($version->is_active)->toBeTrue()
        ->and($version->imported_at)->not->toBeNull()
        ->and($version->game->slug)->toBe('diablo-4')
        ->and($version->game->name)->toBe('Diablo IV');

    expect(CharacterClass::forVersion($version->id)->count())->toBe(1)
        ->and(Skill::forVersion($version->id)->count())->toBe(2)
        ->and(ParagonBoard::forVersion($version->id)->count())->toBe(1)
        ->and(ParagonGlyph::forVersion($version->id)->count())->toBe(1)
        ->and(Affix::forVersion($version->id)->count())->toBe(6)
        ->and(Aspect::forVersion($version->id)->count())->toBe(1)
        ->and(UniqueItem::forVersion($version->id)->count())->toBe(2)
        ->and(ItemType::forVersion($version->id)->count())->toBe(2);
});

test('the barbarian class carries its resource and paragon roster', function () {
    $version = d4Importer()->import();

    $barbarian = CharacterClass::forVersion($version->id)->sole();

    expect($barbarian->name)->toBe('Barbarian')
        ->and($barbarian->sno_id)->toBe(169776)
        ->and($barbarian->resource)->toBe('fury')
        ->and($barbarian->is_released)->toBeTrue()
        ->and($barbarian->raw['skill_kit'])->toBe('Barbarian')
        ->and($barbarian->raw['paragon_boards'])->toContain('Paragon_Barb_00');
});

test('whirlwind resolves its category from the primary skill tag and its text from the string list', function () {
    $version = d4Importer()->import();

    $whirlwind = Skill::forVersion($version->id)->where('sno_id', 206435)->sole();

    expect($whirlwind->name)->toBe('Whirlwind')
        ->and($whirlwind->class_name)->toBe('Barbarian')
        ->and($whirlwind->category)->toBe('core')
        ->and($whirlwind->raw['primary_tag'])->toBe('Skill_Primary_Core')
        ->and($whirlwind->raw['class_relative_category'])->toBe(13)
        ->and($whirlwind->description)->toStartWith('{c_label}Fury Cost:{/c_label}')
        ->and($whirlwind->max_rank)->toBe(15)
        ->and($whirlwind->tags)->toContain('Skill_Channeled', 'Skill_Whirlwind')
        ->and($whirlwind->tags)->not->toContain('Search_Physical')
        ->and($whirlwind->raw['search_tags'])->toContain('Search_Physical')
        ->and($whirlwind->is_released)->toBeTrue();

    $tornado = collect($whirlwind->enhancements)->firstWhere('mod_id', 6);

    expect($tornado['name'])->toBe('Tornado')
        ->and($tornado['description'])->toContain('{c_important}Whirlwind{/c} also creates');
});

test('the barbarian paragon board expands into an n by n grid with socket and gate nodes', function () {
    $version = d4Importer()->import();

    $board = ParagonBoard::forVersion($version->id)->sole();

    expect($board->name)->toBe('Start')
        ->and($board->class_name)->toBe('Barbarian')
        ->and($board->raw['width'])->toBe(21)
        ->and($board->grid)->toHaveCount(21);

    foreach ($board->grid as $row) {
        expect($row)->toHaveCount(21);
    }

    $cells = collect($board->grid)->flatten(1)->filter()->values();

    expect($cells)->toHaveCount(75)
        ->and($cells->firstWhere('has_socket', true)['key'])->toBe('Generic_Socket')
        ->and($cells->firstWhere('is_gate', true)['key'])->toBe('Generic_Gate')
        ->and($cells->firstWhere('key', 'Generic_Socket')['name'])->toBe('Glyph Socket')
        ->and($cells->firstWhere('key', 'Barbarian_Rare_015')['rarity'])->toBe('rare')
        ->and($cells->firstWhere('key', 'Generic_Normal_Str')['attributes'])->toBe(['Strength_Core']);
});

test('the glyph resolves its class and its affix effect text', function () {
    $version = d4Importer()->import();

    $glyph = ParagonGlyph::forVersion($version->id)->sole();

    expect($glyph->name)->toBe('Enchanter')
        ->and($glyph->class_name)->toBe('Sorcerer')
        ->and($glyph->effects)->toHaveCount(3);

    $bonus = collect($glyph->effects)->firstWhere('key', 'DamageBonus_Intelligence_Generic');

    expect($bonus['text'])->toContain('For every 5 Intelligence purchased within range')
        ->and($bonus['attribute_map'][0])->toBe([
            'source' => 'Intelligence_Core',
            'destination' => 'Damage_Percent_All_From_Skills',
        ]);
});

test('affixes take their text from the string list or the generic attribute template', function () {
    $version = d4Importer()->import();

    $legendary = Affix::forVersion($version->id)->where('key', 'legendary_barb_001')->sole();
    $crit = Affix::forVersion($version->id)->where('key', 'CritChance')->sole();
    $tempered = Affix::forVersion($version->id)
        ->where('key', 'Tempered_AttackSpeed_Sorc_Tag_Pyromancy_Tier1')
        ->sole();

    expect($legendary->name)->toBe('of Berserk Ripping')
        ->and($legendary->magic_type)->toBe('power')
        ->and($legendary->class_name)->toBe('Barbarian')
        ->and($legendary->temper_family)->toBe('Legendary')
        ->and($legendary->text)->toContain('bonus Bleeding damage')
        ->and($legendary->item_types)->toBe(['Axe']);

    expect($crit->magic_type)->toBe('stat')
        ->and($crit->class_name)->toBeNull()
        ->and($crit->temper_family)->toBe('Crit_Chance')
        ->and($crit->text)->toBe('+[{VALUE}*100|1%|] Critical Strike Chance')
        ->and($crit->value_range['source'])->toBe('formula')
        ->and($crit->value_range['formula_name'])->toBe('AffixCritChance')
        ->and($crit->value_range['ranges'])->toHaveCount(3);

    expect($tempered->is_tempering)->toBeTrue()
        ->and($tempered->class_name)->toBe('Sorcerer')
        ->and($tempered->temper_family)->toBe('AttackSpeed_Sorc_Tag_Pyromancy')
        ->and($tempered->text)->toContain('Attack Speed')
        ->and($tempered->value_range['formula_name'])->toBe('TemperedAffix_9%');
});

test('literal roll ranges are derived and richer formulas are kept as text', function () {
    $version = d4Importer()->import();

    $test = Affix::forVersion($version->id)->where('key', 'TEST_Legendary_Power1')->sole();
    $legendary = Affix::forVersion($version->id)->where('key', 'legendary_barb_001')->sole();

    expect($test->value_range)->toMatchArray([
        'source' => 'inline',
        'formula' => '5',
        'min' => 5.0,
        'max' => 5.0,
    ]);

    expect($legendary->value_range['formula'])->toBe('0.39+CurrentLegendaryRank()*0.01')
        ->and($legendary->value_range['min'])->toBeNull()
        ->and($legendary->value_range['max'])->toBeNull()
        ->and(collect($legendary->raw['attributes'])->pluck('attribute')->all())->toBe(['Affix_Value_1']);
});

test('an aspect borrows everything from the affix it points at', function () {
    $version = d4Importer()->import();

    $aspect = Aspect::forVersion($version->id)->sole();

    expect($aspect->name)->toBe('of Berserk Ripping')
        ->and($aspect->category)->toBe('offensive')
        ->and($aspect->text)->toContain('bonus Bleeding damage')
        ->and($aspect->item_types)->toBe(['Axe'])
        ->and($aspect->raw['affix'])->toBe('legendary_barb_001')
        ->and($aspect->raw['passive_power'])->toBe('legendary_barb_001')
        ->and($aspect->is_released)->toBeTrue();
});

test('a unique resolves its forced affixes into power text', function () {
    $version = d4Importer()->import();

    $unique = UniqueItem::forVersion($version->id)->where('sno_id', 356745)->sole();

    expect($unique->name)->toBe("Ancients' Oath")
        ->and($unique->class_name)->toBe('Barbarian')
        ->and($unique->item_type)->toBe('Axe2H')
        ->and($unique->is_mythic)->toBeFalse()
        ->and($unique->raw['base_item'])->toBe('2HAxe_Magic_Generic_001')
        ->and($unique->raw['item_families'])->toBe(['AndarielUniques'])
        ->and($unique->affixes)->toHaveCount(2);

    $power = collect($unique->affixes)->firstWhere('key', '2HAxe_Unique_Barb_001_x2');

    expect($power['text'])->toContain('Enemies hit by {c_important}Steel Grasp{/c}')
        ->and($unique->power_text)->toContain('Enemies hit by {c_important}Steel Grasp{/c}');

    // The second forced affix is outside the fixture slice, so it degrades to
    // a bare reference rather than dropping the unique.
    expect(collect($unique->affixes)->firstWhere('key', 'S04_Damage_All'))
        ->toMatchArray(['key' => 'S04_Damage_All', 'text' => null]);
});

test('item types keep their innate stats and weapon slot', function () {
    $version = d4Importer()->import();

    $axe = ItemType::forVersion($version->id)->where('sno_id', 446801)->sole();

    expect($axe->name)->toBe('Axe')
        ->and($axe->slot)->toBe('weapon')
        ->and($axe->implicits)->toHaveCount(1)
        ->and($axe->implicits[0]['attribute'])->toBe('Weapon_Speed_Percent_Bonus')
        ->and($axe->raw['usable_by_class'])->toContain('Barbarian', 'Druid')
        ->and($axe->is_released)->toBeTrue();
});

test('scratch content is imported but flagged unreleased', function () {
    $version = d4Importer()->import();

    expect(Affix::forVersion($version->id)->where('key', 'TEST_Legendary_Power1')->sole()->is_released)->toBeFalse()
        ->and(UniqueItem::forVersion($version->id)->where('name', 'VFX Testing Hat')->sole()->is_released)->toBeFalse()
        ->and(ItemType::forVersion($version->id)->where('name', 'Axe Bad Data')->sole()->is_released)->toBeFalse();

    expect(Affix::forVersion($version->id)->released()->count())->toBe(5)
        ->and(UniqueItem::forVersion($version->id)->released()->count())->toBe(1)
        ->and(ItemType::forVersion($version->id)->released()->count())->toBe(1)
        ->and(Skill::forVersion($version->id)->released()->count())->toBe(2);
});

test('re-importing the same version is idempotent', function () {
    $first = d4Importer()->import();

    $second = d4Importer();
    $version = $second->import();

    expect($version->id)->toBe($first->id)
        ->and($second->counts)->toBe(D4_FIXTURE_COUNTS)
        ->and(GameVersion::count())->toBe(1)
        ->and(Game::count())->toBe(1)
        ->and(Skill::count())->toBe(2)
        ->and(Affix::count())->toBe(6)
        ->and(ParagonBoard::count())->toBe(1)
        ->and(ItemType::count())->toBe(2);
});

test('importing a second version deactivates the first', function () {
    $first = d4Importer()->import('1.0.0');

    expect($first->is_active)->toBeTrue();

    $second = d4Importer()->import('2.0.0');

    expect($second->is_active)->toBeTrue()
        ->and($first->fresh()->is_active)->toBeFalse()
        ->and(GameVersion::count())->toBe(2)
        ->and(Skill::forVersion($first->id)->count())->toBe(2)
        ->and(Skill::forVersion($second->id)->count())->toBe(2);
});
