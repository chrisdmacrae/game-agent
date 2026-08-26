<?php

namespace App\Mcp\Tools\D4;

use App\Domain\D4\D4Context;
use App\Domain\D4\D4ParagonGraph;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class PlanParagonPathTool extends Tool
{
    protected string $name = 'plan_paragon_path';

    protected string $description = 'Compute a legal, contiguous paragon allocation. Give the class and, per board in attachment order, the targets to reach (node names like "Blood Rage" or "Glyph Socket", or {row, col} cells) and the server routes a 4-neighbour path from each board\'s entry gate, returning ready-to-use paragon[].nodes coordinates plus per-target point costs. ALWAYS use this to build nodes — never hand-pick cells, the game requires sequential pathing from the start board\'s gate. On every board that leads to another board, include the gate you exit through as a target so the path reaches it.';

    public function handle(Request $request, D4Context $context, D4ParagonGraph $graph): Response
    {
        $validated = $request->validate([
            'class' => 'required|string|max:50',
            'boards' => 'required|array|min:1|max:8',
            'boards.*.board' => 'required|string|max:100',
            'boards.*.targets' => 'required|array|min:1|max:15',
            'boards.*.targets.*' => 'required',
            'boards.*.attach_gate' => 'nullable|array',
            'boards.*.attach_gate.row' => 'required_with:boards.*.attach_gate|integer|min:0|max:40',
            'boards.*.attach_gate.col' => 'required_with:boards.*.attach_gate|integer|min:0|max:40',
        ]);

        $results = [];
        $totalPoints = 0;

        foreach (array_values($validated['boards']) as $index => $entry) {
            $board = $graph->board($entry['board'], $validated['class']) ?? $graph->board($entry['board']);

            if ($board === null) {
                return Response::error("Unknown paragon board \"{$entry['board']}\". Use get_paragon_board to list the boards for the class.");
            }

            $grid = is_array($board->grid) ? $board->grid : [];
            $result = $this->planBoard($graph, $grid, $entry, $index, $board->name);

            if (is_string($result)) {
                return Response::error($result);
            }

            $totalPoints += $result['points_used'];
            $results[] = $result;
        }

        return Response::json([
            'class' => $validated['class'],
            'boards' => $results,
            'total_points' => $totalPoints,
            'note' => 'Copy each board\'s `nodes` into paragon[].nodes and its `entry_gate` into paragon[].attach.gate (with attach.to = the index of the entry it hangs off; the start board takes no attach). Coordinates are 0-based pre-rotation row/col. Targets are routed greedily in order, so list the important ones first.',
        ]);
    }

    /**
     * @param  list<list<array<string, mixed>|null>>  $grid
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>|string an error message when planning is impossible
     */
    protected function planBoard(D4ParagonGraph $graph, array $grid, array $entry, int $index, string $boardName): array|string
    {
        $targets = array_map($this->coerceTarget(...), array_values($entry['targets']));

        if ($index === 0) {
            $seed = $graph->startNode($grid) ?? $graph->startGate($grid);

            if ($seed === null) {
                return "\"{$boardName}\" is not a start board (start boards carry the free StartNode cell and exactly one gate). List the class start board first — get_paragon_board shows gate_count per board.";
            }

            $plan = $graph->plan($grid, $targets, $seed);

            return $plan === null ? "Could not plan on \"{$boardName}\"." : $this->result($boardName, $seed, $plan, gateChosen: false);
        }

        $gate = $entry['attach_gate'] ?? null;

        if (is_array($gate)) {
            $seed = ['row' => (int) $gate['row'], 'col' => (int) $gate['col']];
            $cell = $graph->cellAt($grid, $seed['row'], $seed['col']);

            if ($cell === null || ($cell['is_gate'] ?? false) !== true) {
                return "attach_gate ({$seed['row']},{$seed['col']}) is not a gate cell on \"{$boardName}\". Its gates are at: ".$this->gateList($graph, $grid).'.';
            }

            $plan = $graph->plan($grid, $targets, $seed);

            return $plan === null ? "Could not plan on \"{$boardName}\"." : $this->result($boardName, $seed, $plan, gateChosen: false);
        }

        // No gate named: try every gate and keep the cheapest complete plan.
        $best = null;
        $bestSeed = null;

        foreach ($graph->gates($grid) as $candidate) {
            $plan = $graph->plan($grid, $targets, $candidate);

            if ($plan === null) {
                continue;
            }

            $better = $best === null
                || count($plan['unreachable']) < count($best['unreachable'])
                || (count($plan['unreachable']) === count($best['unreachable']) && $plan['points_used'] < $best['points_used']);

            if ($better) {
                $best = $plan;
                $bestSeed = $candidate;
            }
        }

        if ($best === null || $bestSeed === null) {
            return "\"{$boardName}\" has no gate cells to enter through; is the board data imported?";
        }

        return $this->result($boardName, $bestSeed, $best, gateChosen: true);
    }

    /**
     * @param  array{row: int, col: int}  $seed
     * @param  array{nodes: list<array{row: int, col: int}>, points_used: int, paths: list<array<string, mixed>>, unreachable: list<string>}  $plan
     * @return array<string, mixed>
     */
    protected function result(string $boardName, array $seed, array $plan, bool $gateChosen): array
    {
        return [
            'board' => $boardName,
            'entry_gate' => $seed,
            'entry_gate_chosen_automatically' => $gateChosen ?: null,
            'nodes' => $plan['nodes'],
            'points_used' => $plan['points_used'],
            'paths' => $plan['paths'],
            'unreachable' => $plan['unreachable'] === [] ? null : $plan['unreachable'],
        ];
    }

    /**
     * A target arrives as a node name, a "row,col" string or a {row, col}
     * object; the graph wants names or coordinate arrays.
     */
    protected function coerceTarget(mixed $target): mixed
    {
        if (is_string($target) && preg_match('/^\s*(\d+)\s*,\s*(\d+)\s*$/', $target, $matches) === 1) {
            return ['row' => (int) $matches[1], 'col' => (int) $matches[2]];
        }

        return $target;
    }

    /**
     * @param  list<list<array<string, mixed>|null>>  $grid
     */
    protected function gateList(D4ParagonGraph $graph, array $grid): string
    {
        return implode(', ', array_map(
            fn (array $gate) => "({$gate['row']},{$gate['col']})",
            $graph->gates($grid),
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'class' => $schema->string()->description('Character class, e.g. "Barbarian". Determines whose boards resolve first.')->required(),
            'boards' => $schema->array()->items(
                $schema->object([
                    'board' => $schema->string()->required()->description('Paragon board name from get_paragon_board. List the class start board first, then boards in attachment order.'),
                    'targets' => $schema->array()->items($schema->string())->required()->description('Node names on this board ("Blood Rage", "Glyph Socket") or "row,col"-style cells to path to. Include the exit gate of any board that leads to another board.'),
                    'attach_gate' => $schema->object([
                        'row' => $schema->integer()->required(),
                        'col' => $schema->integer()->required(),
                    ])->description('The gate cell (pre-rotation) this board is entered through. Omit to let the server pick the cheapest gate.'),
                ]),
            )->required()->description('The boards to plan, in attachment order starting with the class start board.'),
        ];
    }
}
