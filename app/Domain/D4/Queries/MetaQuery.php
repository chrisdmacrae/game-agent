<?php

namespace App\Domain\D4\Queries;

use App\Domain\D4\D4Context;
use App\Domain\D4\Meta\TierListImporter;
use App\Models\D4\Affix;
use App\Models\D4\Aspect;
use App\Models\D4\CharacterClass;
use App\Models\D4\ItemType;
use App\Models\D4\MetaBuild;
use App\Models\D4\ParagonBoard;
use App\Models\D4\ParagonGlyph;
use App\Models\D4\Skill;
use App\Models\D4\UniqueItem;
use Illuminate\Support\Collection;

class MetaQuery
{
    use CachesQueryResults;

    public function __construct(protected D4Context $context) {}

    /**
     * The patch half of the context is cached per game version; the tier list
     * half is read live, because it is refreshed on its own weekly schedule and
     * a version-keyed cache would keep serving last season's rankings.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->remember(__FUNCTION__, [], fn () => $this->uncachedContext())
            + ['meta' => $this->tierList()];
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
                'There is no telemetry-based meta for Diablo IV either — no public build-usage or ladder statistics exist. The only meta signal here is the editorial tier list under "meta", which is a group of authors\' opinions, so attribute it as such and never present it as measured popularity.',
                'Unreleased/PTR/test content is imported but hidden; listings are released-only unless a tool is called with include_unreleased.',
                'Skill, affix, aspect and unique text is raw game text and still contains formula tokens ({c_*}, {if:...}, {SF_N}, {payload:...}, [X|%|]). Those placeholders are NOT evaluated here, so no concrete damage or percentage number can be read out of them.',
                'Read the game model documents first (list_game_models / get_game_model): Diablo IV stacks most modifiers additively inside damage buckets, so Path of Exile style multiplicative reasoning gives wrong answers.',
            ],
        ];
    }

    /**
     * The imported editorial tier list, grouped by tier. Returns an explicit
     * "not ingested" marker rather than erroring when d4:meta has never run.
     *
     * @return array<string, mixed>
     */
    protected function tierList(): array
    {
        $url = (string) config('games.diablo-4.tierlist_url');

        $builds = MetaBuild::where('source', TierListImporter::SOURCE)
            ->orderBy('id')
            ->get();

        if ($builds->isEmpty()) {
            return [
                'status' => 'not_ingested',
                'note' => "No tier list data has been ingested yet (run `d4:meta`), so no meta ranking can be given for Diablo IV. Do not guess one. Source when ingested: {$url}.",
            ];
        }

        $fetchedAt = $builds->max('fetched_at');

        return [
            'status' => 'ingested',
            'source' => 'Maxroll — Overall Endgame Builds Tier List',
            'source_url' => $url,
            'season' => $builds->first()->season,
            'fetched_at' => $fetchedAt?->toIso8601String(),
            'attribution' => sprintf(
                'editorial tier list data from Maxroll (%s), fetched %s',
                $url,
                $fetchedAt?->toDateString() ?? 'unknown',
            ),
            'note' => 'Author rankings for the current season, not telemetry and not a simulation. Cite Maxroll when you use them, and treat tier X as "currently bugged" rather than as a rank.',
            'tiers' => $this->buildsByTier($builds),
        ];
    }

    /**
     * @param  Collection<int, MetaBuild>  $builds
     * @return array<string, list<array{name: string, class: string|null, guide_url: string|null}>>
     */
    protected function buildsByTier(Collection $builds): array
    {
        return $builds
            ->groupBy('tier')
            ->sortKeys()
            ->map(fn (Collection $group) => $group->map(fn (MetaBuild $build) => [
                'name' => $build->name,
                'class' => $build->class_name,
                'guide_url' => $build->guide_url,
            ])->values()->all())
            ->all();
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
