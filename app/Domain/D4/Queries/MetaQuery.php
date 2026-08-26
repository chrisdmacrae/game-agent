<?php

namespace App\Domain\D4\Queries;

use App\Domain\D4\D4Context;
use App\Models\D4\Affix;
use App\Models\D4\Aspect;
use App\Models\D4\CharacterClass;
use App\Models\D4\ItemType;
use App\Models\D4\ParagonBoard;
use App\Models\D4\ParagonGlyph;
use App\Models\D4\Skill;
use App\Models\D4\UniqueItem;

class MetaQuery
{
    use CachesQueryResults;

    public function __construct(protected D4Context $context) {}

    /** @return array<string, mixed> */
    public function context(): array
    {
        return $this->remember(__FUNCTION__, [], fn () => $this->uncachedContext());
    }

    /** @return array<string, mixed> */
    protected function uncachedContext(): array
    {
        $version = $this->context->version();

        return [
            'game' => 'Diablo IV',
            'data_version' => $version->version,
            'data_imported_at' => $version->imported_at?->toIso8601String(),
            'data_fingerprint' => $version->fingerprint,
            'classes' => $this->classNames(),
            'dataset_counts' => $this->counts(),
            'data_sources' => [
                'game data' => 'datamined Diablo IV client files (DiabloTools/d4data dump)',
                'text' => 'the client string tables for the same patch',
            ],
            'notes' => [
                "The data reflects the imported client patch ({$version->version}). It does not carry a season name or season theme powers, so never claim which season is live — say the data is for this patch.",
                'There is no economy or trade data for Diablo IV: this toolkit cannot price items or report market values.',
                'Unreleased/PTR/test content is imported but hidden; listings are released-only unless a tool is called with include_unreleased.',
                'Skill, affix, aspect and unique text is raw game text and still contains formula tokens ({c_*}, {if:...}, {SF_N}, {payload:...}, [X|%|]). Those placeholders are NOT evaluated here, so no concrete damage or percentage number can be read out of them.',
                'Read the game model documents first (list_game_models / get_game_model): Diablo IV stacks most modifiers additively inside damage buckets, so Path of Exile style multiplicative reasoning gives wrong answers.',
            ],
        ];
    }

    /** @return list<string> */
    protected function classNames(): array
    {
        return CharacterClass::forVersion($this->context->versionId())
            ->released()
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    /** @return array<string, array{released: int, total: int}> */
    protected function counts(): array
    {
        $models = [
            'classes' => CharacterClass::class,
            'skills' => Skill::class,
            'paragon_boards' => ParagonBoard::class,
            'paragon_glyphs' => ParagonGlyph::class,
            'affixes' => Affix::class,
            'aspects' => Aspect::class,
            'uniques' => UniqueItem::class,
            'item_types' => ItemType::class,
        ];

        $counts = [];

        foreach ($models as $label => $model) {
            $counts[$label] = [
                'released' => $model::forVersion($this->context->versionId())->released()->count(),
                'total' => $model::forVersion($this->context->versionId())->count(),
            ];
        }

        return $counts;
    }
}
