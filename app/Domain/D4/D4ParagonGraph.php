<?php

namespace App\Domain\D4;

use App\Models\D4\ParagonBoard;

/**
 * Graph operations over paragon board grids: adjacency, gate discovery and
 * path planning, mirroring what Poe2's TreeGraph does for the passive tree.
 *
 * A board grid is the row-major 2D array the importer stored — null cells are
 * empty space, adjacency is implicit 4-neighbour. Everything here works in
 * PRE-ROTATION coordinates: rotating a board never changes its internal
 * adjacency, and the payload names attachment gates by their pre-rotation
 * cell, so rotation only matters to the renderer.
 */
class D4ParagonGraph
{
    /** @var array<string, ParagonBoard|null> */
    protected array $boards = [];

    public function __construct(protected D4Context $context) {}

    public function board(string $name, ?string $className = null): ?ParagonBoard
    {
        $key = mb_strtolower($name.'|'.($className ?? ''));

        return $this->boards[$key] ??= ParagonBoard::forVersion($this->context->versionId())
            ->whereLike('name', $name)
            ->when($className, fn ($query) => $query->whereLike('class_name', $className))
            ->orderByDesc('is_released')
            ->first();
    }

    /**
     * The gate cells of a grid, in pre-rotation coordinates.
     *
     * @param  list<list<array<string, mixed>|null>>  $grid
     * @return list<array{row: int, col: int}>
     */
    public function gates(array $grid): array
    {
        $gates = [];

        foreach ($grid as $row => $cells) {
            foreach ($cells as $col => $cell) {
                if (is_array($cell) && ($cell['is_gate'] ?? false) === true) {
                    $gates[] = ['row' => $row, 'col' => $col];
                }
            }
        }

        return $gates;
    }

    /**
     * The single gate a start board exits through toward its first attached
     * board. Start boards are the only boards with exactly one gate;
     * attachable boards carry one per edge.
     *
     * @param  list<list<array<string, mixed>|null>>  $grid
     * @return array{row: int, col: int}|null
     */
    public function startGate(array $grid): ?array
    {
        $gates = $this->gates($grid);

        return count($gates) === 1 ? $gates[0] : null;
    }

    /**
     * The character's free starting cell on a class start board — the
     * `StartNode*` node allocation grows outward from. Only start boards
     * carry one.
     *
     * @param  list<list<array<string, mixed>|null>>  $grid
     * @return array{row: int, col: int}|null
     */
    public function startNode(array $grid): ?array
    {
        foreach ($grid as $row => $cells) {
            foreach ($cells as $col => $cell) {
                if (is_array($cell) && str_starts_with(mb_strtolower((string) ($cell['key'] ?? '')), 'startnode')) {
                    return ['row' => $row, 'col' => $col];
                }
            }
        }

        return null;
    }

    /**
     * @param  list<list<array<string, mixed>|null>>  $grid
     * @return array<string, mixed>|null
     */
    public function cellAt(array $grid, int $row, int $col): ?array
    {
        $cell = $grid[$row][$col] ?? null;

        return is_array($cell) ? $cell : null;
    }

    /**
     * Which of the allocated cells are reachable from the seed cell walking
     * 4-neighbour steps that only cross allocated cells (the seed itself needs
     * no allocation — a gate is walkable once the previous board reaches it).
     *
     * @param  list<list<array<string, mixed>|null>>  $grid
     * @param  array{row: int, col: int}  $seed
     * @param  list<array{row: int, col: int}>  $allocated
     * @return array{reached: list<array{row: int, col: int}>, unreached: list<array{row: int, col: int}>}
     */
    public function reachability(array $grid, array $seed, array $allocated): array
    {
        $allocatedSet = [];

        foreach ($allocated as $node) {
            $allocatedSet[$node['row'].','.$node['col']] = $node;
        }

        $visited = [];
        $frontier = [[$seed['row'], $seed['col']]];
        $visited[$seed['row'].','.$seed['col']] = true;

        while ($frontier !== []) {
            [$row, $col] = array_pop($frontier);

            foreach ([[$row - 1, $col], [$row + 1, $col], [$row, $col - 1], [$row, $col + 1]] as [$nextRow, $nextCol]) {
                $key = $nextRow.','.$nextCol;

                if (isset($visited[$key]) || ! isset($allocatedSet[$key])) {
                    continue;
                }

                if ($this->cellAt($grid, $nextRow, $nextCol) === null) {
                    continue;
                }

                $visited[$key] = true;
                $frontier[] = [$nextRow, $nextCol];
            }
        }

        $reached = [];
        $unreached = [];

        foreach ($allocatedSet as $key => $node) {
            if (isset($visited[$key])) {
                $reached[] = $node;
            } else {
                $unreached[] = $node;
            }
        }

        return ['reached' => $reached, 'unreached' => $unreached];
    }

