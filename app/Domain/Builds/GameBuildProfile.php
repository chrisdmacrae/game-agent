<?php

namespace App\Domain\Builds;

use App\Domain\D4\Calc\ComputedStats;
use App\Domain\D4\D4BuildPageEnricher;
use App\Domain\D4\D4BuildPayload;
use App\Domain\D4\D4PublishChecks;
use App\Domain\D4\Validation\D4BuildRules;
use App\Domain\D4\Validation\D4BuildValidator;
use App\Domain\Poe2\BuildPageEnricher;
use App\Domain\Poe2\Poe2PublishChecks;
use App\Domain\Poe2\Validation\BuildRules;
use App\Domain\Poe2\Validation\BuildValidator;
use App\Models\Build;
use App\Models\D4\CharacterClass as D4CharacterClass;
use App\Models\D4\ParagonBoard;
use App\Models\Game;
use App\Models\Poe2\Ascendancy;
use App\Models\Poe2\CharacterClass;

/**
 * How one game's builds are validated, normalised, pre-flighted and rendered.
 *
 * Build anatomy is per-game — PoE 2 has support gems, spirit and a passive
 * tree; Diablo IV has an action bar, paragon boards and a keyed gear map — so
 * every place that used to reach straight for the PoE2 classes now asks the
 * profile for the game it is working on. Two games is not a registry: the
 * profile matches on slug, and anything that is not Diablo IV is PoE 2, which
 * keeps the pre-existing behaviour for legacy rows and for the game factories
 * that mint throwaway slugs.
 */
class GameBuildProfile
{
    public const POE2 = 'poe2';

    public const D4 = 'diablo-4';

    protected function __construct(public readonly string $slug) {}

    public static function for(Game|string|null $game): self
    {
        $slug = $game instanceof Game ? $game->slug : $game;

        return new self($slug === self::D4 ? self::D4 : self::POE2);
    }

    public static function forBuild(Build $build): self
    {
        return self::for($build->game);
    }

    public function isD4(): bool
    {
        return $this->slug === self::D4;
    }

    public function isPoe2(): bool
    {
        return ! $this->isD4();
    }

