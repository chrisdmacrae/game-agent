<?php

namespace App\Domain\Poe2;

use App\Models\Poe2\Ascendancy;
use App\Models\Poe2\PassiveNode;
use App\Models\SavedBuild;
use Illuminate\Support\Str;

/**
 * Exports a saved build as a PoE2 in-game Build Planner file: a JSON Build
 * object the client loads from "My Games/Path of Exile 2/BuildPlanner/*.build".
 * Passives are GGG PassiveSkills table ids (e.g. "strength89"), which the
 * tree data carries as each node's raw "id"; ascendancies use their
 * "{Class}{N}" key (e.g. "Warrior1"). Format spec:
 * https://www.pathofexile.com/developer/docs/game
 */
class BuildPlannerExporter
{
    public function __construct(protected Poe2Context $context) {}

    public function json(SavedBuild $build): string
    {
        return json_encode(
            $this->build($build),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        )."\n";
    }

    public function filename(SavedBuild $build): string
    {
        return (Str::slug($build->name ?? '') ?: $build->public_id).'.build';
    }

    /**
     * @return array<string, mixed>
     */
    public function build(SavedBuild $build): array
    {
        $definition = $build->build;
        $versionId = $build->game_version_id ?? $this->context->versionId();

        $ascendancy = isset($definition['ascendancy'])
            ? Ascendancy::forVersion($versionId)
                ->whereLike('name', $definition['ascendancy'])
                ->first()
            : null;

        $file = [
            'name' => $build->name ?? 'Theorycrafted build',
            'author' => 'PoE2 Theorycrafter',
            'link' => $build->url(),
        ];

        if ($build->summary !== null) {
            $file['description'] = $build->summary;
        }

        if ($ascendancy !== null) {
            $file['ascendancy'] = $ascendancy->key;
        }

        $file['passives'] = array_merge(
            $this->treePassiveIds($definition, $versionId),
            $this->ascendancyPassiveIds($definition, $versionId, $ascendancy),
        );

        return $file;
    }

    /**
     * Map the build's numeric tree node hashes onto GGG PassiveSkills ids,
     * preserving allocation order. Nodes without an id in the tree data
     * (class roots, unmapped sockets) are skipped — the game rejects files
     * referencing unknown passive ids.
     *
     * @param  array<string, mixed>  $definition
     * @return list<string>
     */
    protected function treePassiveIds(array $definition, int $versionId): array
    {
        $nodeIds = array_values(array_unique(array_map('intval', $definition['passives']['node_ids'] ?? [])));

        if ($nodeIds === []) {
            return [];
        }

        $nodes = PassiveNode::forVersion($versionId)
            ->whereIn('node_id', $nodeIds)
            ->get(['node_id', 'raw'])
            ->keyBy('node_id');

        $passives = [];

        foreach ($nodeIds as $nodeId) {
            $gggId = $nodes[$nodeId]->raw['id'] ?? null;

            if (is_string($gggId) && $gggId !== '') {
                $passives[] = $gggId;
            }
        }

        return $passives;
    }

    /**
     * Ascendancy picks are stored by name; resolve each within the build's
     * ascendancy cluster.
     *
     * @param  array<string, mixed>  $definition
     * @return list<string>
     */
    protected function ascendancyPassiveIds(array $definition, int $versionId, ?Ascendancy $ascendancy): array
    {
        $passives = [];

        foreach ($definition['passives']['ascendancy_nodes'] ?? [] as $name) {
            $raw = PassiveNode::forVersion($versionId)
                ->whereLike('name', $name)
                ->when($ascendancy, fn ($q) => $q->where('ascendancy_key', $ascendancy->key))
                ->first()
                ?->raw;

            $gggId = $raw['id'] ?? null;

            if (is_string($gggId) && $gggId !== '') {
                $passives[] = $gggId;
            }
        }

        return $passives;
    }
}
