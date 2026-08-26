<?php

namespace App\Domain\D4\Queries;

use App\Domain\D4\D4Context;
use App\Models\D4\CharacterClass;
use App\Models\D4\Skill;
use Illuminate\Database\Eloquent\Builder;

class SkillQuery
{
    use CachesQueryResults;

    public function __construct(protected D4Context $context) {}

    /** @return list<array<string, mixed>> */
    public function listClasses(bool $includeUnreleased = false): array
    {
        return $this->remember(__FUNCTION__, func_get_args(), fn () => $this->uncachedListClasses($includeUnreleased));
    }

    /** @return list<array<string, mixed>> */
    protected function uncachedListClasses(bool $includeUnreleased): array
    {
        $skillCounts = Skill::forVersion($this->context->versionId())
            ->when(! $includeUnreleased, fn (Builder $q) => $q->released())
            ->whereNotNull('class_name')
            ->get(['class_name'])
            ->countBy('class_name');

        return CharacterClass::forVersion($this->context->versionId())
            ->when(! $includeUnreleased, fn (Builder $q) => $q->released())
            ->orderBy('name')
            ->get()
            ->map(fn (CharacterClass $class) => [
                'name' => $class->name,
                'resource' => $class->resource,
                'description' => $class->description,
                'skill_count' => $skillCounts[$class->name] ?? 0,
                'is_released' => $class->is_released,
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function search(
        ?string $term = null,
        ?string $className = null,
        ?string $category = null,
        ?string $tag = null,
        bool $includeUnreleased = false,
        int $limit = 25,
    ): array {
        return $this->remember(__FUNCTION__, func_get_args(), fn () => Skill::forVersion($this->context->versionId())
            ->when(! $includeUnreleased, fn (Builder $q) => $q->released())
            ->when($className, fn (Builder $q) => $q->whereLike('class_name', $className))
            ->when($category, fn (Builder $q) => $q->whereLike('category', $category))
            ->when($tag, fn (Builder $q) => $q->whereJsonContains('tags', $tag))
            ->when($term, fn (Builder $q) => $q->where(fn (Builder $sub) => $sub
                ->whereLike('name', "%{$term}%")
                ->orWhereLike('description', "%{$term}%")))
            ->orderBy('name')
            ->limit(min($limit, 100))
            ->get()
            ->map(fn (Skill $skill) => $this->summarize($skill))
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
        $skill = Skill::forVersion($this->context->versionId())
            ->when($snoId !== null, fn (Builder $q) => $q->where('sno_id', $snoId))
            ->when($snoId === null && $name !== null, fn (Builder $q) => $q->whereLike('name', $name))
            ->orderByDesc('is_released')
            ->first();

        if ($skill === null) {
            return null;
        }

        return array_merge($this->summarize($skill), [
            'enhancements' => $skill->enhancements,
            'primary_tag' => $skill->raw['primary_tag'] ?? null,
            'search_tags' => $skill->raw['search_tags'] ?? [],
        ]);
    }

    /** @return array<string, mixed> */
    protected function summarize(Skill $skill): array
    {
        return [
            'sno_id' => $skill->sno_id,
            'name' => $skill->name,
            'class' => $skill->class_name,
            'category' => $skill->category,
            'max_rank' => $skill->max_rank,
            'tags' => $skill->tags,
            'description' => $skill->description,
            'enhancement_count' => count($skill->enhancements),
            'is_released' => $skill->is_released,
        ];
    }
}
