<?php

namespace App\Domain\D4\Validation;

use App\Domain\Builds\BuildStage;

/**
 * The request-validation rules for a structured Diablo IV build payload,
 * shared by the D4 validate_build and save_build MCP tools.
 *
 * These rules and D4SaveBuildTool::schema() / D4ValidateBuildTool::schema()
 * describe the same payload and must be changed together. Everything except
 * `equipped_skills` is optional: the MCP writes a partial build and a human
 * finishes it in the web editor.
 *
 * Where a cap is volatile (masterworking levels, glyph levels, resistances)
 * these rules stay deliberately permissive and D4BuildValidator reports the
 * game-accurate limit as a violation or warning, rather than 422-ing a payload
 * the model can still fix.
 */
class D4BuildRules
{
    /**
     * The tier taxonomy is shared with PoE2 but deliberately duplicated: there
     * is no game-agnostic home for it yet, and the D4 rules must not depend on
     * the PoE2 namespace.
     *
     * @var list<string>
     */
    public const TIERS = ['S', 'A', 'B', 'C'];

    /**
     * Level cap after the Lord of Hatred expansion.
     */
    public const MAX_LEVEL = 70;

    /**
     * Six skills fit on the action bar. This is a hard game rule, so it is
     * enforced here as well as reported by the validator.
     */
    public const MAX_EQUIPPED_SKILLS = 6;

    /**
     * Skill ranks are 5 from the tree plus gear ranks; 15 is the practical
     * gear-inclusive ceiling.
     */
    public const MAX_SKILL_RANK = 15;

    /**
     * A character attaches the starting board plus up to eight more.
     */
    public const MAX_PARAGON_BOARDS = 10;

    /** @var list<string> */
    public const CLASSES = [
        'Barbarian',
        'Druid',
        'Necromancer',
        'Paladin',
        'Rogue',
        'Sorcerer',
        'Spiritborn',
        'Warlock',
    ];

    /** @var list<string> */
    public const CONTENT_TIERS = ['leveling', 'endgame', 'pit_push'];

    /** @var list<string> */
    public const RESISTANCES = ['fire', 'cold', 'lightning', 'poison', 'shadow'];

    /** @var list<string> */
    public const RARITIES = ['common', 'magic', 'rare', 'legendary', 'unique', 'mythic'];

    /**
     * The single-item gear slots. Weapons are a separate flexible list: how
     * many weapons a character carries depends on the class (the Barbarian's
     * arsenal holds four, a Sorcerer holds one plus a focus).
     *
     * @var list<string>
     */
    public const GEAR_SLOTS = [
        'helm',
        'chest',
        'gloves',
        'pants',
        'boots',
        'amulet',
        'ring_1',
        'ring_2',
    ];

    public const MAX_WEAPONS = 4;

    /** @return array<string, mixed> */
    public static function rules(string $prefix = ''): array
    {
        return array_merge(
            self::identityRules($prefix),
            self::skillRules($prefix),
            self::paragonRules($prefix),
            self::gearRules($prefix),
            self::characterPowerRules($prefix),
            self::presentationRules($prefix),
        );
    }

    /** @return array<string, mixed> */
    protected static function identityRules(string $prefix): array
    {
        $rules = [
            $prefix.'class' => 'nullable|string|in:'.implode(',', self::CLASSES),
            $prefix.'level' => 'nullable|integer|min:1|max:'.self::MAX_LEVEL,
            $prefix.'armor' => 'nullable|integer|min:0|max:100000',
            $prefix.'resistances' => 'nullable|array',
            $prefix.'content_tier' => 'nullable|string|in:'.implode(',', self::CONTENT_TIERS),
            $prefix.'stage' => 'nullable|string|in:'.implode(',', BuildStage::values()),
            $prefix.'tier' => 'nullable|string|in:'.implode(',', self::TIERS),
            $prefix.'dps' => 'nullable|integer|min:0',
            $prefix.'ehp' => 'nullable|integer|min:0',
            $prefix.'hardcore_viable' => 'nullable|boolean',
        ];

        // Resistances cap at 70% and can be pushed to 85% with max-resistance
        // bonuses. The bounds here only reject nonsense; D4BuildValidator
        // reports anything over the cap.
        foreach (self::RESISTANCES as $element) {
            $rules[$prefix.'resistances.'.$element] = 'nullable|integer|min:-100|max:100';
        }

        return $rules;
    }

    /** @return array<string, mixed> */
    protected static function skillRules(string $prefix): array
    {
        return [
            $prefix.'equipped_skills' => 'required|array|min:1|max:'.self::MAX_EQUIPPED_SKILLS,
            $prefix.'equipped_skills.*.skill' => 'required|string|max:100',
            $prefix.'equipped_skills.*.rank' => 'nullable|integer|min:1|max:'.self::MAX_SKILL_RANK,
            $prefix.'equipped_skills.*.role' => 'nullable|string|max:60',
            // Modifier pairs and variant nodes replaced the old
            // enhancement/upgrade system and the datamined names lag the
            // display names, so these are free text validated leniently and
            // only warned about by the validator.
            $prefix.'equipped_skills.*.modifiers' => 'nullable|array|max:4',
            $prefix.'equipped_skills.*.modifiers.*' => 'string|max:120',
            $prefix.'equipped_skills.*.reported' => 'nullable|string|max:300',
            $prefix.'skill_points' => 'nullable|array|max:60',
            $prefix.'skill_points.*.skill' => 'required|string|max:100',
            $prefix.'skill_points.*.points' => 'nullable|integer|min:0|max:'.self::MAX_SKILL_RANK,
        ];
    }

