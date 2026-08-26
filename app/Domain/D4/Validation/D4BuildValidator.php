<?php

namespace App\Domain\D4\Validation;

use App\Domain\D4\D4Context;
use App\Models\D4\Affix;
use App\Models\D4\Aspect;
use App\Models\D4\CharacterClass;
use App\Models\D4\ParagonBoard;
use App\Models\D4\ParagonGlyph;
use App\Models\D4\Skill;
use App\Models\D4\UniqueItem;
use Illuminate\Database\Eloquent\Builder;

/**
 * Heuristic validation of a Diablo IV build definition against the game's hard
 * rules, mirroring the PoE2 BuildValidator's contract exactly: same return
 * shape, same severity conventions.
 *
 * This is not a damage calculator. It checks legality (does the skill exist,
 * does the board belong to the class, is the aspect claimed twice) and budget
 * (resistance caps), not numbers. Every check is resilient to missing imported
 * data: an empty table produces warnings, never violations, because a partial
 * import must not make every build look illegal.
 */
class D4BuildValidator
{
    /**
     * Resistances cap at 70% and can be pushed to 85% with max-resistance
     * bonuses from paragon and gear.
     */
    public const RESISTANCE_CAP = 70;

    public const RESISTANCE_CAP_MAX = 85;

    /** @var list<string> */
    protected array $violations = [];

    /** @var list<string> */
    protected array $warnings = [];

    /** @var list<string> */
    protected array $suggestions = [];

    public function __construct(protected D4Context $context) {}

    /**
     * @param  array<string, mixed>  $build
     * @return array{valid: bool, violations: list<string>, warnings: list<string>, suggestions: list<string>}
     */
    public function validate(array $build): array
    {
        $this->violations = [];
        $this->warnings = [];
        $this->suggestions = [];

        $className = $this->checkIdentity($build);
        $this->checkSkills($build, $className);
        $this->checkParagon($build, $className);
        $this->checkGear($build, $className);
        $this->checkDefences($build);
        $this->checkMilestones($build);

        return [
            'valid' => $this->violations === [],
            'violations' => $this->violations,
            'warnings' => $this->warnings,
            'suggestions' => $this->suggestions,
        ];
    }

    /**
     * @param  array<string, mixed>  $build
     * @return string|null the canonical class name, when it resolves
     */
    protected function checkIdentity(array $build): ?string
    {
        $className = $build['class'] ?? null;

        if (! is_string($className) || $className === '') {
            $this->warnings[] = 'No class specified; skills, paragon boards and class-restricted aspects cannot be checked.';

            return null;
        }

        $class = CharacterClass::forVersion($this->context->versionId())
            ->whereLike('name', $className)
            ->first();

        if ($class === null) {
            // The request rules already restrict `class` to the eight real
            // classes, so a miss here means the import is thin, not that the
            // build is illegal.
            $this->warnings[] = "Class \"{$className}\" is not in the imported class list for this patch; class-specific checks were skipped.";

            return null;
        }

        return $class->name;
    }

    /**
     * @param  array<string, mixed>  $build
     */
    protected function checkSkills(array $build, ?string $className): void
    {
        $skills = $build['equipped_skills'] ?? [];

        if (count($skills) > D4BuildRules::MAX_EQUIPPED_SKILLS) {
            $this->violations[] = 'The build equips '.count($skills).' skills; the action bar holds '
                .D4BuildRules::MAX_EQUIPPED_SKILLS.'.';
        }

        $seen = [];

        foreach ($skills as $index => $setup) {
            $name = $setup['skill'] ?? null;
            $label = is_string($name) && $name !== '' ? $name : 'skill #'.$index;

            if (! is_string($name) || $name === '') {
                continue;
            }

            $key = mb_strtolower($name);
            $seen[$key] = ($seen[$key] ?? 0) + 1;

            $skill = $this->findSkill($name);

            if ($skill === null) {
                $this->violations[] = "Unknown skill \"{$name}\". Use search_skills to find the right name.";

                continue;
            }

            if (! $skill->is_released) {
                $this->warnings[] = "\"{$skill->name}\" is datamined but not live yet.";
            }

            if ($className !== null
                && $skill->class_name !== null
                && ! $this->sameName($skill->class_name, $className)) {
                $this->violations[] = "\"{$skill->name}\" is a {$skill->class_name} skill and cannot be equipped by a {$className}.";
            }

            $this->checkSkillRank($skill, $setup);
            $this->checkSkillModifiers($skill, $setup);
        }

        foreach ($seen as $key => $count) {
            if ($count > 1) {
                $this->violations[] = "Skill \"{$key}\" is equipped {$count} times; each skill occupies one action bar slot.";
            }
        }
    }

