<?php

namespace App\Domain\D4\Validation;

use App\Domain\D4\D4Context;
use App\Domain\D4\D4ParagonGraph;
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
        $this->checkSkillPoints($build, $className);
        $this->checkParagon($build, $className);
        $this->checkGear($build, $className);
        $this->checkDefences($build);
        $this->checkMilestones($build);
        $this->checkComputedDisagreement($build);

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

    /**
     * The skill-point spends get the same scrutiny as the action bar: every
     * entry must be a real skill or passive, belong to the class, appear
     * once, and stay inside its rank cap where the import knows one.
     *
     * @param  array<string, mixed>  $build
     */
    protected function checkSkillPoints(array $build, ?string $className): void
    {
        $seen = [];

        foreach ($build['skill_points'] ?? [] as $index => $entry) {
            $name = is_array($entry) ? ($entry['skill'] ?? null) : null;

            if (! is_string($name) || $name === '') {
                continue;
            }

            $key = mb_strtolower($name);
            $seen[$key] = ($seen[$key] ?? 0) + 1;

            $skill = $this->findSkill($name);

            if ($skill === null) {
                $this->violations[] = "Unknown skill or passive \"{$name}\" in skill_points. Use search_skills to find the right name.";

                continue;
            }

            if (! $skill->is_released) {
                $this->warnings[] = "\"{$skill->name}\" (skill_points) is datamined but not live yet.";
            }

            if ($className !== null
                && $skill->class_name !== null
                && ! $this->sameName($skill->class_name, $className)) {
                $this->violations[] = "\"{$skill->name}\" (skill_points) is a {$skill->class_name} skill and cannot be taken by a {$className}.";
            }

            $points = is_array($entry) ? ($entry['points'] ?? null) : null;

            if (is_numeric($points) && $skill->max_rank > 0 && (int) $points > $skill->max_rank) {
                $this->warnings[] = "\"{$skill->name}\" (skill_points) lists {$points} points, above the imported maximum of {$skill->max_rank}.";
            }
        }

        foreach ($seen as $key => $count) {
            if ($count > 1) {
                $this->violations[] = "\"{$key}\" appears {$count} times in skill_points; list each skill or passive once with its total points.";
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
        $resolved = [];

        foreach ($paragon as $index => $entry) {
            $boardName = $entry['board'] ?? null;
            $resolved[$index] = null;

            if (is_string($boardName) && $boardName !== '') {
                $boardsSeen[mb_strtolower($boardName)] = ($boardsSeen[mb_strtolower($boardName)] ?? 0) + 1;

                // Board names repeat across classes (every class has a
                // "Start"), so the build's class breaks the tie.
                $candidates = ParagonBoard::forVersion($this->context->versionId())
                    ->whereLike('name', $boardName)
                    ->orderByDesc('is_released')
                    ->get();

                $board = ($className === null ? null : $candidates->first(
                    fn (ParagonBoard $candidate) => $candidate->class_name !== null
                        && $this->sameName($candidate->class_name, $className),
                )) ?? $candidates->first();

                if ($board === null) {
                    $this->violations[] = "Unknown paragon board \"{$boardName}\". Use get_paragon_board to list the boards for the class.";
                } elseif ($className !== null
                    && $board->class_name !== null
                    && ! $this->sameName($board->class_name, $className)) {
                    $this->violations[] = "Paragon board \"{$board->name}\" belongs to {$board->class_name}, not {$className}.";
                } else {
                    $resolved[$index] = $board;
                }
            }

            $this->checkGlyph($entry, $className);
        }

        foreach ($boardsSeen as $board => $count) {
            if ($count > 1) {
                $this->violations[] = "Paragon board \"{$board}\" is attached {$count} times; each board can only be attached once.";
            }
        }

        $this->checkParagonConnectivity($paragon, $resolved);
    }

    /**
     * Paragon allocation is a path, not a pick list: it enters the start board
     * through its single gate and every purchased node must connect back to it
     * 4-neighbour-wise, crossing onto later boards only through gate cells.
     * Entries without node data predate the path model and are legal forever —
     * they just skip these checks.
     *
     * @param  list<array<string, mixed>>  $paragon
     * @param  array<int, ParagonBoard|null>  $resolved
     */
    protected function checkParagonConnectivity(array $paragon, array $resolved): void
    {
        $graph = new D4ParagonGraph($this->context);
        $withNodes = array_filter($paragon, fn (array $entry) => ($entry['nodes'] ?? []) !== []);

        if ($withNodes === []) {
            $this->suggestions[] = 'No paragon entry lists allocated nodes, so path connectivity and paragon stat contributions were not checked. Use plan_paragon_path to compute routes and store them in paragon[].nodes.';

            $this->checkParagonNotables($paragon, $resolved, $graph, hasNodes: false);

            return;
        }

        $totalNodes = 0;

        foreach ($paragon as $index => $entry) {
            $nodes = $entry['nodes'] ?? [];
            $board = $resolved[$index] ?? null;
            $label = is_string($entry['board'] ?? null) ? $entry['board'] : 'board #'.$index;

            if ($nodes === [] || $board === null) {
                continue;
            }

            $totalNodes += count($nodes);
            $grid = is_array($board->grid) ? $board->grid : [];

            $offGrid = array_values(array_filter($nodes, fn (array $node) => $graph->cellAt($grid, $node['row'], $node['col']) === null));

            if ($offGrid !== []) {
                $samples = array_slice(array_map(fn (array $node) => "({$node['row']},{$node['col']})", $offGrid), 0, 4);
                $this->violations[] = 'Paragon board "'.$label.'" allocates '.count($offGrid)
                    .' cells that are empty space on its grid: '.implode(', ', $samples)
                    .'. Coordinates are 0-based pre-rotation row/col from get_paragon_board.';

                continue;
            }

            $seed = $this->paragonEntrySeed($graph, $grid, $entry, $index, $label);

            if ($seed === null) {
                continue;
            }

            $reachability = $graph->reachability($grid, $seed, $nodes);

            if ($reachability['unreached'] !== []) {
                $samples = array_slice(array_map(fn (array $node) => "({$node['row']},{$node['col']})", $reachability['unreached']), 0, 4);
                $this->violations[] = 'Paragon board "'.$label.'": '.count($reachability['unreached'])
                    .' allocated nodes do not connect back to the entry gate at ('.$seed['row'].','.$seed['col']
                    .'): '.implode(', ', $samples).'. Paragon nodes must form a contiguous path; use plan_paragon_path.';
            }

            $this->checkParagonSocket($graph, $grid, $entry, $nodes, $label);
        }

        // The paragon ladder grants a bounded pool of points; the exact pool
        // has moved between seasons, so overshooting is a warning, not a 422.
        if ($totalNodes > 300) {
            $this->warnings[] = "The paragon entries allocate {$totalNodes} nodes in total, above the 300 paragon points a maxed ladder grants.";
        }

        $this->checkParagonNotables($paragon, $resolved, $graph, hasNodes: true);
    }

    /**
     * Where a board's allocation enters: the single gate on the start board,
     * or the attach.gate the payload names on a later board.
     *
     * @param  list<list<array<string, mixed>|null>>  $grid
     * @param  array<string, mixed>  $entry
     * @return array{row: int, col: int}|null
     */
    protected function paragonEntrySeed(D4ParagonGraph $graph, array $grid, array $entry, int $index, string $label): ?array
    {
        if ($index === 0) {
            $seed = $graph->startNode($grid) ?? $graph->startGate($grid);

            if ($seed === null) {
                $this->violations[] = "The first paragon entry \"{$label}\" is not a start board (start boards carry the free starting node allocation grows from). Attach the class start board first.";
            }

            return $seed;
        }

        $attach = is_array($entry['attach'] ?? null) ? $entry['attach'] : [];
        $gate = is_array($attach['gate'] ?? null) ? $attach['gate'] : null;

        $to = $attach['to'] ?? null;

        if (is_numeric($to) && (int) $to >= $index) {
            $this->violations[] = "Paragon board \"{$label}\" attaches to entry #{$to}, which is not an earlier entry.";
        }

        if ($gate === null) {
            $this->warnings[] = "Paragon board \"{$label}\" lists nodes but no attach.gate, so its connectivity from the previous board was not checked.";

            return null;
        }

        $cell = $graph->cellAt($grid, (int) $gate['row'], (int) $gate['col']);

        if ($cell === null || ($cell['is_gate'] ?? false) !== true) {
            $this->violations[] = "Paragon board \"{$label}\": attach.gate ({$gate['row']},{$gate['col']}) is not a gate cell on this board.";

            return null;
        }

        return ['row' => (int) $gate['row'], 'col' => (int) $gate['col']];
    }

    /**
     * A socketed glyph only works when the allocation reaches the socket.
     *
     * @param  list<list<array<string, mixed>|null>>  $grid
     * @param  array<string, mixed>  $entry
     * @param  list<array{row: int, col: int}>  $nodes
     */
    protected function checkParagonSocket(D4ParagonGraph $graph, array $grid, array $entry, array $nodes, string $label): void
    {
        if (! is_string($entry['glyph'] ?? null) || $entry['glyph'] === '') {
            return;
        }

        foreach ($nodes as $node) {
            $cell = $graph->cellAt($grid, $node['row'], $node['col']);

            if ($cell !== null && ($cell['has_socket'] ?? false) === true) {
                return;
            }
        }

        $this->warnings[] = "Paragon board \"{$label}\" sockets \"{$entry['glyph']}\" but no allocated node is the glyph socket, so the glyph would be inactive.";
    }

    /**
     * Notables are the human-readable half of the allocation; each one should
     * exist on its board, and when node data is present, be covered by it.
     *
     * @param  list<array<string, mixed>>  $paragon
     * @param  array<int, ParagonBoard|null>  $resolved
     */
    protected function checkParagonNotables(array $paragon, array $resolved, D4ParagonGraph $graph, bool $hasNodes): void
    {
        foreach ($paragon as $index => $entry) {
            $board = $resolved[$index] ?? null;
            $notables = $entry['notables'] ?? [];

            if ($board === null || ! is_array($notables) || $notables === []) {
                continue;
            }

            $grid = is_array($board->grid) ? $board->grid : [];
            $nodes = $entry['nodes'] ?? [];

            $allocated = [];

            foreach (is_array($nodes) ? $nodes : [] as $node) {
                $allocated[$node['row'].','.$node['col']] = true;
            }

            foreach ($notables as $notable) {
                if (! is_string($notable) || trim($notable) === '') {
                    continue;
                }

                $cells = $graph->cellsNamed($grid, $notable);

                if ($cells === []) {
                    $this->warnings[] = "Notable \"{$notable}\" is not a node on paragon board \"{$board->name}\"; check the grid with get_paragon_board.";

                    continue;
                }

                if ($hasNodes && $allocated !== [] && array_intersect_key($cells, $allocated) === []) {
                    $this->warnings[] = "Notable \"{$notable}\" on board \"{$board->name}\" is not covered by the allocated nodes.";
                }
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
        $unstructuredSlots = [];

        foreach ($this->gearItems($build) as $slot => $item) {
            $this->checkItemAspect($slot, $item, $className, $aspectSlots);
            $this->checkItemUnique($slot, $item);
            $this->checkItemTempering($slot, $item);
            $this->checkItemAffixes($slot, $item, $unstructuredSlots);
        }

        foreach ($aspectSlots as $aspect => $slots) {
            if (count($slots) > 1) {
                $this->violations[] = "Aspect \"{$aspect}\" is imprinted on ".count($slots).' items ('
                    .implode(', ', $slots).'); each aspect can only be used once per character.';
            }
        }

        if ($unstructuredSlots !== []) {
            $this->warnings[] = count($unstructuredSlots).' gear item(s) ('.implode(', ', $unstructuredSlots)
                .') carry only unstructured affix text; those lines cannot contribute to the computed DPS/EHP. Use search_affixes and store {affix, value} entries.';
        }
    }

    /**
     * Structured affix entries resolve against the imported affix pool and
     * their rolled values sit inside the datamined roll range. Plain display
     * strings are legal but invisible to the calculator, which gets a single
     * summary warning per build.
     *
     * @param  array<string, mixed>  $item
     * @param  list<string>  $unstructuredSlots
     */
    protected function checkItemAffixes(string $slot, array $item, array &$unstructuredSlots): void
    {
        $affixes = $item['affixes'] ?? [];

        if (! is_array($affixes) || $affixes === []) {
            return;
        }

        $hasStructured = false;

        foreach ($affixes as $entry) {
            $key = is_array($entry) ? ($entry['affix'] ?? null) : null;

            if (! is_string($key) || $key === '') {
                continue;
            }

            $hasStructured = true;

            $affix = Affix::forVersion($this->context->versionId())
                ->where(fn (Builder $query) => $query
                    ->whereLike('key', $key)
                    ->orWhereLike('name', $key))
                ->orderByDesc('is_released')
                ->first();

            if ($affix === null) {
                $this->warnings[] = "Affix \"{$key}\" ({$slot}) does not resolve in the imported affix pool; check the key with search_affixes.";

                continue;
            }

            $this->checkAffixValue($slot, $affix, $entry['value'] ?? null);
        }

        if (! $hasStructured) {
            $unstructuredSlots[] = $slot;
        }
    }

    /**
     * The affix's stored roll range is evaluated at the top item-power
     * breakpoint. Some ranges are stored as fractions of the displayed
     * percentage, so a value is accepted when it fits either scale — this is
     * a plausibility net, not a calculator.
     */
    protected function checkAffixValue(string $slot, Affix $affix, mixed $value): void
    {
        if (! is_numeric($value)) {
            return;
        }

        $range = is_array($affix->value_range) ? $affix->value_range : [];
        $min = $range['min'] ?? null;
        $max = $range['max'] ?? null;

        if (! is_numeric($min) || ! is_numeric($max) || (float) $max <= 0.0) {
            return;
        }

        $value = (float) $value;
        $fitsRaw = $value >= (float) $min && $value <= (float) $max;
        $fitsPercent = $value >= (float) $min * 100 && $value <= (float) $max * 100;

        if (! $fitsRaw && ! $fitsPercent) {
            $label = $affix->name ?? $affix->key;
            $itemPower = $range['item_power'] ?? null;

            $this->warnings[] = "Affix \"{$label}\" ({$slot}) is listed at {$value}, outside the datamined roll range {$min}–{$max}"
                .($itemPower !== null ? " at item power {$itemPower}" : '').'.';
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

    /**
     * A hand-entered headline number that sits far from the calculator's
     * baseline deserves a second look — either the sheet reading is stale or
     * the structured build is missing the pieces that explain it.
     *
     * @param  array<string, mixed>  $build
     */
    protected function checkComputedDisagreement(array $build): void
    {
        $computed = is_array($build['computed'] ?? null) ? $build['computed'] : [];
        $wrote = (array) ($computed['wrote'] ?? []);

        foreach (['dps', 'ehp'] as $field) {
            $stated = $build[$field] ?? null;
            $baseline = $computed[$field] ?? null;

            if (! is_numeric($stated) || ! is_numeric($baseline)
                || in_array($field, $wrote, true)
                || (float) $baseline <= 0 || (float) $stated <= 0) {
                continue;
            }

            $ratio = (float) $stated / (float) $baseline;

            if ($ratio >= 5.0 || $ratio <= 0.2) {
                $this->warnings[] = 'The stated '.strtoupper($field)." ({$stated}) is more than 5x away from the computed baseline ({$baseline}). If it came off an in-game sheet, the structured build is probably missing the pieces that explain it.";
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
