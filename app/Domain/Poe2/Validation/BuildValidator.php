<?php

namespace App\Domain\Poe2\Validation;

use App\Domain\Poe2\Poe2Context;
use App\Domain\Poe2\TreeGraph;
use App\Models\Poe2\Ascendancy;
use App\Models\Poe2\CharacterClass;
use App\Models\Poe2\Gem;
use App\Models\Poe2\PassiveNode;
use App\Models\Poe2\UniqueItem;

/**
 * Heuristic validation of a build definition against PoE2's hard rules.
 * This is not a calculation engine: it checks legality and budget constraints
 * (support gem rules, spirit reservation, resistance targets), not DPS.
 */
class BuildValidator
{
    /** @var list<string> */
    protected array $violations = [];

    /** @var list<string> */
    protected array $warnings = [];

    /** @var list<string> */
    protected array $suggestions = [];

    public function __construct(protected Poe2Context $context) {}

    /**
     * @param  array<string, mixed>  $build
     * @return array{valid: bool, violations: list<string>, warnings: list<string>, suggestions: list<string>}
     */
    public function validate(array $build): array
    {
        $this->violations = [];
        $this->warnings = [];
        $this->suggestions = [];

        $this->checkIdentity($build);
        $this->checkSkills($build);
        $this->checkSpiritBudget($build);
        $this->checkGear($build);
        $this->checkPassives($build);
        $this->checkDefences($build);

        return [
            'valid' => $this->violations === [],
            'violations' => $this->violations,
            'warnings' => $this->warnings,
            'suggestions' => $this->suggestions,
        ];
    }

    /** @param array<string, mixed> $build */
    protected function checkIdentity(array $build): void
    {
        $className = $build['class'] ?? null;
        $ascendancyName = $build['ascendancy'] ?? null;

        if ($className === null) {
            $this->warnings[] = 'No class specified.';

            return;
        }

        $class = CharacterClass::forVersion($this->context->versionId())
            ->whereLike('name', $className)
            ->first();

        if ($class === null) {
            $this->violations[] = "Unknown class \"{$className}\".";

            return;
        }

        if ($ascendancyName === null) {
            return;
        }

        $ascendancy = Ascendancy::forVersion($this->context->versionId())
            ->whereLike('name', $ascendancyName)
            ->first();

        if ($ascendancy === null) {
            $this->violations[] = "Unknown ascendancy \"{$ascendancyName}\".";
        } elseif (! str_starts_with(strtolower($ascendancy->class_name ?? ''), strtolower($class->name))) {
            $this->violations[] = "Ascendancy \"{$ascendancy->name}\" belongs to {$ascendancy->class_name}, not {$class->name}.";
        }
    }

