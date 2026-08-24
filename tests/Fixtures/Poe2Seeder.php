<?php

namespace Tests\Fixtures;

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\Poe2\Ascendancy;
use App\Models\Poe2\CharacterClass;
use App\Models\Poe2\Gem;
use App\Models\Poe2\PassiveNode;
use App\Models\Poe2\UniqueItem;

/**
 * Seeds a tiny, hand-written slice of PoE2 data for feature tests.
 */
class Poe2Seeder
{
    public static function seed(): GameVersion
    {
        $game = Game::create(['slug' => 'poe2', 'name' => 'Path of Exile 2']);

        $version = GameVersion::create([
            'game_id' => $game->id,
            'version' => '0.5.2-test',
            'league' => 'Test League',
            'is_active' => true,
            'imported_at' => now(),
            'fingerprint' => 'test',
        ]);

        CharacterClass::create([
            'game_version_id' => $version->id,
            'metadata_id' => 'Metadata/Characters/Int/Int',
            'name' => 'Witch',
            'description' => 'A dark caster.',
            'base_stats' => ['strength' => 7, 'dexterity' => 7, 'intelligence' => 15],
        ]);

        CharacterClass::create([
            'game_version_id' => $version->id,
            'metadata_id' => 'Metadata/Characters/Dex/Dex',
            'name' => 'Ranger',
            'description' => 'A swift hunter.',
            'base_stats' => ['strength' => 7, 'dexterity' => 15, 'intelligence' => 7],
        ]);

        Ascendancy::create([
            'game_version_id' => $version->id,
            'key' => 'Witch1',
            'name' => 'Infernalist',
            'class_name' => 'Witch',
            'flavour_text' => 'Burn it all.',
        ]);

        Ascendancy::create([
            'game_version_id' => $version->id,
            'key' => 'Ranger1',
            'name' => 'Deadeye',
            'class_name' => 'Ranger',
            'flavour_text' => 'Never miss.',
        ]);

        Gem::create([
            'game_version_id' => $version->id,
            'metadata_id' => 'Metadata/Items/Gem/SkillGemSpark',
            'name' => 'Spark',
            'gem_type' => 'active',
            'color' => 'b',
            'is_released' => true,
            'description' => 'Launches unpredictable sparks.',
            'tags' => ['intelligence', 'spell', 'projectile', 'lightning'],
            'requirement_weights' => ['intelligence' => 100, 'strength' => 0, 'dexterity' => 0],
            'recommended_supports' => ['Metadata/Items/Gems/SupportGemPierce'],
            'granted_skills' => ['Spark'],
            'skill_details' => [[
                'key' => 'Spark',
                'display_name' => 'Spark',
                'description' => 'Launches unpredictable sparks.',
                'types' => ['Spell', 'Projectile', 'Damage', 'Lightning'],
                'weapon_restrictions' => [],
                'cast_time' => 700,
                'is_support' => false,
                'support_gem' => null,
                'static' => ['costs' => ['Mana' => 8]],
                'stat_sets' => [[
                    'id' => 'Spark',
                    'per_level' => [
                        '1' => ['stat_text' => ['a' => 'Deals 1 to 3 Lightning Damage']],
                        '20' => ['stat_text' => ['a' => 'Deals 40 to 120 Lightning Damage']],
                    ],
                    'static' => ['stat_text' => ['b' => 'Fires 3 Projectiles']],
                ]],
            ]],
        ]);

        Gem::create([
            'game_version_id' => $version->id,
            'metadata_id' => 'Metadata/Items/Gems/SupportGemPierce',
            'name' => 'Pierce',
            'gem_type' => 'support',
            'color' => 'g',
            'is_released' => true,
            'description' => 'Supports projectile skills.',
            'tags' => ['dexterity', 'support', 'projectile'],
            'skill_details' => [[
                'key' => 'SupportPierce',
                'display_name' => 'Pierce',
                'is_support' => true,
                'types' => [],
                'support_gem' => [
                    'allowed_types' => ['Projectile'],
                    'excluded_types' => [],
                    'supports_gems_only' => false,
                ],
                'static' => null,
                'stat_sets' => [],
            ]],
        ]);

        Gem::create([
            'game_version_id' => $version->id,
            'metadata_id' => 'Metadata/Items/Gems/SupportGemMeleeOnly',
            'name' => 'Heavy Swing',
            'gem_type' => 'support',
            'color' => 'r',
            'is_released' => true,
            'description' => 'Supports melee attacks.',
            'tags' => ['strength', 'support', 'attack'],
            'skill_details' => [[
                'key' => 'SupportHeavySwing',
                'display_name' => 'Heavy Swing',
                'is_support' => true,
                'types' => [],
                'support_gem' => [
                    'allowed_types' => ['Melee'],
                    'excluded_types' => ['Spell'],
                    'supports_gems_only' => false,
                ],
                'static' => null,
                'stat_sets' => [],
            ]],
        ]);

        Gem::create([
            'game_version_id' => $version->id,
            'metadata_id' => 'Metadata/Items/Gem/SkillGemArcticArmour',
            'name' => 'Arctic Armour',
            'gem_type' => 'active',
            'color' => 'b',
            'is_released' => true,
            'description' => 'A persistent icy buff.',
            'tags' => ['intelligence', 'spell', 'cold', 'persistent'],
            'skill_details' => [[
                'key' => 'ArcticArmour',
                'display_name' => 'Arctic Armour',
                'types' => ['Spell', 'Buff', 'Persistent'],
                'is_support' => false,
                'support_gem' => null,
                'static' => ['reservations' => ['spirit' => 30]],
                'stat_sets' => [],
            ]],
        ]);

        PassiveNode::create([
            'game_version_id' => $version->id,
            'node_id' => 1001,
            'name' => 'Chaos Inoculation',
            'kind' => 'keystone',
            'stats' => ['Maximum Life becomes 1', 'Immune to Chaos Damage'],
            'connections' => [],
        ]);

        PassiveNode::create([
            'game_version_id' => $version->id,
            'node_id' => 1002,
            'name' => 'Heightened Curses',
            'kind' => 'notable',
            'stats' => ['20% increased Curse Magnitudes'],
            'connections' => [],
        ]);

        PassiveNode::create([
            'game_version_id' => $version->id,
            'node_id' => 2001,
            'name' => 'Infernal Flame',
            'kind' => 'notable',
            'ascendancy_key' => 'Witch1',
            'stats' => ['Gain Infernal Flame'],
            'connections' => [],
        ]);

        UniqueItem::create([
            'game_version_id' => $version->id,
            'name' => 'Astramentis',
            'base_name' => 'Stellar Amulet',
            'item_class' => 'Amulet',
            'implicit_count' => 1,
            'variants' => ['Pre 0.2.0', 'Current'],
            'mods' => [
                ['text' => '+(5-7) to all Attributes', 'tags' => ['attribute'], 'variants' => null, 'is_implicit' => true],
                ['text' => '+(80-100) to all Attributes', 'tags' => ['attribute'], 'variants' => [1], 'is_implicit' => false],
                ['text' => '+(50-100) to all Attributes', 'tags' => ['attribute'], 'variants' => [2], 'is_implicit' => false],
            ],
            'source_text' => 'Astramentis',
        ]);

        return $version;
    }
}
