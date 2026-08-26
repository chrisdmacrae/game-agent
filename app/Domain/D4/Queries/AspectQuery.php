<?php

namespace App\Domain\D4\Queries;

use App\Domain\D4\D4Context;
use App\Models\D4\Aspect;
use Illuminate\Database\Eloquent\Builder;

class AspectQuery
{
    use CachesQueryResults;

    public function __construct(protected D4Context $context) {}

    /**
     * The aspects table has no class column: the class an aspect belongs to is
     * carried on the importer's `raw.class_name` (empty for generic aspects).
     *
     * @return list<array<string, mixed>>
     */
    public function search(
        ?string $term = null,
        ?string $category = null,
        ?string $className = null,
        ?string $itemType = null,
        bool $includeUnreleased = false,
        int $limit = 25,
    ): array {
        return $this->remember(__FUNCTION__, func_get_args(), fn () => Aspect::forVersion($this->context->versionId())
            ->when(! $includeUnreleased, fn (Builder $q) => $q->released())
            ->when($category, fn (Builder $q) => $q->whereLike('category', $category))
            ->when($className, fn (Builder $q) => $q->where('raw->class_name', $className))
            ->when($itemType, fn (Builder $q) => $q->whereJsonContains('item_types', $itemType))
            ->when($term, fn (Builder $q) => $q->where(fn (Builder $sub) => $sub
                ->whereLike('name', "%{$term}%")
                ->orWhereLike('text', "%{$term}%")))
            ->orderBy('name')
            ->limit(min($limit, 100))
            ->get()
            ->map(fn (Aspect $aspect) => [
                'sno_id' => $aspect->sno_id,
                'name' => $aspect->name,
                'category' => $aspect->category,
                'class' => ($aspect->raw['class_name'] ?? '') ?: null,
                'text' => $aspect->text,
                'item_types' => $aspect->item_types,
                'value_range' => $aspect->value_range,
                'is_released' => $aspect->is_released,
            ])
            ->all());
    }
}
