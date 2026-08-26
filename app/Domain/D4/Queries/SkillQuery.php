<?php

namespace App\Domain\D4\Queries;

use App\Domain\D4\D4Context;
use App\Domain\D4\TooltipText;
use App\Models\D4\CharacterClass;
use App\Models\D4\Skill;
use Illuminate\Database\Eloquent\Builder;

class SkillQuery
{
    use CachesQueryResults;

    public function __construct(
        protected D4Context $context,
        protected TooltipText $tooltips,
    ) {}

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
    public function detail(?string $name = null, ?int $snoId = null, int $rank = 1): ?array
    {
        return $this->remember(__FUNCTION__, func_get_args(), fn () => $this->uncachedDetail($name, $snoId, $rank));
    }

    /** @return array<string, mixed>|null */
    protected function uncachedDetail(?string $name, ?int $snoId, int $rank): ?array
    {
        $skill = Skill::forVersion($this->context->versionId())
            ->when($snoId !== null, fn (Builder $q) => $q->where('sno_id', $snoId))
            ->when($snoId === null && $name !== null, fn (Builder $q) => $q->whereLike('name', $name))
            ->orderByDesc('is_released')
            ->first();

        if ($skill === null) {
            return null;
        }

        $rank = $this->clampRank($skill, $rank);
        $values = $this->valuesForRank($skill, $rank);

        return array_merge($this->summarize($skill, $rank), [
            'enhancements' => array_map(
                fn (array $enhancement) => $enhancement + [
                    'description_rendered' => $this->tooltips->render($enhancement['description'] ?? null, $values),
                ],
                $skill->enhancements,
            ),
            'rankup_description' => $skill->raw['rankup_description'] ?? null,
            'rankup_description_rendered' => $this->tooltips->render($skill->raw['rankup_description'] ?? null, $values),
            'rank_values' => $this->rankValues($skill),
            'primary_tag' => $skill->raw['primary_tag'] ?? null,
            'search_tags' => $skill->raw['search_tags'] ?? [],
        ]);
    }

    /** @return array<string, mixed> */
    protected function summarize(Skill $skill, int $rank = 1): array
    {
        $rank = $this->clampRank($skill, $rank);

        return [
            'sno_id' => $skill->sno_id,
            'name' => $skill->name,
            'class' => $skill->class_name,
            'category' => $skill->category,
            'max_rank' => $skill->max_rank,
            'tags' => $skill->tags,
            'rank' => $rank,
            'description' => $skill->description,
            'description_rendered' => $this->tooltips->render($skill->description, $this->valuesForRank($skill, $rank)),
            'enhancement_count' => count($skill->enhancements),
            'is_released' => $skill->is_released,
        ];
    }

    /**
     * The script formula values the import evaluated for one rank, keyed by
     * their `SF_n` token so a tooltip can be rendered against them.
     *
     * @return array<string, float|array{min: float, max: float}>
     */
    protected function valuesForRank(Skill $skill, int $rank): array
    {
        return TooltipText::scriptFormulaValues($skill->rank_values[$rank] ?? null);
    }

    /**
     * The stored per-rank values, republished under their `SF_n` token names
     * so a reader can see how each number scales without decoding the indexes.
     *
     * @return array<int, array<string, float|array{min: float, max: float}>>
     */
    protected function rankValues(Skill $skill): array
    {
        $values = [];

        foreach ((array) $skill->rank_values as $rank => $atRank) {
            $values[(int) $rank] = TooltipText::scriptFormulaValues($atRank);
        }

        return $values;
    }

    protected function clampRank(Skill $skill, int $rank): int
    {
        return max(1, min($rank, max($skill->max_rank, 1)));
    }
}
