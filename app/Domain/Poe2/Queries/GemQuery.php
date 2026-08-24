<?php

namespace App\Domain\Poe2\Queries;

use App\Domain\Poe2\Poe2Context;
use App\Models\Poe2\Gem;
use Illuminate\Database\Eloquent\Builder;

class GemQuery
{
    use CachesQueryResults;

    public function __construct(protected Poe2Context $context) {}

    /**
     * @param  list<string>  $tags
     * @return list<array<string, mixed>>
     */
    public function search(
        ?string $term = null,
        ?string $gemType = null,
        array $tags = [],
        bool $includeUnreleased = false,
        int $limit = 20,
    ): array {
        return $this->remember(__FUNCTION__, func_get_args(), function () use ($term, $gemType, $tags, $includeUnreleased, $limit) {
            $query = Gem::forVersion($this->context->versionId())
                ->when(! $includeUnreleased, fn (Builder $q) => $q->where('is_released', true))
                ->whereNot('name', 'Coming Soon')
                ->when($gemType, fn (Builder $q) => $q->where('gem_type', $gemType))
                ->when($term, fn (Builder $q) => $q->where(fn (Builder $sub) => $sub
                    ->whereLike('name', "%{$term}%")
                    ->orWhereLike('description', "%{$term}%")))
                ->orderBy('name')
                ->limit(min($limit, 50));

            foreach ($tags as $tag) {
                $query->whereJsonContains('tags', $tag);
            }

            return $query->get()->map(fn (Gem $gem) => $this->summarize($gem))->all();
        });
    }

    /** @return array<string, mixed>|null */
    public function detail(string $name, ?int $level = null): ?array
    {
        return $this->remember(__FUNCTION__, func_get_args(), fn () => $this->uncachedDetail($name, $level));
    }

    /** @return array<string, mixed>|null */
    protected function uncachedDetail(string $name, ?int $level): ?array
    {
        $gem = Gem::forVersion($this->context->versionId())
            ->whereLike('name', $name)
            ->orderByDesc('is_released')
            ->first();

        if ($gem === null) {
            return null;
        }

        return array_merge($this->summarize($gem), [
            'requirement_weights' => $gem->requirement_weights,
            'recommended_supports' => $this->resolveGemNames($gem->recommended_supports),
            'skills' => array_map(fn (array $skill) => $this->summarizeSkill($skill, $level), $gem->skill_details),
        ]);
    }

    /**
     * Which support gems can support the given active gem, based on the
     * support's allowed/excluded skill types. Type matching is heuristic:
     * logical markers like "AND" in allowed_types are ignored.
     *
     * @return array{gem: string, skill_types: list<string>, supports: list<array<string, mixed>>}|null
     */
    public function supportsFor(string $gemName, ?string $term = null, int $limit = 30): ?array
    {
        return $this->remember(__FUNCTION__, func_get_args(), fn () => $this->uncachedSupportsFor($gemName, $term, $limit));
    }

    /** @return array{gem: string, skill_types: list<string>, supports: list<array<string, mixed>>}|null */
    protected function uncachedSupportsFor(string $gemName, ?string $term, int $limit): ?array
    {
        $gem = Gem::forVersion($this->context->versionId())
            ->whereLike('name', $gemName)
            ->where('gem_type', '!=', 'support')
            ->orderByDesc('is_released')
            ->first();

        if ($gem === null) {
            return null;
        }

        $skillTypes = collect($gem->skill_details)
            ->flatMap(fn (array $skill) => $skill['types'] ?? [])
            ->unique()
            ->values()
            ->all();

        $recommended = $gem->recommended_supports;

        $supports = Gem::forVersion($this->context->versionId())
            ->where('gem_type', 'support')
            ->whereNot('name', 'Coming Soon')
            ->where('is_released', true)
            ->when($term, fn (Builder $q) => $q->whereLike('name', "%{$term}%"))
            ->get()
            ->filter(fn (Gem $support) => $this->supportsTypes($support, $skillTypes))
            ->map(fn (Gem $support) => [
                'name' => $support->name,
                'description' => $support->description,
                'tags' => $support->tags,
                'is_recommended' => in_array($support->metadata_id, $recommended, true),
            ])
            ->sortByDesc('is_recommended')
            ->take($limit)
            ->values()
            ->all();

        return [
            'gem' => $gem->name,
            'skill_types' => $skillTypes,
            'supports' => $supports,
        ];
    }

    /** @param list<string> $skillTypes */
    protected function supportsTypes(Gem $support, array $skillTypes): bool
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

        return false;
    }

    /** @return array<string, mixed> */
    protected function summarize(Gem $gem): array
    {
        $reservations = collect($gem->skill_details)
            ->pluck('static.reservations')
            ->filter()
            ->first();

        return [
            'name' => $gem->name,
            'gem_type' => $gem->gem_type,
            'color' => $gem->color,
            'is_released' => $gem->is_released,
            'description' => $gem->description,
            'tags' => $gem->tags,
            'spirit_reservation' => $reservations['spirit'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $skill
     * @return array<string, mixed>
     */
    protected function summarizeSkill(array $skill, ?int $level): array
    {
        $statSets = [];

        foreach ($skill['stat_sets'] ?? [] as $set) {
            $perLevel = $set['per_level'] ?? [];
            $levels = array_map('intval', array_keys($perLevel));
            sort($levels);

            $chosen = $level !== null && in_array($level, $levels, true)
                ? $level
                : ($levels === [] ? null : max($levels));

            $statSets[] = [
                'id' => $set['id'] ?? null,
                'level' => $chosen,
                'available_levels' => $levels === [] ? null : ['min' => min($levels), 'max' => max($levels)],
                'stat_text' => $chosen !== null
                    ? array_values($perLevel[(string) $chosen]['stat_text'] ?? [])
                    : [],
                'static_stat_text' => array_values($set['static']['stat_text'] ?? []),
            ];
        }

        return [
            'name' => $skill['display_name'] ?? $skill['key'],
            'description' => $skill['description'] ?? null,
            'types' => $skill['types'] ?? [],
            'weapon_restrictions' => $skill['weapon_restrictions'] ?? [],
            'cast_time_ms' => $skill['cast_time'] ?? null,
            'is_support' => $skill['is_support'] ?? false,
            'support_constraints' => $skill['support_gem'] ?? null,
            'costs_and_reservations' => $skill['static'] ?? null,
            'stat_sets' => $statSets,
        ];
    }

    /**
     * @param  list<string>  $metadataIds
     * @return list<string>
     */
    protected function resolveGemNames(array $metadataIds): array
    {
        if ($metadataIds === []) {
            return [];
        }

        return Gem::forVersion($this->context->versionId())
            ->whereIn('metadata_id', $metadataIds)
            ->pluck('name')
            ->all();
    }
}
