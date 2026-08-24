<?php

namespace App\Domain\Poe2\Queries;

use App\Domain\Poe2\Poe2Context;
use App\Models\Poe2\UniqueItem;
use Illuminate\Database\Eloquent\Builder;

class UniqueQuery
{
    use CachesQueryResults;

    public function __construct(protected Poe2Context $context) {}

    /** @return list<array<string, mixed>> */
    public function search(
        ?string $term = null,
        ?string $itemClass = null,
        ?string $modText = null,
        int $limit = 20,
    ): array {
        return $this->remember(__FUNCTION__, func_get_args(), fn () => UniqueItem::forVersion($this->context->versionId())
            ->when($term, fn (Builder $q) => $q->where(fn (Builder $sub) => $sub
                ->whereLike('name', "%{$term}%")
                ->orWhereLike('base_name', "%{$term}%")))
            ->when($itemClass, fn (Builder $q) => $q->whereLike('item_class', $itemClass))
            ->when($modText, fn (Builder $q) => $q->whereLike('source_text', "%{$modText}%"))
            ->orderBy('name')
            ->limit(min($limit, 50))
            ->get()
            ->map(fn (UniqueItem $unique) => [
                'name' => $unique->name,
                'base_name' => $unique->base_name,
                'item_class' => $unique->item_class,
                'mods' => $this->currentMods($unique),
            ])
            ->all());
    }

    /** @return array<string, mixed>|null */
    public function detail(string $name): ?array
    {
        return $this->remember(__FUNCTION__, func_get_args(), fn () => $this->uncachedDetail($name));
    }

    /** @return array<string, mixed>|null */
    protected function uncachedDetail(string $name): ?array
    {
        $unique = UniqueItem::forVersion($this->context->versionId())
            ->whereLike('name', $name)
            ->first();

        if ($unique === null) {
            return null;
        }

        return [
            'name' => $unique->name,
            'base_name' => $unique->base_name,
            'item_class' => $unique->item_class,
            'variants' => $unique->variants,
            'mods' => $unique->mods,
            'current_mods' => $this->currentMods($unique),
        ];
    }

    /**
     * Mod lines applicable to the item's current version (i.e. lines not
     * restricted to a legacy variant). "Current" is the last listed variant.
     *
     * @return list<string>
     */
    protected function currentMods(UniqueItem $unique): array
    {
        $variants = $unique->variants;
        $currentVariant = $variants === [] ? null : count($variants);

        $lines = [];

        foreach ($unique->mods as $mod) {
            $applies = $mod['variants'] === null
                || $currentVariant === null
                || in_array($currentVariant, $mod['variants'], true);

            if ($applies) {
                $prefix = ($mod['is_implicit'] ?? false) ? '(implicit) ' : '';
                $lines[] = $prefix.$mod['text'];
            }
        }

        return $lines;
    }
}