    /** @param array<string, mixed> $build */
    protected function checkSkills(array $build): void
    {
        $skills = $build['skills'] ?? [];
        $supportUsage = [];

        foreach ($skills as $index => $setup) {
            $gemName = $setup['gem'] ?? null;
            $label = $gemName ?? "skill #{$index}";

            $gem = $gemName ? $this->findGem($gemName) : null;

            if ($gemName !== null && $gem === null) {
                $this->violations[] = "Unknown gem \"{$gemName}\".";
            } elseif ($gem !== null && $gem->gem_type === 'support') {
                $this->violations[] = "\"{$gem->name}\" is a support gem; it cannot be used as a main skill.";
            } elseif ($gem !== null && ! $gem->is_released) {
                $this->warnings[] = "\"{$gem->name}\" is not currently obtainable (unreleased).";
            }

            $supports = $setup['supports'] ?? [];

            if (count($supports) > 5) {
                $this->violations[] = "\"{$label}\" has ".count($supports).' support gems; the maximum is 5 (2 by default, up to 5 with Jeweller\'s Orbs).';
            } elseif (count($supports) > 2) {
                $this->suggestions[] = "\"{$label}\" uses ".count($supports).' support sockets; sockets 3-5 require Jeweller\'s Orbs (Lesser, Greater, Perfect).';
            }

            $skillTypes = $gem !== null
                ? collect($gem->skill_details)->flatMap(fn (array $s) => $s['types'] ?? [])->unique()->all()
                : [];

            foreach ($supports as $supportName) {
                $support = $this->findGem($supportName);

                if ($support === null) {
                    $this->violations[] = "Unknown support gem \"{$supportName}\" on \"{$label}\".";

                    continue;
                }

                if ($support->gem_type !== 'support') {
                    $this->violations[] = "\"{$support->name}\" is not a support gem (it is {$support->gem_type}).";

                    continue;
                }

                $supportUsage[$support->name][] = $label;

                if ($skillTypes !== [] && ! $this->supportAccepts($support, $skillTypes)) {
                    $this->violations[] = "\"{$support->name}\" cannot support \"{$label}\": its allowed skill types don't match the skill.";
                }
            }
        }

        // PoE2 hard rule: only one copy of each support gem across the whole build.
        foreach ($supportUsage as $supportName => $usedBy) {
            if (count($usedBy) > 1) {
                $this->violations[] = "Support gem \"{$supportName}\" is used on multiple skills (".implode(', ', $usedBy).'). PoE2 allows only ONE copy of each support gem per character.';
            }
        }
    }

    /** @param array<string, mixed> $build */
    protected function checkSpiritBudget(array $build): void
    {
        $available = $build['spirit_available'] ?? null;
        $reserved = 0;
        $reservers = [];

        foreach ($build['skills'] ?? [] as $setup) {
            $gem = isset($setup['gem']) ? $this->findGem($setup['gem']) : null;

            if ($gem === null) {
                continue;
            }

            foreach ($gem->skill_details as $skill) {
                $spirit = $skill['static']['reservations']['spirit'] ?? null;

                if ($spirit !== null) {
                    $reserved += $spirit;
                    $reservers[] = "{$gem->name} ({$spirit})";
                }
            }
        }

        if ($reserved === 0) {
            return;
        }

        if ($available === null) {
            $this->warnings[] = "Build reserves {$reserved} spirit (".implode(', ', $reservers).') but no spirit_available was provided; base spirit from the campaign is 100.';

            return;
        }

        if ($reserved > $available) {
            $this->violations[] = "Spirit over budget: {$reserved} reserved (".implode(', ', $reservers).") but only {$available} available.";
        } else {
            $this->suggestions[] = "Spirit budget: {$reserved}/{$available} reserved (".implode(', ', $reservers).').';
        }
    }

