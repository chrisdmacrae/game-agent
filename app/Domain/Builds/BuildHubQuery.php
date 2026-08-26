<?php

namespace App\Domain\Builds;

use App\Models\Build;
use App\Models\Game;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Collection;

/**
 * The game hub's published-build list: server-side filtering, sorting and the
 * class facet counts rendered next to the filter rail (scope §3.4).
 *
 * Everything filters and sorts on the promoted columns, never on the jsonb
 * payload — see Build::syncPromotedFields().
 */
class BuildHubQuery
{
    /** @var list<string> */
    public const SORTS = ['updated', 'endorsements', 'dps', 'cost'];

    /**
     * Every filter the rail can carry, with the value that means "not applied".
     *
     * @var array<string, mixed>
     */
    protected const DEFAULTS = [
        'classes' => [],
        'ascendancy' => null,
        'stage' => null,
        'min_divine' => null,
        'max_divine' => null,
        'current_patch_only' => false,
        'hardcore_viable' => false,
    ];

    /** @var array{classes: list<string>, ascendancy: string|null, stage: string|null, min_divine: float|null, max_divine: float|null, current_patch_only: bool, hardcore_viable: bool, sort: string} */
    protected array $filters;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        protected Game $game,
        array $filters = [],
        protected ?int $activeVersionId = null,
    ) {
        $this->filters = self::gate($game, $filters);
    }

    /**
     * The filters as they were actually applied: a parameter this game's rail
     * does not offer (a crafted `?ascendancy=` on the Diablo IV hub, say) falls
     * back to its default instead of filtering the list, and an unoffered sort
     * falls back to the newest-first default. The page prop is read back off
     * here so the rail and the query can never disagree.
     *
     * @param  array<string, mixed>  $filters
     * @return array{classes: list<string>, ascendancy: string|null, stage: string|null, min_divine: float|null, max_divine: float|null, current_patch_only: bool, hardcore_viable: bool, sort: string}
     */
    public static function gate(Game|string|null $game, array $filters): array
    {
        $profile = GameBuildProfile::for($game);
        $offered = $profile->hubFilterParams();

        $gated = [];

        foreach (self::DEFAULTS as $param => $default) {
            $gated[$param] = in_array($param, $offered, true)
                ? ($filters[$param] ?? $default)
                : $default;
        }

        $sort = $filters['sort'] ?? 'updated';
        $gated['sort'] = in_array($sort, $profile->hubSorts(), true) ? $sort : 'updated';

        /** @var array{classes: list<string>, ascendancy: string|null, stage: string|null, min_divine: float|null, max_divine: float|null, current_patch_only: bool, hardcore_viable: bool, sort: string} $gated */
        return $gated;
    }

    /**
     * @return array{classes: list<string>, ascendancy: string|null, stage: string|null, min_divine: float|null, max_divine: float|null, current_patch_only: bool, hardcore_viable: bool, sort: string}
     */
    public function filters(): array
    {
        return $this->filters;
    }

    /**
     * The filtered, sorted result set.
     *
     * @return EloquentBuilder<Build>
     */
    public function results(): EloquentBuilder
    {
        $query = $this->base();

        $classes = $this->filters['classes'] ?? [];

        if ($classes !== []) {
            $query->whereIn('class', $classes);
        }

        return $this->sort($query);
    }

    /**
     * Class counts for the filter rail. Every other filter applies, but the
     * class filter itself does not: the counts have to show what selecting a
     * different class would return.
     *
     * @return array<string, int>
     */
    public function classFacets(): array
    {
        return $this->base()
            ->whereNotNull('class')
            ->select('class')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('class')
            ->orderBy('class')
            ->pluck('aggregate', 'class')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * Every filter except the class selection.
     *
     * @return EloquentBuilder<Build>
     */
    protected function base(): EloquentBuilder
    {
        $query = Build::query()
            ->public()
            ->where('game_id', $this->game->id);

        if (($ascendancy = $this->filters['ascendancy'] ?? null) !== null) {
            $query->where('ascendancy', $ascendancy);
        }

        if (($stage = $this->filters['stage'] ?? null) !== null) {
            $query->where('stage', $stage);
        }

        if (($min = $this->filters['min_divine'] ?? null) !== null) {
            $query->where('cost_divine', '>=', $min);
        }

        if (($max = $this->filters['max_divine'] ?? null) !== null) {
            $query->where('cost_divine', '<=', $max);
        }

        if (($this->filters['current_patch_only'] ?? false) && $this->activeVersionId !== null) {
            $query->where('game_version_id', $this->activeVersionId);
        }

        if ($this->filters['hardcore_viable'] ?? false) {
            $query->where('hardcore_viable', true);
        }

        return $query;
    }

    /**
     * @param  EloquentBuilder<Build>  $query
     * @return EloquentBuilder<Build>
     */
    protected function sort(EloquentBuilder $query): EloquentBuilder
    {
        return match ($this->filters['sort'] ?? 'updated') {
            // `is null` sorts false before true on both Postgres and SQLite,
            // which keeps builds without a number at the bottom either way.
            'endorsements' => $query->orderByDesc('endorsements_count')->orderByDesc('updated_at'),
            'dps' => $query->orderByRaw('(dps is null) asc')->orderByDesc('dps')->orderByDesc('updated_at'),
            'cost' => $query->orderByRaw('(cost_divine is null) asc')->orderBy('cost_divine')->orderByDesc('updated_at'),
            default => $query->orderByDesc('updated_at'),
        };
    }

    /**
     * Shape a build for a BuildCard.
     *
     * @return array<string, mixed>
     */
    public static function card(Build $build): array
    {
        return [
            'id' => $build->public_id,
            'name' => $build->name,
            'summary' => $build->summary,
            'visibility' => $build->visibility,
            'class' => $build->class,
            'ascendancy' => $build->ascendancy,
            'stage' => $build->stage,
            'tier' => $build->tier,
            'level' => $build->level,
            'dps' => $build->dps,
            'ehp' => $build->ehp,
            'cost_divine' => $build->cost_divine,
            'hardcore_viable' => $build->hardcore_viable,
            'endorsements' => $build->endorsements_count,
            'author' => $build->user?->handle ?? $build->user?->name,
            'patch' => $build->gameVersion?->version,
            'url' => $build->url(),
            'updated_at' => $build->updated_at?->toDateString(),
        ];
    }

    /**
     * @param  Collection<int, Build>  $builds
     * @return list<array<string, mixed>>
     */
    public static function cards(Collection $builds): array
    {
        return $builds->map(fn (Build $build) => self::card($build))->values()->all();
    }
}