    /** @param array<string, mixed> $setup */
    protected function checkSkillRank(Skill $skill, array $setup): void
    {
        $rank = $setup['rank'] ?? null;

        if (! is_numeric($rank) || $skill->max_rank <= 0) {
            return;
        }

        if ((int) $rank > $skill->max_rank) {
            $this->warnings[] = "\"{$skill->name}\" is listed at rank {$rank}, above the imported maximum of {$skill->max_rank} (5 from the tree plus gear ranks).";
        }
    }

    /**
     * Modifier pairs and variant nodes replaced the old enhancement/upgrade
     * system, but the datamined names still come from the old `enhancements`
     * list, so an unrecognised name is a warning: the build is probably fine
     * and the name probably just moved.
     *
     * @param  array<string, mixed>  $setup
     */
    protected function checkSkillModifiers(Skill $skill, array $setup): void
    {
        $modifiers = $setup['modifiers'] ?? [];

        if (! is_array($modifiers) || $modifiers === []) {
            return;
        }

        $known = collect($skill->enhancements)
            ->pluck('name')
            ->filter(fn (mixed $name) => is_string($name) && $name !== '')
            ->map(fn (string $name) => mb_strtolower($name))
            ->all();

        if ($known === []) {
            return;
        }

        foreach ($modifiers as $modifier) {
            if (! is_string($modifier) || $modifier === '') {
                continue;
            }

            if (! in_array(mb_strtolower($modifier), $known, true)) {
                $this->warnings[] = "Skill modifier \"{$modifier}\" is not one of the modifiers datamined for \"{$skill->name}\"; check the name with get_skill.";
            }
        }
    }

    /**
     * @param  array<string, mixed>  $build
     */
    protected function checkParagon(array $build, ?string $className): void
    {
        $paragon = $build['paragon'] ?? [];

        if ($paragon === []) {
            return;
        }

        $boardsSeen = [];

        foreach ($paragon as $entry) {
            $boardName = $entry['board'] ?? null;

            if (is_string($boardName) && $boardName !== '') {
                $boardsSeen[mb_strtolower($boardName)] = ($boardsSeen[mb_strtolower($boardName)] ?? 0) + 1;

                $board = ParagonBoard::forVersion($this->context->versionId())
                    ->whereLike('name', $boardName)
                    ->orderByDesc('is_released')
                    ->first();

                if ($board === null) {
                    $this->violations[] = "Unknown paragon board \"{$boardName}\". Use get_paragon_board to list the boards for the class.";
                } elseif ($className !== null
                    && $board->class_name !== null
                    && ! $this->sameName($board->class_name, $className)) {
                    $this->violations[] = "Paragon board \"{$board->name}\" belongs to {$board->class_name}, not {$className}.";
                }
            }

            $this->checkGlyph($entry, $className);
        }

        foreach ($boardsSeen as $board => $count) {
            if ($count > 1) {
                $this->violations[] = "Paragon board \"{$board}\" is attached {$count} times; each board can only be attached once.";
            }
        }
    }

    /** @param array<string, mixed> $entry */
    protected function checkGlyph(array $entry, ?string $className): void
    {
        $glyphName = $entry['glyph'] ?? null;

        if (! is_string($glyphName) || $glyphName === '') {
            return;
        }

        $glyph = ParagonGlyph::forVersion($this->context->versionId())
            ->whereLike('name', $glyphName)
            ->orderByDesc('is_released')
            ->first();

        if ($glyph === null) {
            $this->violations[] = "Unknown paragon glyph \"{$glyphName}\". Use search_glyphs to find the right name.";

            return;
        }

        if ($className !== null
            && $glyph->class_name !== null
            && ! $this->sameName($glyph->class_name, $className)) {
            $this->violations[] = "Glyph \"{$glyph->name}\" belongs to {$glyph->class_name}, not {$className}.";
        }
    }

