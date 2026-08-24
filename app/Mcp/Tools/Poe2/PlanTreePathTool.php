<?php

namespace App\Mcp\Tools\Poe2;

use App\Domain\Poe2\Poe2Context;
use App\Domain\Poe2\TreeGraph;
use App\Models\Poe2\PassiveNode;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class PlanTreePathTool extends Tool
{
    protected string $name = 'plan_tree_path';

    protected string $description = 'Compute a legal, contiguous passive tree allocation. Give the class and target passives (keystone/notable names or node ids) and the server routes travel paths from the class start to every target, returning ready-to-use node_ids for validate_build/save_build plus per-target point costs. ALWAYS use this to build node_ids — never hand-pick nodes, the game requires sequential pathing. Targets that are cheaper via an instilled amulet or unique jewel should be left out and declared in granted_nodes instead.';

    public function handle(Request $request, Poe2Context $context, TreeGraph $graph): Response
    {
        $validated = $request->validate([
            'class' => 'required|string|max:50',
            'targets' => 'required|array|min:1|max:15',
            'targets.*' => 'required',
        ]);

        $resolved = [];
        $notFound = [];
        $ambiguous = [];

        foreach ($validated['targets'] as $target) {
            if (is_numeric($target)) {
                $resolved[(int) $target] = "node {$target}";

                continue;
            }

            $matches = PassiveNode::forVersion($context->versionId())
                ->whereLike('name', (string) $target)
                ->whereNull('ascendancy_key')
                ->get(['node_id', 'name', 'kind']);

            if ($matches->isEmpty()) {
                $notFound[] = (string) $target;
            } elseif ($matches->count() > 1) {
                // Same-named nodes exist in multiple tree locations; plan to
                // the one the router can reach cheapest by offering all ids.
                $ambiguous[(string) $target] = $matches->pluck('node_id')->all();
                $resolved[$matches->first()->node_id] = $matches->first()->name;
            } else {
                $resolved[$matches->first()->node_id] = $matches->first()->name;
            }
        }

        if ($notFound !== []) {
            return Response::error('Unknown passives: '.implode(', ', $notFound).'. Use search_passives to find exact names (ascendancy nodes cannot be pathed to — they are allocated with ascendancy points).');
        }

        $plan = $graph->plan($validated['class'], array_keys($resolved));

        if ($plan === null) {
            return Response::error("Could not resolve the class start for \"{$validated['class']}\". If the class name is valid (see list_classes), the tree data needs refreshing — the server operator should run `php artisan poe2:import`.");
        }

        $names = PassiveNode::forVersion($context->versionId())
            ->whereIn('node_id', array_merge($plan['node_ids'], $plan['unreachable']))
            ->pluck('name', 'node_id');

        return Response::json([
            'class' => $validated['class'],
            'node_ids' => $plan['node_ids'],
            'points_used' => $plan['points_used'],
            'paths' => array_map(fn (array $path) => [
                'target' => $resolved[$path['target']] ?? "node {$path['target']}",
                'points_added' => $path['points_added'],
                'route' => array_map(
                    fn (int $id) => ($names[$id] ?: 'travel')." ({$id})",
                    $path['route'],
                ),
            ], $plan['paths']),
            'unreachable' => array_map(
                fn (int $id) => ($names[$id] ?? $resolved[$id] ?? 'node')." ({$id})",
                $plan['unreachable'],
            ),
            'ambiguous_names' => $ambiguous === [] ? null : $ambiguous,
            'note' => 'Pass node_ids directly to validate_build / save_build as passives.node_ids. Order targets by importance: paths are routed greedily, so nearer targets share travel nodes.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'class' => $schema->string()->description('Character class, e.g. "Monk". Determines the tree starting point.')->required(),
            'targets' => $schema->array()->items($schema->string())->description('Keystone/notable names (from search_passives) or node ids to path to. Main-tree nodes only — not ascendancy nodes.')->required(),
        ];
    }
}
