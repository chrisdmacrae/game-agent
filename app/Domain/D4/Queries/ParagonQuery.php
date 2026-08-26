<?php

namespace App\Domain\D4\Queries;

use App\Domain\D4\D4Context;
use App\Models\D4\ParagonBoard;
use App\Models\D4\ParagonGlyph;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ParagonQuery
{
    use CachesQueryResults;

    public function __construct(protected D4Context $context) {}

    /** @return list<array<string, mixed>> */
    public function listBoards(?string $className = null, bool $includeUnreleased = false, int $limit = 50): array
    {
        return $this->remember(__FUNCTION__, func_get_args(), fn () => ParagonBoard::forVersion($this->context->versionId())
            ->when(! $includeUnreleased, fn (Builder $q) => $q->released())
            ->when($className, fn (Builder $q) => $q->whereLike('class_name', $className))
            ->orderBy('class_name')
            ->orderBy('name')
            ->limit(min($limit, 100))
            ->get()
            ->map(fn (ParagonBoard $board) => $this->summarizeBoard($board))
            ->all());
    }

    /** @return array<string, mixed>|null */
    public function board(string $name, ?string $className = null): ?array
    {
        return $this->remember(__FUNCTION__, func_get_args(), fn () => $this->uncachedBoard($name, $className));
    }

    /** @return array<string, mixed>|null */
    protected function uncachedBoard(string $name, ?string $className): ?array
    {
        $board = ParagonBoard::forVersion($this->context->versionId())
            ->whereLike('name', $name)
            ->when($className, fn (Builder $q) => $q->whereLike('class_name', $className))
            ->orderByDesc('is_released')
            ->first();

        if ($board === null) {
            return null;
        }

        return array_merge($this->summarizeBoard($board), [
            'grid_legend' => 'grid is a row-major 2D array. A null cell is empty space; a filled cell is a paragon node with its key, name, rarity, attributes and socket/gate flags.',
            'grid' => $board->grid,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function searchGlyphs(?string $term = null, ?string $className = null, bool $includeUnreleased = false, int $limit = 25): array
    {
        return $this->remember(__FUNCTION__, func_get_args(), fn () => ParagonGlyph::forVersion($this->context->versionId())
            ->when(! $includeUnreleased, fn (Builder $q) => $q->released())
            ->when($className, fn (Builder $q) => $q->whereLike('class_name', $className))
            ->when($term, fn (Builder $q) => $q->where(fn (Builder $sub) => $sub
                ->whereLike('name', "%{$term}%")
                ->orWhereLike($this->jsonAsText($sub, 'effects'), "%{$term}%")))
            ->orderBy('name')
            ->limit(min($limit, 100))
            ->get()
            ->map(fn (ParagonGlyph $glyph) => [
                'sno_id' => $glyph->sno_id,
                'name' => $glyph->name,
                'class' => $glyph->class_name,
                'effects' => $glyph->effects,
                'is_released' => $glyph->is_released,
            ])
            ->all());
    }

    /** @return array<string, mixed> */
    protected function summarizeBoard(ParagonBoard $board): array
    {
        $cells = collect($board->grid)->flatten(1)->filter();

        return [
            'sno_id' => $board->sno_id,
            'name' => $board->name,
            'class' => $board->class_name,
            'width' => $board->raw['width'] ?? count($board->grid),
            'node_count' => $cells->count(),
            'socket_count' => $cells->where('has_socket', true)->count(),
            'gate_count' => $cells->where('is_gate', true)->count(),
            'rarity_counts' => $cells->countBy(fn (array $cell) => $cell['rarity'] ?? 'unknown')->all(),
            'is_released' => $board->is_released,
        ];
    }

    /**
     * jsonb columns are not scannable with LIKE on Postgres; cast them first.
     */
    protected function jsonAsText(Builder $query, string $column): Expression|string
    {
        return $query->getConnection()->getDriverName() === 'pgsql'
            ? DB::raw("{$column}::text")
            : $column;
    }
}