    /**
     * Plan a contiguous allocation on one board reaching every target from the
     * entry gate, greedy-Steiner the way TreeGraph::planFrom routes the
     * passive tree: repeatedly connect the closest unreached target to the
     * already-allocated set via BFS over non-null cells.
     *
     * Targets are cell coordinates or node names ("Blood Rage", "Glyph
     * Socket"); a name matches every cell carrying it, and reaching one of
     * them satisfies the target.
     *
     * @param  list<list<array<string, mixed>|null>>  $grid
     * @param  list<array{row: int, col: int}|string>  $targets
     * @param  array{row: int, col: int}|null  $from  entry gate; null falls back to the board's single gate
     * @return array{
     *     nodes: list<array{row: int, col: int}>,
     *     points_used: int,
     *     paths: list<array{target: string, points_added: int, route: list<array{row: int, col: int}>}>,
     *     unreachable: list<string>,
     * }|null null when no entry point can be resolved
     */
    public function plan(array $grid, array $targets, ?array $from = null): ?array
    {
        $from ??= $this->startGate($grid);

        if ($from === null || $this->cellAt($grid, $from['row'], $from['col']) === null) {
            return null;
        }

        $remaining = [];

        foreach ($targets as $target) {
            if (is_array($target)) {
                $row = $target['row'] ?? null;
                $col = $target['col'] ?? null;

                if (is_numeric($row) && is_numeric($col)) {
                    $key = (int) $row.','.(int) $col;
                    $remaining[$key] = $this->cellAt($grid, (int) $row, (int) $col) !== null ? [$key => true] : [];
                }

                continue;
            }

            if (! is_string($target) || trim($target) === '') {
                continue;
            }

            $cells = $this->cellsNamed($grid, $target);

            if ($cells !== []) {
                $remaining[$target] = $cells;
            } else {
                $remaining[$target] = [];
            }
        }

        $allocated = [$from['row'].','.$from['col'] => true];
        $paths = [];
        $unreachable = [];

        foreach ($remaining as $label => $candidates) {
            if ($candidates === []) {
                $unreachable[] = (string) $label;
            }
        }

        $remaining = array_filter($remaining, fn (array $candidates) => $candidates !== []);

        while ($remaining !== []) {
            [$label, $route] = $this->closestTarget($grid, $allocated, $remaining);

            if ($label === null) {
                $unreachable = array_merge($unreachable, array_map(strval(...), array_keys($remaining)));
                break;
            }

            foreach ($route as $key) {
                $allocated[$key] = true;
            }

            $paths[] = [
                'target' => (string) $label,
                'points_added' => count($route),
                'route' => array_map($this->coordinate(...), $route),
            ];

            unset($remaining[$label]);
        }

        unset($allocated[$from['row'].','.$from['col']]);

        return [
            'nodes' => array_map($this->coordinate(...), array_keys($allocated)),
            'points_used' => count($allocated),
            'paths' => $paths,
            'unreachable' => $unreachable,
        ];
    }

    /**
     * BFS outward from the allocated set until the nearest remaining target
     * (any candidate cell of any target) is found.
     *
     * @param  list<list<array<string, mixed>|null>>  $grid
     * @param  array<string, true>  $allocated
     * @param  array<array-key, array<string, true>>  $remaining
     * @return array{0: array-key|null, 1: list<string>}
     */
    protected function closestTarget(array $grid, array $allocated, array $remaining): array
    {
        // A target already inside the allocated set costs nothing.
        foreach ($remaining as $label => $candidates) {
            foreach ($candidates as $key => $true) {
                if (isset($allocated[$key])) {
                    return [$label, []];
                }
            }
        }

        $visited = $allocated;
        $frontier = array_keys($allocated);
        $parent = [];

        while ($frontier !== []) {
            $next = [];

            foreach ($frontier as $currentKey) {
                [$row, $col] = array_map(intval(...), explode(',', $currentKey));

                foreach ([[$row - 1, $col], [$row + 1, $col], [$row, $col - 1], [$row, $col + 1]] as [$nextRow, $nextCol]) {
                    $key = $nextRow.','.$nextCol;

                    if (isset($visited[$key]) || $this->cellAt($grid, $nextRow, $nextCol) === null) {
                        continue;
                    }

                    $visited[$key] = true;
                    $parent[$key] = $currentKey;
                    $next[] = $key;

                    foreach ($remaining as $label => $candidates) {
                        if (isset($candidates[$key])) {
                            $route = [];
                            $cursor = $key;

                            while (! isset($allocated[$cursor])) {
                                $route[] = $cursor;
                                $cursor = $parent[$cursor];
                            }

                            return [$label, array_reverse($route)];
                        }
                    }
                }
            }

            $frontier = $next;
        }

        return [null, []];
    }

    /**
     * Every cell whose node name matches, keyed "row,col".
     *
     * @param  list<list<array<string, mixed>|null>>  $grid
     * @return array<string, true>
     */
    public function cellsNamed(array $grid, string $name): array
    {
        $needle = mb_strtolower(trim($name));
        $cells = [];

        foreach ($grid as $row => $rowCells) {
            foreach ($rowCells as $col => $cell) {
                if (! is_array($cell)) {
                    continue;
                }

                $cellName = mb_strtolower(trim((string) ($cell['name'] ?? '')));
                $cellKey = mb_strtolower(trim((string) ($cell['key'] ?? '')));

                if ($cellName === $needle || $cellKey === $needle) {
                    $cells[$row.','.$col] = true;
                }
            }
        }

        return $cells;
    }

    /**
     * @return array{row: int, col: int}
     */
    protected function coordinate(string $key): array
    {
        [$row, $col] = array_map(intval(...), explode(',', $key));

        return ['row' => $row, 'col' => $col];
    }
}
