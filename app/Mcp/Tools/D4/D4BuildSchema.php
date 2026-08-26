<?php

namespace App\Mcp\Tools\D4;

use App\Domain\D4\Validation\D4BuildRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;

/**
 * The LLM-facing description of a Diablo IV build payload, shared by
 * save_build (nested under `build`) and validate_build (at the top level) so
 * the two tools can never drift apart.
 *
 * This is the mirror of D4BuildRules: every property here has a matching rule
 * there and vice versa. Change them together or the tools advertise a shape
 * the request rejects.
 */
class D4BuildSchema
{
    /**
     * @return array<string, mixed>
     */
    public static function properties(JsonSchema $schema): array
    {
        return array_merge(
            self::identity($schema),
            self::skills($schema),
            self::paragon($schema),
            self::gear($schema),
            self::characterPower($schema),
            self::presentation($schema),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected static function identity(JsonSchema $schema): array
    {
        return [
            'class' => $schema->string()->enum(D4BuildRules::CLASSES)->description('Character class.'),
            'level' => $schema->integer()->description('Target character level, 1-'.D4BuildRules::MAX_LEVEL.' (the level cap).'),
            'armor' => $schema->integer()->description('Total armor as shown on the character sheet. Armor is the main physical mitigation stat in Diablo IV.'),
            'resistances' => $schema->object([
                'fire' => $schema->integer(),
                'cold' => $schema->integer(),
                'lightning' => $schema->integer(),
                'poison' => $schema->integer(),
                'shadow' => $schema->integer(),
            ])->description('The five elemental resistances as percentages. They cap at 70% and only go higher (to 85%) with max-resistance bonuses.'),
            'content_tier' => $schema->string()->enum(D4BuildRules::CONTENT_TIERS)->description('Content the build targets: "leveling", "endgame" (Nightmare Dungeons, Helltides, Infernal Hordes) or "pit_push" (high Pit tiers).'),
            'stage' => $schema->string()->enum(['leveling', 'mapping', 'endgame', 'bossing'])->description('The game-agnostic stage tag used by the site\'s build hub.'),
            'tier' => $schema->string()->enum(D4BuildRules::TIERS)->description('How strong the build is relative to the current meta.'),
            'dps' => $schema->integer()->description('Headline damage per second, as a plain number.'),
            'ehp' => $schema->integer()->description('Effective hit points, as a plain number.'),
            'hardcore_viable' => $schema->boolean()->description('Whether the build is viable in hardcore.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function skills(JsonSchema $schema): array
    {
        return [
            'equipped_skills' => $schema->array()->max(D4BuildRules::MAX_EQUIPPED_SKILLS)->items(
                $schema->object([
                    'skill' => $schema->string()->required()->description('Exact skill name from search_skills, e.g. "Whirlwind".'),
                    'rank' => $schema->integer()->description('Total skill rank including gear ranks, 1-'.D4BuildRules::MAX_SKILL_RANK.' (5 from the skill tree plus "+X to Skill" affixes).'),
                    'role' => $schema->string()->description('What this skill does in play, e.g. "Main damage" or "Mobility".'),
                    'modifiers' => $schema->array()->max(4)->items($schema->string())->description('The skill modifier choices taken on this skill (the modifier pairs and variant node on its tree page), by name.'),
                    'reported' => $schema->string()->description('Free-form reported numbers for this skill, e.g. "8.2M per tick with 12 stacks".'),
                ]),
            )->description('The skills on the action bar. Diablo IV equips exactly six; this is the only required part of a build.')->required(),
            'skill_points' => $schema->array()->max(60)->items(
                $schema->object([
                    'skill' => $schema->string()->required()->description('Skill or passive name on the class skill tree.'),
                    'points' => $schema->integer()->description('Points invested, e.g. 3.'),
                ]),
            )->description('Where the skill tree points go, including passives that are not on the action bar.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function paragon(JsonSchema $schema): array
    {
        return [
            'paragon' => $schema->array()->max(D4BuildRules::MAX_PARAGON_BOARDS)->items(
                $schema->object([
                    'board' => $schema->string()->required()->description('Paragon board name from get_paragon_board, e.g. "Start", "Bone Graft".'),
                    'rotation' => $schema->integer()->enum([0, 90, 180, 270])->description('How the board is rotated when attached, in degrees.'),
                    'glyph' => $schema->string()->description('The glyph socketed in this board, by name from search_glyphs.'),
                    'glyph_level' => $schema->integer()->description('The glyph\'s level, which sets its effect radius.'),
                    'notables' => $schema->array()->max(20)->items($schema->string())->description('Notable/legendary nodes taken on this board.'),
                ]),
            )->description('The paragon boards attached, in attachment order starting from the class start board.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function gear(JsonSchema $schema): array
    {
        $item = fn (string $description) => $schema->object([
            'name' => $schema->string()->description('Item name: the unique/mythic name (validated), or a label for a rare or legendary.'),
            'item_type' => $schema->string()->description('Base item type, e.g. "Two-Handed Axe", "Amulet".'),
            'rarity' => $schema->string()->enum(D4BuildRules::RARITIES),
            'aspect' => $schema->string()->description('The legendary aspect imprinted on this item, by name from search_aspects. Each aspect can only be used once per character.'),
            'affixes' => $schema->array()->max(8)->items($schema->string())->description('The affixes rolled on the item, as displayed.'),
            'greater_affixes' => $schema->integer()->description('How many of the affixes are Greater Affixes (0-4).'),
            'tempered' => $schema->array()->max(2)->items(
                $schema->object([
                    'affix' => $schema->string()->required()->description('The tempered affix or its tempering family, from search_affixes with is_tempering.'),
                    'tier' => $schema->integer()->description('The tempering recipe tier.'),
                ]),
            )->description('Tempered affixes added with a tempering manual.'),
            'masterwork_level' => $schema->integer()->description('Masterworking level, 0-12. Levels 4, 8 and 12 each critically upgrade one affix.'),
            'runes' => $schema->array()->max(2)->items($schema->string())->description('The runeword in this item\'s sockets: a Ritual (condition) rune and an Invocation (effect) rune.'),
        ])->description($description);

        $slots = [];

        foreach (D4BuildRules::GEAR_SLOTS as $slot) {
            $slots[$slot] = $item('The '.str_replace('_', ' ', $slot).' slot.');
        }

        $slots['weapons'] = $schema->array()->max(D4BuildRules::MAX_WEAPONS)->items(
            $item('One equipped weapon.'),
        )->description('Equipped weapons as a list rather than fixed slots: how many a character carries depends on the class and loadout (a Barbarian arsenal holds four, a Sorcerer a weapon plus a focus).');

        return [
            'gear' => $schema->object($slots)->description('Equipped gear, keyed by slot. Every slot is optional.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function characterPower(JsonSchema $schema): array
    {
        return [
            'seasonal_power' => $schema->string()->description('The seasonal mechanic power the build leans on, if any. The imported data carries no season name, so only set this from what the user told you.'),
            'mercenary' => $schema->object([
                'hired' => $schema->string()->description('The hired mercenary who fights alongside the character.'),
                'reinforcement' => $schema->string()->description('The reinforcement mercenary called in on a trigger.'),
            ])->description('The mercenary pairing.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function presentation(JsonSchema $schema): array
    {
        return [
            'milestones' => $schema->array()->max(12)->items(
                $schema->object([
                    'level' => $schema->integer()->required()->description('Character level this happens at, 1-'.D4BuildRules::MAX_LEVEL.'.'),
                    'text' => $schema->string()->required()->description('What to do or swap to at that level.'),
                ]),
            )->description('Leveling milestones, shown as a timeline on the overview tab.'),
            'stats' => $schema->object([
                'offence' => $schema->array()->max(8)->items(
                    $schema->object([
                        'label' => $schema->string()->required()->description('e.g. "Total DPS".'),
                        'value' => $schema->string()->required()->description('e.g. "8.4M" — display text, units included.'),
                    ]),
                ),
                'defence' => $schema->array()->max(8)->items(
                    $schema->object([
                        'label' => $schema->string()->required()->description('e.g. "Effective HP".'),
                        'value' => $schema->string()->required()->description('e.g. "412k".'),
                    ]),
                ),
            ])->description('The offence and defence tables on the overview tab. Report what the tooling actually shows; do not invent numbers.'),
            'how_it_plays' => $schema->array()->max(3)->items($schema->string())->description('Up to three sentences on the moment-to-moment rotation.'),
            'works_because' => $schema->array()->max(4)->items($schema->string())->description('Up to four reasons the build holds together.'),
            'watch_out_for' => $schema->array()->max(4)->items($schema->string())->description('Up to four honest limitations.'),
        ];
    }
}
