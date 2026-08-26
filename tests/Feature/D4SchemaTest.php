<?php

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
use Illuminate\Support\Facades\Schema;

function d4Version(): GameVersion
{
    $game = Game::factory()->create(['slug' => 'diablo-4', 'name' => 'Diablo IV']);

    return GameVersion::create([
        'game_id' => $game->id,
        'version' => '1.4.0',
        'is_active' => true,
        'imported_at' => now(),
    ]);
}

test('the d4 migration creates every table', function () {
    $tables = [
        'd4_classes', 'd4_skills', 'd4_paragon_boards', 'd4_paragon_glyphs',
        'd4_affixes', 'd4_aspects', 'd4_uniques', 'd4_item_types',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue("missing table {$table}");
    }
});

test('d4 models round-trip their json columns and belong to a game version', function () {
    $version = d4Version();

    $skill = Skill::create([
        'game_version_id' => $version->id,
        'sno_id' => 123456,
        'name' => 'Hammer of the Ancients',
        'class_name' => 'Barbarian',
        'category' => 'core',
        'max_rank' => 5,
        'description' => 'Smash.',
        'tags' => ['physical', 'core'],
        'enhancements' => [['name' => 'Furious Hammer of the Ancients']],
        'raw' => ['snoId' => 123456],
    ]);

    $fresh = $skill->fresh();

    expect($fresh->tags)->toBe(['physical', 'core'])
        ->and($fresh->enhancements)->toBe([['name' => 'Furious Hammer of the Ancients']])
        ->and($fresh->raw)->toBe(['snoId' => 123456])
        ->and($fresh->is_released)->toBeTrue()
        ->and($fresh->gameVersion->id)->toBe($version->id);
});

test('the forVersion and released scopes filter d4 rows', function () {
    $version = d4Version();
    $otherVersion = GameVersion::create([
        'game_id' => $version->game_id,
        'version' => '1.5.0',
    ]);

    Skill::create([
        'game_version_id' => $version->id,
        'sno_id' => 1,
        'name' => 'Live Skill',
    ]);

    Skill::create([
        'game_version_id' => $version->id,
        'sno_id' => 2,
        'name' => 'PTR Skill',
        'is_released' => false,
    ]);

    Skill::create([
        'game_version_id' => $otherVersion->id,
        'sno_id' => 3,
        'name' => 'Next Patch Skill',
    ]);

    expect(Skill::forVersion($version->id)->count())->toBe(2)
        ->and(Skill::forVersion($version->id)->released()->pluck('name')->all())->toBe(['Live Skill'])
        ->and(Skill::released()->count())->toBe(2);
});

test('every d4 model writes to its own table', function () {
    $version = d4Version();

    $rows = [
        [CharacterClass::class, ['sno_id' => 10, 'name' => 'Barbarian', 'resource' => 'fury']],
        [Skill::class, ['sno_id' => 11, 'name' => 'Whirlwind']],
        [ParagonBoard::class, ['sno_id' => 12, 'name' => 'Blood Rage', 'grid' => [['x' => 0, 'y' => 0]]]],
        [ParagonGlyph::class, ['sno_id' => 13, 'name' => 'Might', 'effects' => ['+5% damage']]],
        [Aspect::class, ['sno_id' => 14, 'name' => 'Aspect of Might', 'value_range' => ['min' => 1, 'max' => 5]]],
        [UniqueItem::class, ['sno_id' => 15, 'name' => 'Doombringer', 'is_mythic' => true]],
        [ItemType::class, ['sno_id' => 16, 'name' => 'Two-Handed Sword', 'slot' => 'weapon']],
        [Affix::class, ['key' => 'affix_str_1', 'name' => 'Strength', 'text' => '+# Strength', 'is_tempering' => true]],
    ];

    foreach ($rows as [$model, $attributes]) {
        $record = $model::create($attributes + ['game_version_id' => $version->id]);

        expect($model::forVersion($version->id)->released()->whereKey($record->id)->exists())->toBeTrue();
    }

    expect(UniqueItem::forVersion($version->id)->sole()->is_mythic)->toBeTrue()
        ->and(Affix::forVersion($version->id)->where('is_tempering', true)->count())->toBe(1);
});