    /**
     * The payload validation rules, shared with this game's save_build tool.
     *
     * @return array<string, mixed>
     */
    public function rules(string $prefix = ''): array
    {
        return $this->isD4()
            ? D4BuildRules::rules($prefix)
            : BuildRules::rules($prefix);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalize(array $payload): array
    {
        return $this->isD4()
            ? D4BuildPayload::normalize($payload)
            : BuildPayload::normalize($payload);
    }

    /**
     * Normalize a payload for saving and, where the game has a stat
     * calculator, fold its computed dps/ehp/stat rows in. Every path that
     * persists `build` should come through here rather than normalize().
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function finalize(array $payload, ?int $versionId = null): array
    {
        $payload = $this->normalize($payload);

        return $this->isD4() ? ComputedStats::apply($payload, $versionId) : $payload;
    }

    /**
     * The heuristic validator for this game. Both games' validators expose the
     * same `validate(array $definition): array` contract.
     */
    public function validator(): BuildValidator|D4BuildValidator
    {
        return $this->isD4()
            ? app(D4BuildValidator::class)
            : app(BuildValidator::class);
    }

    /**
     * The game-specific half of the publish pre-flight; the stats and patch
     * checks around it are game-agnostic and stay in PublishChecklist.
     *
     * @return list<array{key: string, label: string, passed: bool, detail: string|null}>
     */
    public function publishChecks(Build $build): array
    {
        $payload = $build->build ?? [];

        return $this->isD4()
            ? new D4PublishChecks()->checks($payload)
            : new Poe2PublishChecks()->checks($payload);
    }

    /** @return list<string> */
    public function tiers(): array
    {
        return $this->isD4() ? D4BuildRules::TIERS : BuildRules::TIERS;
    }

    /**
     * The hub's filter rail for this game, in the order it is drawn. The page
     * renders these generically — `type` picks the control, `params` names the
     * query-string keys the control owns, and `options` names the list in the
     * hub's `options` prop it reads its choices from — so adding a game means
     * editing this method and nothing in the Vue.
     *
     * A Diablo IV character has no second class layer and no divine-orb
     * economy, so the ascendancy select and the budget inputs are not on
     * offer; hardcore is left off deliberately too, even though the game has
     * the mode.
     *
     * @return list<array{key: string, label: string, type: string, params: list<string>, options: string|null, placeholder: string|null, fields: list<array{param: string, placeholder: string, label: string}>}>
     */
    public function hubFilters(): array
    {
        $classes = $this->hubFilter('classes', 'Class', 'checkboxes', ['classes'], options: 'classes');
        $stage = $this->hubFilter('stage', 'Game stage', 'radio', ['stage'], options: 'stages', placeholder: 'Any stage');
        $patch = $this->hubFilter('current_patch_only', 'Current patch only', 'toggle', ['current_patch_only']);

        if ($this->isD4()) {
            return [$classes, $stage, $patch];
        }

        return [
            $classes,
            $this->hubFilter('ascendancy', 'Ascendancy', 'select', ['ascendancy'], options: 'ascendancies', placeholder: 'Any ascendancy'),
            $stage,
            $this->hubFilter('budget', 'Budget', 'number_range', ['min_divine', 'max_divine'], fields: [
                ['param' => 'min_divine', 'placeholder' => 'Min div', 'label' => 'Minimum divine'],
                ['param' => 'max_divine', 'placeholder' => 'Max div', 'label' => 'Maximum divine'],
            ]),
            $patch,
            $this->hubFilter('hardcore_viable', 'Hardcore viable', 'toggle', ['hardcore_viable']),
        ];
    }

    /**
     * Every query-string key the rail offers; anything else is dropped from a
     * hub request rather than filtering the list — see BuildHubQuery::gate().
     *
     * @return list<string>
     */
    public function hubFilterParams(): array
    {
        return array_values(array_merge(
            ...array_map(fn (array $filter) => $filter['params'], $this->hubFilters()),
        ));
    }

    /**
     * The sorts the hub offers. Cheapest-first is a divine-orb sort, so it is
     * PoE 2 only.
     *
     * @return list<string>
     */
    public function hubSorts(): array
    {
        return $this->isD4()
            ? ['updated', 'endorsements', 'dps']
            : BuildHubQuery::SORTS;
    }

    /**
     * @param  list<string>  $params
     * @param  list<array{param: string, placeholder: string, label: string}>  $fields
     * @return array{key: string, label: string, type: string, params: list<string>, options: string|null, placeholder: string|null, fields: list<array{param: string, placeholder: string, label: string}>}
     */
    protected function hubFilter(
        string $key,
        string $label,
        string $type,
        array $params,
        ?string $options = null,
        ?string $placeholder = null,
        array $fields = [],
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'params' => $params,
            'options' => $options,
            'placeholder' => $placeholder,
            'fields' => $fields,
        ];
    }

