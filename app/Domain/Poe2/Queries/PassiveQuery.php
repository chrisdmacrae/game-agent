<?php

namespace App\Domain\Poe2\Queries;

use App\Domain\Poe2\Poe2Context;
use App\Models\Poe2\Ascendancy;
use App\Models\Poe2\CharacterClass;
use App\Models\Poe2\PassiveNode;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PassiveQuery
{
    use CachesQueryResults;

    public function __construct(protected Poe2Context $context) {}

    /**
     * Search passive tree nodes by name or stat text.
     *
     * @return list<array<string, mixed>>
     */
    public function searchNodes(
        ?string $term = null,
        ?string $kind = null,
        bool $includeAscendancy = false,
        int $limit = 25,
    ): array {
        return $this->remember(__FUNCTION__, func_get_args(), fn () => PassiveNode::forVersion($this->context->versionId())
            ->when($kind, fn (Builder $q) => $q->where('kind', $kind))
            ->when(! $includeAscendancy, fn (Builder $q) => $q->whereNull('ascendancy_key'))
            ->when($term, fn (Builder $q) => $q->where(fn (Builder $sub) => $sub
                ->whereLike('name', "%{$term}%")
                ->orWhereLike($this->statsAsText($sub), "%{$term}%")))
            ->whereNot('name', '')
            ->orderByRaw("case kind when 'keystone' then 0 when 'notable' then 1 else 2 end")
            ->limit(min($limit, 50))
            ->get()
            ->map(fn (PassiveNode $node) => $this->summarizeNode($node))
            ->all());
    }

    /** @return array<string, mixed>|null */
    public function ascendancy(string $name): ?array
    {
        return $this->remember(__FUNCTION__, func_get_args(), fn () => $this->uncachedAscendancy($name));
    }

    /** @return array<string, mixed>|null */
    protected function uncachedAscendancy(string $name): ?array
    {
        $ascendancy = Ascendancy::forVersion($this->context->versionId())
            ->whereLike('name', $name)
            ->first();

        if ($ascendancy === null) {
            return null;
        }

        $nodes = PassiveNode::forVersion($this->context->versionId())
            ->where('ascendancy_key', $ascendancy->key)
            ->whereNot('name', '')
            ->orderByRaw("case kind when 'notable' then 0 when 'keystone' then 1 else 2 end")
            ->get()
            ->map(fn (PassiveNode $node) => $this->summarizeNode($node))
            ->all();

        return [
            'name' => $ascendancy->name,
            'class' => $ascendancy->class_name,
            'flavour_text' => $ascendancy->flavour_text,
            'nodes' => $nodes,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function listClasses(): array
    {
        return $this->remember(__FUNCTION__, [], fn () => $this->uncachedListClasses());
    }

    /** @return list<array<string, mixed>> */
    protected function uncachedListClasses(): array
    {
        $ascendancies = Ascendancy::forVersion($this->context->versionId())
            ->get()
            ->groupBy('class_name');

        return CharacterClass::forVersion($this->context->versionId())
            ->orderBy('name')
            ->get()
            ->map(fn (CharacterClass $class) => [
                'name' => $class->name,
                'description' => $class->description,
                'base_stats' => collect($class->base_stats)->except('unarmed')->all(),
                'ascendancies' => ($ascendancies[$class->name] ?? collect())
                    ->map(fn (Ascendancy $a) => $a->name)
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    /**
     * The stats column is jsonb on Postgres, which LIKE can't scan directly.
     */
    protected function statsAsText(Builder $query): Expression|string
    {
        return $query->getConnection()->getDriverName() === 'pgsql'
            ? DB::raw('stats::text')
            : 'stats';
    }

    /** @return array<string, mixed> */
    protected function summarizeNode(PassiveNode $node): array
    {
        return [
            'node_id' => $node->node_id,
            'name' => $node->name,
            'kind' => $node->kind,
            'ascendancy' => $node->ascendancy_key,
            'stats' => $node->stats,
        ];
    }
}