    /** @param array<string, mixed> $build */
    protected function checkPassives(array $build): void
    {
        $passives = $build['passives'] ?? [];
        $names = array_merge($passives['keystones'] ?? [], $passives['notables'] ?? []);

        foreach ($names as $name) {
            $node = PassiveNode::forVersion($this->context->versionId())
                ->whereLike('name', $name)
                ->whereIn('kind', ['keystone', 'notable'])
                ->whereNull('ascendancy_key')
                ->first();

            if ($node === null) {
                $this->violations[] = "Passive \"{$name}\" was not found as a keystone or notable on the tree.";
            }
        }

        $ascendancyNodes = $passives['ascendancy_nodes'] ?? [];

        if ($ascendancyNodes !== []) {
            $ascendancy = isset($build['ascendancy'])
                ? Ascendancy::forVersion($this->context->versionId())->whereLike('name', $build['ascendancy'])->first()
                : null;

            if ($ascendancy === null) {
                $this->violations[] = 'ascendancy_nodes were given but no valid ascendancy is set on the build.';
            } else {
                foreach ($ascendancyNodes as $name) {
                    $node = PassiveNode::forVersion($this->context->versionId())
                        ->whereLike('name', $name)
                        ->where('ascendancy_key', $ascendancy->key)
                        ->first();

                    if ($node === null) {
                        $this->violations[] = "Ascendancy passive \"{$name}\" was not found on {$ascendancy->name}'s ascendancy tree.";
                    }
                }
            }
        }

        $nodeIds = $passives['node_ids'] ?? [];
        $grantedNodes = $passives['granted_nodes'] ?? [];

        $this->checkGrantedNodes($grantedNodes, $build);

        if ($nodeIds !== []) {
            $allocated = PassiveNode::forVersion($this->context->versionId())
                ->whereIn('node_id', $nodeIds)
                ->get(['node_id', 'ascendancy_key']);

            $unknown = array_diff($nodeIds, $allocated->pluck('node_id')->all());

            if ($unknown !== []) {
                $this->violations[] = 'Unknown passive node ids: '.implode(', ', array_slice($unknown, 0, 10)).'. Use node_id values from search_passives.';
            }

            $ascendancyScoped = $allocated->whereNotNull('ascendancy_key')->pluck('node_id')->all();

            if ($ascendancyScoped !== []) {
                $this->violations[] = 'node_ids contains ascendancy tree nodes ('.implode(', ', array_slice($ascendancyScoped, 0, 10)).'); list ascendancy picks under passives.ascendancy_nodes instead.';
            }

            $this->checkTreeConnectivity(
                $build,
                array_values(array_diff($nodeIds, $unknown, $ascendancyScoped)),
                array_column($grantedNodes, 'node_id'),
            );
        }

        $level = $build['level'] ?? null;
        $pointsUsed = $passives['points_used'] ?? ($nodeIds !== [] ? count($nodeIds) : null);

        if ($level !== null && $pointsUsed !== null) {
            // Heuristic: roughly 1 point per level plus ~24 from quests/books.
            $budget = min((int) $level - 1, 99) + 24;

            if ($pointsUsed > $budget) {
                $this->warnings[] = "Passive points used ({$pointsUsed}) likely exceeds the budget at level {$level} (~{$budget} incl. quest rewards).";
            }
        }
    }

    /** @param array<string, mixed> $build */
    protected function checkDefences(array $build): void
    {
        $resistances = $build['resistances'] ?? null;

        if ($resistances === null) {
            return;
        }

        $tier = $build['content_tier'] ?? 'endgame';
        $target = $tier === 'campaign' ? 0 : 75;

        foreach (['fire', 'cold', 'lightning'] as $element) {
            $value = $resistances[$element] ?? null;

            if ($value !== null && $value < $target) {
                $this->warnings[] = ucfirst($element)." resistance {$value}% is below the {$target}% cap expected for {$tier} content.";
            }
        }

        if (($resistances['chaos'] ?? null) !== null && $resistances['chaos'] < 0 && $tier !== 'campaign') {
            $this->suggestions[] = "Chaos resistance is negative ({$resistances['chaos']}%); consider gearing some chaos res for endgame content.";
        }
    }