    /**
     * The class roster the editor and the hub filter rail offer.
     *
     * @return list<string>
     */
    public function classes(?int $versionId): array
    {
        if ($versionId === null) {
            return [];
        }

        if ($this->isD4()) {
            return D4CharacterClass::query()
                ->forVersion($versionId)
                ->released()
                ->orderBy('name')
                ->pluck('name')
                ->all();
        }

        return CharacterClass::query()
            ->forVersion($versionId)
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    /**
     * Ascendancies, optionally narrowed to the selected classes. A Diablo IV
     * character has no second class layer, so the list is empty rather than
     * borrowed from PoE 2.
     *
     * @param  list<string>  $classes
     * @return list<array{name: string, class_name: string|null}>
     */
    public function ascendancies(?int $versionId, array $classes = []): array
    {
        if ($versionId === null || $this->isD4()) {
            return [];
        }

        return Ascendancy::query()
            ->forVersion($versionId)
            ->when($classes !== [], fn ($query) => $query->whereIn('class_name', $classes))
            ->orderBy('name')
            ->get(['name', 'class_name'])
            ->map(fn (Ascendancy $ascendancy) => [
                'name' => $ascendancy->name,
                'class_name' => $ascendancy->class_name,
            ])
            ->all();
    }

    /**
     * The hover-card dictionary and gear view the build page renders. Each
     * game has its own enricher; the D4 one carries no gear view or
     * ascendancy paths, so those keys come back empty for it and the page
     * shell renders without special-casing a missing prop.
     *
     * @return array{entities: array<string, mixed>, ascendancy_path_ids: list<int>, gear_view: array{slots: array<string, mixed>, jewels: list<mixed>}, guide_html: string|null}
     */
    public function enrich(Build $build, ?string $guideHtml): array
    {
        if ($this->isD4()) {
            $enriched = app(D4BuildPageEnricher::class)->enrich($build, $guideHtml);

            return [
                'entities' => $enriched['entities'],
                'ascendancy_path_ids' => [],
                'gear_view' => ['slots' => [], 'jewels' => []],
                'guide_html' => $enriched['guide_html'],
            ];
        }

        return app(BuildPageEnricher::class)->enrich($build, $guideHtml);
    }

    /**
     * The assets each game's allocation renderer needs. PoE 2 gets the passive
     * tree sprite and render data; Diablo IV gets those keys empty and the
     * grids of the paragon boards its build actually attaches.
     *
     * @return array{spriteUrl: string|null, treeUrl: string|null, ascendancyKey: string|null, paragonBoards: list<array{name: string, class_name: string|null, grid: array<int, mixed>}>}
     */
    public function treeProps(Build $build): array
    {
        if ($this->isD4()) {
            return [
                'spriteUrl' => null,
                'treeUrl' => null,
                'ascendancyKey' => null,
                'paragonBoards' => $this->paragonBoards($build),
            ];
        }

        return [
            'spriteUrl' => asset('games/poe2/tree/skills.webp'),
            'treeUrl' => is_file(public_path('games/poe2/tree/render.json'))
                ? asset('games/poe2/tree/render.json')
                : null,
            'ascendancyKey' => isset($build->build['ascendancy'])
                ? Ascendancy::forVersion($build->game_version_id ?? 0)
                    ->whereLike('name', $build->build['ascendancy'])
                    ->value('key')
                : null,
            'paragonBoards' => [],
        ];
    }

    /**
     * The grids of the boards this build attaches, and only those: a full board
     * table is megabytes of cells the page would never draw. Boards the build
     * names but the imported data does not have simply do not come back, and
     * the page renders them as a labelled card instead.
     *
     * @return list<array{name: string, class_name: string|null, grid: array<int, mixed>}>
     */
    protected function paragonBoards(Build $build): array
    {
        $names = collect(is_array($build->build['paragon'] ?? null) ? $build->build['paragon'] : [])
            ->pluck('board')
            ->filter(fn (mixed $name) => is_string($name) && $name !== '')
            ->unique()
            ->values();

        if ($names->isEmpty() || $build->game_version_id === null) {
            return [];
        }

        return ParagonBoard::query()
            ->forVersion($build->game_version_id)
            ->whereIn('name', $names->all())
            ->get(['name', 'class_name', 'grid'])
            ->map(fn (ParagonBoard $board) => [
                'name' => $board->name,
                'class_name' => $board->class_name,
                'grid' => is_array($board->grid) ? $board->grid : [],
            ])
            ->all();
    }

    /**
     * Path of Building and the build-planner file are PoE 2 formats; the
     * export endpoints 404 for every other game.
     */
    public function exportsToPathOfBuilding(): bool
    {
        return $this->isPoe2();
    }

    /**
     * The product kicker printed across the top of a build's share card.
     */
    public function ogKicker(): string
    {
        return $this->isD4() ? 'D4 Theorycrafter' : 'PoE2 Theorycrafter';
    }
}
