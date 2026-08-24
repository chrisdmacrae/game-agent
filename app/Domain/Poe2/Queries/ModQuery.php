<?php

namespace App\Domain\Poe2\Queries;

use App\Domain\Poe2\Poe2Context;
use App\Models\Poe2\ItemMod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ModQuery
{
    use CachesQueryResults;

    public function __construct(protected Poe2Context $context) {}

    /**
     * Search the affix pool. `itemTag` filters to mods that can spawn on
     * items with that tag (e.g. "amulet", "ring", "body_armour", "boots").
     *
     * @return list<array<string, mixed>>
     */
    public function search(
        ?string $term = null,
        ?string $itemTag = null,
        string $domain = 'item',
        ?string $generationType = null,
        int $limit = 30,
    ): array {
        return $this->remember(__FUNCTION__, func_get_args(), fn () => $this->uncachedSearch($term, $itemTag, $domain, $generationType, $limit));
    }

    /** @return list<array<string, mixed>> */
    protected function uncachedSearch(?string $term, ?string $itemTag, string $domain, ?string $generationType, int $limit): array
    {
        $mods = ItemMod::forVersion($this->context->versionId())
            ->when($domain !== 'any', fn (Builder $q) => $q->where('domain', $domain))
            ->when($generationType, fn (Builder $q) => $q->where('generation_type', $generationType))
            ->when($term, fn (Builder $q) => $q->whereLike('text', "%{$term}%"))
            ->when($itemTag, fn (Builder $q) => $q->whereJsonContains('spawn_tags', $itemTag))
            ->whereNotNull('text')
            ->orderBy('group_type')
            ->orderBy('required_level')
            ->limit(min($limit * 10, 500))
            ->get();

        // Collapse tier families (same group_type) into one entry per family,
        // annotated with tier count and level/value ranges.
        return $mods
            ->groupBy(fn (ItemMod $mod) => $mod->group_type ?? $mod->key)
            ->map(function ($family) {
                /** @var Collection<int, ItemMod> $family */
                $best = $family->sortByDesc('required_level')->first();

                return [
                    'text' => $best->text,
                    'generation_type' => $best->generation_type,
                    'tiers' => $family->count(),
                    'level_range' => [
                        'min' => $family->min('required_level'),
                        'max' => $family->max('required_level'),
                    ],
                    'best_tier_stats' => $best->stats,
                    'spawn_tags' => $best->spawn_tags,
                    'is_essence_only' => $best->is_essence_only,
                ];
            })
            ->take($limit)
            ->values()
            ->all();
    }
}