    /**
     * Gear legality: aspects exist and are usable by the class, no aspect is
     * claimed twice, unique names resolve, tempered affixes are real tempering
     * recipes.
     *
     * @param  array<string, mixed>  $build
     */
    protected function checkGear(array $build, ?string $className): void
    {
        $aspectSlots = [];

        foreach ($this->gearItems($build) as $slot => $item) {
            $this->checkItemAspect($slot, $item, $className, $aspectSlots);
            $this->checkItemUnique($slot, $item);
            $this->checkItemTempering($slot, $item);
        }

        foreach ($aspectSlots as $aspect => $slots) {
            if (count($slots) > 1) {
                $this->violations[] = "Aspect \"{$aspect}\" is imprinted on ".count($slots).' items ('
                    .implode(', ', $slots).'); each aspect can only be used once per character.';
            }
        }
    }

    /**
     * Every equipped item, keyed by a human-readable slot label. Weapons are a
     * list, so they get numbered labels.
     *
     * @param  array<string, mixed>  $build
     * @return array<string, array<string, mixed>>
     */
    protected function gearItems(array $build): array
    {
        $gear = $build['gear'] ?? [];

        if (! is_array($gear)) {
            return [];
        }

        $items = [];

        foreach ($gear as $slot => $item) {
            if ($slot === 'weapons') {
                foreach (is_array($item) ? $item : [] as $index => $weapon) {
                    if (is_array($weapon)) {
                        $items['weapon '.($index + 1)] = $weapon;
                    }
                }

                continue;
            }

            if (is_array($item)) {
                $items[(string) $slot] = $item;
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, list<string>>  $aspectSlots
     */
    protected function checkItemAspect(string $slot, array $item, ?string $className, array &$aspectSlots): void
    {
        $aspectName = $item['aspect'] ?? null;

        if (! is_string($aspectName) || $aspectName === '') {
            return;
        }

        $aspectSlots[$aspectName][] = $slot;

        $aspect = Aspect::forVersion($this->context->versionId())
            ->whereLike('name', $aspectName)
            ->orderByDesc('is_released')
            ->first();

        if ($aspect === null) {
            $this->violations[] = "Unknown aspect \"{$aspectName}\" on {$slot}. Use search_aspects to find the right name.";

            return;
        }

        // The aspects table has no class column: the class an aspect is
        // restricted to lives on the importer's raw.class_name, and generic
        // aspects leave it empty.
        $aspectClass = $aspect->raw['class_name'] ?? null;

        if ($className !== null
            && is_string($aspectClass)
            && $aspectClass !== ''
            && ! $this->sameName($aspectClass, $className)) {
            $this->violations[] = "Aspect \"{$aspect->name}\" ({$slot}) is a {$aspectClass} aspect and cannot be imprinted by a {$className}.";
        }

        $itemTypes = is_array($aspect->item_types) ? $aspect->item_types : [];

        if ($itemTypes !== []) {
            $this->suggestions[] = "Aspect \"{$aspect->name}\" can only be imprinted on: ".implode(', ', $itemTypes).'.';
        }
    }

    /**
     * Datamined unique names differ from the in-game display names often
     * enough that an unknown name is a warning, not a violation.
     *
     * @param  array<string, mixed>  $item
     */
    protected function checkItemUnique(string $slot, array $item): void
    {
        $rarity = $item['rarity'] ?? null;

        if (! in_array($rarity, ['unique', 'mythic'], true)) {
            return;
        }

        $name = $item['name'] ?? null;

        if (! is_string($name) || $name === '') {
            $this->violations[] = "The {$rarity} item in slot \"{$slot}\" has no name.";

            return;
        }

        $unique = UniqueItem::forVersion($this->context->versionId())
            ->whereLike('name', $name)
            ->orderByDesc('is_released')
            ->first();

        if ($unique === null) {
            $this->warnings[] = "Unique item \"{$name}\" ({$slot}) is not in the imported unique list — the datamined name may differ from the in-game one. Check it with search_uniques.";

            return;
        }

        if ($rarity === 'mythic' && ! $unique->is_mythic) {
            $this->warnings[] = "\"{$unique->name}\" ({$slot}) is listed as mythic but the data has it as an ordinary unique.";
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function checkItemTempering(string $slot, array $item): void
    {
        $tempered = $item['tempered'] ?? [];

        if (! is_array($tempered) || $tempered === []) {
            return;
        }

        if (count($tempered) > 1) {
            $this->warnings[] = "The item in slot \"{$slot}\" lists ".count($tempered)
                .' tempered affixes; an item carries one tempered affix per tempering manual recipe.';
        }

        foreach ($tempered as $entry) {
            $affix = is_array($entry) ? ($entry['affix'] ?? null) : $entry;

            if (! is_string($affix) || $affix === '') {
                continue;
            }

            $exists = Affix::forVersion($this->context->versionId())
                ->where('is_tempering', true)
                ->where(fn (Builder $query) => $query
                    ->whereLike('name', $affix)
                    ->orWhereLike('key', $affix)
                    ->orWhereLike('temper_family', $affix))
                ->exists();

            if (! $exists) {
                $this->warnings[] = "Tempered affix \"{$affix}\" ({$slot}) is not a known tempering recipe; check it with search_affixes (is_tempering).";
            }
        }
    }

    /**
     * @param  array<string, mixed>  $build
     */
    protected function checkDefences(array $build): void
    {
        $resistances = $build['resistances'] ?? null;

        if (! is_array($resistances) || $resistances === []) {
            return;
        }

        $tier = $build['content_tier'] ?? 'endgame';
        $target = $tier === 'leveling' ? 0 : self::RESISTANCE_CAP;

        foreach (D4BuildRules::RESISTANCES as $element) {
            $value = $resistances[$element] ?? null;

            if (! is_numeric($value)) {
                continue;
            }

            $value = (int) $value;

            if ($value > self::RESISTANCE_CAP_MAX) {
                $this->violations[] = ucfirst($element)." resistance {$value}% is above the ".self::RESISTANCE_CAP_MAX
                    .'% hard ceiling; no amount of max-resistance bonuses goes past it.';
            } elseif ($value > self::RESISTANCE_CAP) {
                $this->warnings[] = ucfirst($element)." resistance {$value}% is above the ".self::RESISTANCE_CAP
                    .'% armoury cap; it only applies with enough max-resistance bonuses to raise the cap that far.';
            } elseif ($value < $target) {
                $this->warnings[] = ucfirst($element)." resistance {$value}% is below the {$target}% cap expected for {$tier} content.";
            }
        }

        $armor = $build['armor'] ?? null;

        if (is_numeric($armor) && (int) $armor === 0) {
            $this->warnings[] = 'Armor is listed as 0; armor is the main physical mitigation stat and caps damage reduction against every hit type.';
        }
    }

    /**
     * @param  array<string, mixed>  $build
     */
    protected function checkMilestones(array $build): void
    {
        $level = $build['level'] ?? null;

        if (! is_numeric($level)) {
            return;
        }

        foreach ($build['milestones'] ?? [] as $milestone) {
            $milestoneLevel = $milestone['level'] ?? null;

            if (is_numeric($milestoneLevel) && (int) $milestoneLevel > (int) $level) {
                $this->warnings[] = "Leveling milestone at level {$milestoneLevel} is past the build's target level {$level}.";
            }
        }
    }

    protected function findSkill(string $name): ?Skill
    {
        return Skill::forVersion($this->context->versionId())
            ->whereLike('name', $name)
            ->orderByDesc('is_released')
            ->first();
    }

    protected function sameName(string $left, string $right): bool
    {
        return mb_strtolower(trim($left)) === mb_strtolower(trim($right));
    }
}