    /** @return array<string, mixed> */
    protected static function paragonRules(string $prefix): array
    {
        return [
            $prefix.'paragon' => 'nullable|array|max:'.self::MAX_PARAGON_BOARDS,
            $prefix.'paragon.*.board' => 'required|string|max:100',
            $prefix.'paragon.*.rotation' => 'nullable|integer|in:0,90,180,270',
            $prefix.'paragon.*.glyph' => 'nullable|string|max:100',
            // Glyph levels have been raised twice already; stay permissive and
            // let the validator report an implausible level.
            $prefix.'paragon.*.glyph_level' => 'nullable|integer|min:1|max:200',
            // Allocated cells in pre-rotation grid coordinates. Board widths
            // vary per board (21 today), so the bounds stay permissive and the
            // validator checks against the real grid.
            $prefix.'paragon.*.nodes' => 'nullable|array|max:441',
            $prefix.'paragon.*.nodes.*.row' => 'required|integer|min:0|max:40',
            $prefix.'paragon.*.nodes.*.col' => 'required|integer|min:0|max:40',
            // Which earlier entry this board hangs off and through which of its
            // own gate cells (pre-rotation coordinates). The start board omits it.
            $prefix.'paragon.*.attach' => 'nullable|array',
            $prefix.'paragon.*.attach.to' => 'nullable|integer|min:0|max:'.(self::MAX_PARAGON_BOARDS - 1),
            $prefix.'paragon.*.attach.gate' => 'nullable|array',
            $prefix.'paragon.*.attach.gate.row' => 'required_with:'.$prefix.'paragon.*.attach.gate|integer|min:0|max:40',
            $prefix.'paragon.*.attach.gate.col' => 'required_with:'.$prefix.'paragon.*.attach.gate|integer|min:0|max:40',
            $prefix.'paragon.*.notables' => 'nullable|array|max:20',
            $prefix.'paragon.*.notables.*' => 'string|max:100',
        ];
    }

    /** @return array<string, mixed> */
    protected static function gearRules(string $prefix): array
    {
        $rules = [
            $prefix.'gear' => 'nullable|array',
            $prefix.'gear.weapons' => 'nullable|array|max:'.self::MAX_WEAPONS,
        ];

        foreach (self::GEAR_SLOTS as $slot) {
            $rules[$prefix.'gear.'.$slot] = 'nullable|array';
            $rules += self::itemRules($prefix.'gear.'.$slot);
        }

        $rules += self::itemRules($prefix.'gear.weapons.*');

        return $rules;
    }

    /**
     * One equipped item. Used for every fixed slot and for each entry in the
     * weapons list.
     *
     * @return array<string, mixed>
     */
    protected static function itemRules(string $path): array
    {
        return [
            $path.'.name' => 'nullable|string|max:120',
            $path.'.item_type' => 'nullable|string|max:60',
            $path.'.rarity' => 'nullable|string|in:'.implode(',', self::RARITIES),
            $path.'.aspect' => 'nullable|string|max:120',
            $path.'.affixes' => 'nullable|array|max:8',
            // A display string (legacy) or a structured {text, affix, value,
            // greater} object the calculator can count.
            $path.'.affixes.*' => [new Rules\AffixEntry],
            $path.'.greater_affixes' => 'nullable|integer|min:0|max:4',
            // One tempered affix per item today; two is allowed here because
            // the recipe count has moved before and the validator reports the
            // current limit instead of 422-ing.
            $path.'.tempered' => 'nullable|array|max:2',
            $path.'.tempered.*.affix' => 'required|string|max:150',
            $path.'.tempered.*.tier' => 'nullable|integer|min:1|max:20',
            $path.'.tempered.*.value' => 'nullable|numeric',
            $path.'.masterwork_level' => 'nullable|integer|min:0|max:12',
            // Two runes make a runeword: one condition, one effect.
            $path.'.runes' => 'nullable|array|max:2',
            $path.'.runes.*' => 'string|max:100',
        ];
    }

    /** @return array<string, mixed> */
    protected static function characterPowerRules(string $prefix): array
    {
        return [
            $prefix.'seasonal_power' => 'nullable|string|max:200',
            $prefix.'mercenary' => 'nullable|array',
            $prefix.'mercenary.hired' => 'nullable|string|max:100',
            $prefix.'mercenary.reinforcement' => 'nullable|string|max:100',
        ];
    }

    /** @return array<string, mixed> */
    protected static function presentationRules(string $prefix): array
    {
        return [
            $prefix.'milestones' => 'nullable|array|max:12',
            $prefix.'milestones.*.level' => 'required|integer|min:1|max:'.self::MAX_LEVEL,
            $prefix.'milestones.*.text' => 'required|string|max:300',
            $prefix.'stats' => 'nullable|array',
            $prefix.'stats.offence' => 'nullable|array|max:8',
            $prefix.'stats.offence.*.label' => 'required|string|max:60',
            $prefix.'stats.offence.*.value' => 'required|string|max:60',
            $prefix.'stats.defence' => 'nullable|array|max:8',
            $prefix.'stats.defence.*.label' => 'required|string|max:60',
            $prefix.'stats.defence.*.value' => 'required|string|max:60',
            $prefix.'how_it_plays' => 'nullable|array|max:3',
            $prefix.'how_it_plays.*' => 'string|max:300',
            $prefix.'works_because' => 'nullable|array|max:4',
            $prefix.'works_because.*' => 'string|max:300',
            $prefix.'watch_out_for' => 'nullable|array|max:4',
            $prefix.'watch_out_for.*' => 'string|max:300',
        ];
    }
}
