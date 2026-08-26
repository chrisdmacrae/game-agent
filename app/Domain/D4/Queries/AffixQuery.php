<?php

namespace App\Domain\D4\Queries;

use App\Domain\D4\D4Context;
use App\Models\D4\Affix;
use Illuminate\Database\Eloquent\Builder;

class AffixQuery
{
    use CachesQueryResults;

    public function __construct(protected D4Context $context) {}

    /** @return list<array<string, mixed>> */
    public function search(
        ?string $term = null,
        ?bool $isTempering = null,
        ?string $temperFamily = null,
        ?string $magicType = null,
        ?string $className = null,
        ?string $itemType = null,
        bool $includeUnreleased = false,
        int $limit = 30,
    ): array {
        return $this->remember(__FUNCTION__, func_get_args(), fn () => Affix::forVersion($this->context->versionId())
            ->when(! $includeUnreleased, fn (Builder $q) => $q->released())
            ->when($isTempering !== null, fn (Builder $q) => $q->where('is_tempering', $isTempering))
            ->when($temperFamily, fn (Builder $q) => $q->whereLike('temper_family', "%{$temperFamily}%"))
            ->when($magicType, fn (Builder $q) => $q->where('magic_type', $magicType))
            ->when($className, fn (Builder $q) => $q->whereLike('class_name', $className))
            ->when($itemType, fn (Builder $q) => $q->whereJsonContains('item_types', $itemType))
            ->when($term, fn (Builder $q) => $q->where(fn (Builder $sub) => $sub
                ->whereLike('text', "%{$term}%")
                ->orWhereLike('name', "%{$term}%")
                ->orWhereLike('key', "%{$term}%")))
            ->whereNotNull('text')
            ->orderBy('key')
            ->limit(min($limit, 100))
            ->get()
            ->map(fn (Affix $affix) => [
                'key' => $affix->key,
                'name' => $affix->name,
                'magic_type' => $affix->magic_type,
                'text' => $affix->text,
                'display_text' => $affix->display_text,
                'class' => $affix->class_name,
                'item_types' => $affix->item_types,
                'is_tempering' => $affix->is_tempering,
                'temper_family' => $affix->temper_family,
                'value_range' => $affix->value_range,
                'is_released' => $affix->is_released,
            ])
            ->all());
    }
}