    /**
     * Granted nodes are allocated by out-of-tree mechanics (instilled amulets,
     * unique jewels like From Nothing, ascendancy mechanics like the Oracle's
     * Entwined Realities) and are exempt from pathing. Instilling only works
     * for notables.
     *
     * When the build carries structured gear/jewels, each granted node is
     * cross-checked against them: an instilled notable must match the worn
     * amulet's instill, and jewel grants require a unique jewel in the build.
     *
     * @param  list<array{node_id: int, source: string, detail?: string}>  $grantedNodes
     * @param  array<string, mixed>  $build
     */
    protected function checkGrantedNodes(array $grantedNodes, array $build): void
    {
        $gear = $build['gear'] ?? [];
        $jewels = $build['jewels'] ?? [];
        $hasStructuredGear = $gear !== [] || $jewels !== [];

        $instilledNotables = collect($gear)
            ->filter(fn (array $item) => ($item['slot'] ?? null) === 'amulet' && isset($item['instill']['notable']))
            ->map(fn (array $item) => strtolower($item['instill']['notable']))
            ->values();

        $uniqueJewelNames = collect($jewels)
            ->filter(fn (array $jewel) => ($jewel['rarity'] ?? null) === 'unique')
            ->map(fn (array $jewel) => strtolower($jewel['name']))
            ->values();

        $instillGrants = 0;

        foreach ($grantedNodes as $granted) {
            $node = PassiveNode::forVersion($this->context->versionId())
                ->where('node_id', $granted['node_id'])
                ->first();

            if ($node === null) {
                $this->violations[] = "Granted node id {$granted['node_id']} does not exist on the tree.";

                continue;
            }

            if ($granted['source'] === 'instilled_amulet') {
                $instillGrants++;

                if ($node->kind !== 'notable') {
                    $this->violations[] = "Granted node \"{$node->name}\" ({$granted['node_id']}) cannot come from an instilled amulet: instilling only allocates NOTABLE passives, and this is a {$node->kind}.";
                } elseif ($hasStructuredGear && ! $instilledNotables->contains(strtolower($node->name ?? ''))) {
                    $this->violations[] = "Granted node \"{$node->name}\" claims an instilled amulet, but no amulet in the build's gear has instill.notable set to it. Add the instill to the amulet gear entry.";
                }
            }

            if ($granted['source'] === 'unique_jewel' && $hasStructuredGear && $uniqueJewelNames->isEmpty()) {
                $this->violations[] = "Granted node \"{$node->name}\" claims a unique jewel, but the build's jewels list contains no unique jewel. Add it (e.g. From Nothing) to jewels.";
            }
        }

        if ($instillGrants > 1) {
            $this->violations[] = 'Only one notable can be granted by instilling: a character wears one amulet, and an instilled amulet grants exactly one notable.';
        }
    }

    /**
     * Structured gear sanity: one item per slot, unique names must exist,
     * instills only on amulets, unique jewels must exist as jewel uniques.
     *
     * @param  array<string, mixed>  $build
     */
    protected function checkGear(array $build): void
    {
        $gear = $build['gear'] ?? [];
        $jewels = $build['jewels'] ?? [];

        $slotCounts = [];

        foreach ($gear as $item) {
            $slot = $item['slot'] ?? 'unknown';
            $slotCounts[$slot] = ($slotCounts[$slot] ?? 0) + 1;

            if (isset($item['instill']) && $slot !== 'amulet') {
                $this->violations[] = "Gear in slot \"{$slot}\" has an instill — only amulets can be instilled.";
            }

            if (($item['rarity'] ?? null) === 'unique') {
                if (empty($item['name'])) {
                    $this->violations[] = "Unique gear in slot \"{$slot}\" has no name.";

                    continue;
                }

                $unique = UniqueItem::forVersion($this->context->versionId())
                    ->whereLike('name', $item['name'])
                    ->first();

                if ($unique === null) {
                    $this->violations[] = "Unknown unique item \"{$item['name']}\" in slot \"{$slot}\". Use search_uniques to find the right name.";
                }
            }
        }

        foreach ($slotCounts as $slot => $count) {
            if ($count > 1) {
                $this->violations[] = "Slot \"{$slot}\" has {$count} items; each slot holds one.";
            }
        }

        foreach ($jewels as $jewel) {
            if (($jewel['rarity'] ?? null) !== 'unique') {
                continue;
            }

            $unique = UniqueItem::forVersion($this->context->versionId())
                ->whereLike('name', $jewel['name'])
                ->first();

            if ($unique === null) {
                $this->violations[] = "Unknown unique jewel \"{$jewel['name']}\". Use search_uniques to find the right name.";
            } elseif ($unique->item_class !== null && stripos($unique->item_class, 'jewel') === false) {
                $this->violations[] = "\"{$jewel['name']}\" is a {$unique->item_class}, not a jewel.";
            }

            $socket = $jewel['socket_node_id'] ?? null;

            if ($socket !== null) {
                $node = PassiveNode::forVersion($this->context->versionId())
                    ->where('node_id', $socket)
                    ->first();

                if ($node === null || $node->kind !== 'jewel_socket') {
                    $this->violations[] = "Jewel \"{$jewel['name']}\" socket_node_id {$socket} is not a jewel socket on the tree.";
                }
            }
        }
    }

