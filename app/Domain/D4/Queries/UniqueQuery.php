<?php

namespace App\Domain\D4\Queries;

use App\Domain\D4\D4Context;
use App\Models\D4\UniqueItem;
use Illuminate\Database\Eloquent\Builder;

class UniqueQuery
{
    use CachesQueryResults;

    public function __construct(protected D4Context $context) {}

    /** @return list<array<string, mixed>> */
    public function search(
        ?string $term = null,
        ?string $className = null,
        ?string $itemType = null,
        ?bool $isMythic = null,
        bool $includeUnreleased = false,
        int $limit = 25,
    ): array {
        return $this->remember(__FUNCTION__, func_get_args(), fn () => UniqueItem::forVersion($this->context->versionId())
            ->when(! $includeUnreleased, fn (Builder $q) => $q->released())
            ->when($className, fn (Builder $q) => $q->whereLike('class_name', $className))
            ->when($itemType, fn (Builder $q) => $q->whereLike('item_type', "%{$itemType}%"))
            ->when($isMythic !== null, fn (Builder $q) => $q->where('is_mythic', $isMythic))
            ->when($term, fn (Builder $q) => $q->where(fn (Builder $sub) => $sub
                ->whereLike('name', "%{$term}%")
                ->orWhereLike('power_text', "%{$term}%")))
            ->orderBy('name')
            ->limit(min($limit, 100))
            ->get()
            ->map(fn (UniqueItem $unique) => $this->summarize($unique))
            ->all());
    }

    /** @return array<string, mixed>|null */
    public function detail(?string $name = null, ?int $snoId = null): ?array
    {
        return $this->remember(__FUNCTION__, func_get_args(), fn () => $this->uncachedDetail($name, $snoId));
    }

    /** @return array<string, mixed>|null */
    protected function uncachedDetail(?string $name, ?int $snoId): ?array
    {
        $unique = UniqueItem::forVersion($this->context->versionId())
            ->when($snoId !== null, fn (Builder $q) => $q->where('sno_id', $snoId))
            ->when($snoId === null && $name !== null, fn (Builder $q) => $q->whereLike('name', $name))
            ->orderByDesc('is_released')
            ->first();

        if ($unique === null) {
            return null;
        }

        return array_merge($this->summarize($unique), [
            'affixes' => $unique->affixes,
            'base_item' => $unique->raw['base_item'] ?? null,
            'item_families' => $unique->raw['item_families'] ?? [],
        ]);
    }

    /** @return array<string, mixed> */
    protected function summarize(UniqueItem $unique): array
    {
        return [
            'sno_id' => $unique->sno_id,
            'name' => $unique->name,
            'item_type' => $unique->item_type,
            'class' => $unique->class_name,
            'is_mythic' => $unique->is_mythic,
            'power_text' => $unique->power_text,
            'affix_count' => count($unique->affixes),
            'is_released' => $unique->is_released,
        ];
    }
}
