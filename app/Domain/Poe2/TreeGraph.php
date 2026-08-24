<?php

namespace App\Domain\Poe2;

use App\Models\Poe2\CharacterClass;
use App\Models\Poe2\PassiveNode;

/**
 * Graph operations over the main passive tree: adjacency, class start
 * resolution, and path planning. Used by the build validator (connectivity)
 * and the plan_tree_path MCP tool (routing).
 */
class TreeGraph
{
    public function __construct(protected Poe2Context $context) {}

    /**
     * Undirected adjacency for the whole main tree, cached per game version.
     *
     * @return array<int, list<int>>
     */
    public function adjacency(): array
    {
        static $cache = [];

        $versionId = $this->context->versionId();

        if (isset($cache[$versionId])) {
            return $cache[$versionId];
        }

        $adjacency = [];

        PassiveNode::forVersion($versionId)
            ->whereNull('ascendancy_key')
            ->get(['node_id', 'connections'])
            ->each(function ($node) use (&$adjacency) {
                foreach ($node->connections as $target) {
                    $target = (int) $target;
                    $adjacency[$node->node_id][] = $target;
                    $adjacency[$target][] = $node->node_id;
                }
            });

        return $cache[$versionId] = $adjacency;
    }

    public function startNodeId(string $className): ?int
    {
        $versionId = $this->context->versionId();

        // Primary: the start_classes tag stamped onto class_start nodes at
        // import time (importers from 2026-08-24 onward).
        $tagged = PassiveNode::forVersion($versionId)
            ->where('kind', 'class_start')
            ->whereJsonContains('raw->start_classes', $className)
            ->value('node_id');

        if ($tagged !== null) {
            return $tagged;
        }

        // Fallback for data imported before the tag existed: the class's
        // integer_id (from the characters export) matches the tree's
        // classStartIndex values — both index the same game-data class table.
        $classIndex = CharacterClass::forVersion($versionId)
            ->whereLike('name', $className)
            ->first()
            ?->raw['integer_id'] ?? null;

        if ($classIndex === null) {
            return null;
        }

        return PassiveNode::forVersion($versionId)
            ->where('kind', 'class_start')
            ->get(['node_id', 'raw'])
            ->first(fn ($node) => in_array($classIndex, (array) ($node->raw['classStartIndex'] ?? []), true))
            ?->node_id;
    }

    /**
     * Plan a contiguous allocation reaching every target from the class start,
     * using a greedy Steiner approximation: repeatedly connect the closest
     * unreached target to the already-allocated set via BFS.
     *
     * @param  list<int>  $targetIds
     * @return array{
     *     node_ids: list<int>,
     *     points_used: int,
     *     paths: list<array{target: int, points_added: int, route: list<int>}>,
     *     unreachable: list<int>,
     * }|null null when the class start cannot be resolved
     */
    public function plan(string $className, array $targetIds): ?array
    {
        $start = $this->startNodeId($className);

        if ($start === null) {
            return null;
        }

        return $this->planFrom($start, $targetIds, $this->adjacency());
    }

    /**
     * Adjacency within one ascendancy's cluster (their connections are stored
     * on the nodes just like main-tree edges).
     *
     * @return array<int, list<int>>
     */
    public function ascendancyAdjacency(string $ascendancyKey): array
    {
        $adjacency = [];

        PassiveNode::forVersion($this->context->versionId())
            ->where('ascendancy_key', $ascendancyKey)
            ->get(['node_id', 'connections'])
            ->each(function ($node) use (&$adjacency) {
                foreach ($node->connections as $target) {
                    $target = (int) $target;
                    $adjacency[$node->node_id][] = $target;
                    $adjacency[$target][] = $node->node_id;
                }
            });

        return $adjacency;
    }

    public function ascendancyStartId(string $ascendancyKey): ?int
    {
        return PassiveNode::forVersion($this->context->versionId())
            ->where('ascendancy_key', $ascendancyKey)
            ->where('kind', 'ascendancy_start')
            ->value('node_id');
    }

    /**
     * Plan a contiguous allocation within an ascendancy cluster, starting
     * from its (free) ascendancy start node.
     *
     * @param  list<int>  $targetIds
     * @return array{node_ids: list<int>, points_used: int, paths: list<array{target: int, points_added: int, route: list<int>}>, unreachable: list<int>}|null
     */
    public function planAscendancy(string $ascendancyKey, array $targetIds): ?array
    {
        $start = $this->ascendancyStartId($ascendancyKey);

        if ($start === null) {
            return null;
        }

        return $this->planFrom($start, $targetIds, $this->ascendancyAdjacency($ascendancyKey));
    }

    /**
     * @param  list<int>  $targetIds
     * @param  array<int, list<int>>  $adjacency
     * @return array{node_ids: list<int>, points_used: int, paths: list<array{target: int, points_added: int, route: list<int>}>, unreachable: list<int>}
     */
    protected function planFrom(int $start, array $targetIds, array $adjacency): array
    {

        $allocated = [$start => true];
        $remaining = array_values(array_unique($targetIds));
        $paths = [];
        $unreachable = [];

        while ($remaining !== []) {
            $best = null;

            // BFS outward from the allocated set until the nearest remaining
            // target is found; ties resolve to whichever BFS reaches first.
            $visited = $allocated;
            $frontier = array_keys($allocated);
            $parent = [];

            $found = null;

            while ($frontier !== [] && $found === null) {
                $next = [];

                foreach ($frontier as $current) {
                    foreach ($adjacency[$current] ?? [] as $neighbour) {
                        if (isset($visited[$neighbour])) {
                            continue;
                        }

                        $visited[$neighbour] = true;
                        $parent[$neighbour] = $current;
                        $next[] = $neighbour;

                        if (in_array($neighbour, $remaining, true)) {
                            $found = $neighbour;
                            break 2;
                        }
                    }
                }

                $frontier = $next;
            }

            if ($found === null) {
                $unreachable = array_merge($unreachable, $remaining);
                break;
            }

            // Walk back to the allocated set to collect the route.
            $route = [];
            $cursor = $found;

            while (! isset($allocated[$cursor])) {
                $route[] = $cursor;
                $cursor = $parent[$cursor];
            }

            $route = array_reverse($route);

            foreach ($route as $nodeId) {
                $allocated[$nodeId] = true;
            }

            $paths[] = [
                'target' => $found,
                'points_added' => count($route),
                'route' => $route,
            ];

            $remaining = array_values(array_diff($remaining, [$found]));
        }

        unset($allocated[$start]);

        return [
            'node_ids' => array_keys($allocated),
            'points_used' => count($allocated),
            'paths' => $paths,
            'unreachable' => $unreachable,
        ];
    }
}