    /**
     * The game engine only lets you allocate nodes contiguously from the class
     * start. Nodes covered by granted_nodes (jewels, instills, ascendancy
     * mechanics) are exempt.
     *
     * @param  array<string, mixed>  $build
     * @param  list<int>  $nodeIds  known, non-ascendancy allocated ids
     * @param  list<int>  $grantedIds
     */
    protected function checkTreeConnectivity(array $build, array $nodeIds, array $grantedIds): void
    {
        if ($nodeIds === []) {
            return;
        }

        $graph = new TreeGraph($this->context);

        $adjacency = $graph->adjacency();

        $startNodeId = isset($build['class']) ? $graph->startNodeId($build['class']) : null;

        $allocated = array_flip($nodeIds);
        $granted = array_flip($grantedIds);

        // BFS from the class start (or from the first allocated node when the
        // start can't be resolved) across allocated nodes only.
        $queue = [];
        $reached = [];

        if ($startNodeId !== null) {
            foreach ($adjacency[$startNodeId] ?? [] as $neighbour) {
                if (isset($allocated[$neighbour]) && ! isset($reached[$neighbour])) {
                    $reached[$neighbour] = true;
                    $queue[] = $neighbour;
                }
            }
        } else {
            $this->warnings[] = 'Could not resolve the class start node; verified the allocation is one connected group but not its attachment to the start.';
            $first = $nodeIds[0];
            $reached[$first] = true;
            $queue[] = $first;
        }

        while ($queue !== []) {
            $current = array_shift($queue);

            foreach ($adjacency[$current] ?? [] as $neighbour) {
                if (isset($allocated[$neighbour]) && ! isset($reached[$neighbour])) {
                    $reached[$neighbour] = true;
                    $queue[] = $neighbour;
                }
            }
        }

        $disconnected = array_values(array_filter(
            $nodeIds,
            fn (int $id) => ! isset($reached[$id]) && ! isset($granted[$id]),
        ));

        if ($disconnected !== []) {
            $names = PassiveNode::forVersion($this->context->versionId())
                ->whereIn('node_id', array_slice($disconnected, 0, 8))
                ->get(['node_id', 'name'])
                ->map(fn ($n) => ($n->name ?: 'node')." ({$n->node_id})")
                ->join(', ');

            $this->violations[] = 'Passive allocation is not contiguous: '.count($disconnected)
                .' node(s) are not connected to the class start via allocated nodes: '.$names
                .'. The game requires sequential pathing — either add connecting travel nodes to node_ids, or declare how each detached node is allocated in passives.granted_nodes (instilled_amulet, unique_jewel, or ascendancy_mechanic).';
        }
    }

    protected function findGem(string $name): ?Gem
    {
        return Gem::forVersion($this->context->versionId())
            ->whereLike('name', $name)
            ->orderByDesc('is_released')
            ->first();
    }

    /** @param list<string> $skillTypes */
    protected function supportAccepts(Gem $support, array $skillTypes): bool
    {
        foreach ($support->skill_details as $skill) {
            $constraints = $skill['support_gem'] ?? null;

            if ($constraints === null) {
                continue;
            }

            $allowed = array_diff($constraints['allowed_types'] ?? [], ['AND', 'OR', 'NOT']);
            $excluded = array_diff($constraints['excluded_types'] ?? [], ['AND', 'OR', 'NOT']);

            if (array_intersect($excluded, $skillTypes) !== []) {
                return false;
            }

            if ($allowed === [] || array_intersect($allowed, $skillTypes) !== []) {
                return true;
            }
        }

        // No constraint data: don't block, the agent should verify manually.
        return true;
    }
}
