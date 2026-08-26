<?php

namespace App\Http\Controllers;

use App\Domain\Builds\BuildHubQuery;
use App\Domain\Builds\BuildStage;
use App\Domain\Builds\GameBuildProfile;
use App\Domain\Builds\GameReference;
use App\Domain\Seo\PageMeta;
use App\Models\Build;
use App\Models\Game;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * `/{game}`: the hub for a live game, the waitlist for a queued one.
 */
class GameHubController extends Controller
{
    public function __invoke(Request $request, Game $game, GameReference $reference): Response
    {
        return $game->is_live
            ? $this->hub($request, $game, $reference)
            : $this->waitlist($game);
    }

    /**
     * Scope §3.4. Filtering and sorting happen in the database, never in the
     * page: the list is the whole point of the hub.
     */
    protected function hub(Request $request, Game $game, GameReference $reference): Response
    {
        $profile = GameBuildProfile::for($game);
        $version = $game->activeVersion();

        $query = new BuildHubQuery($game, $this->filters($request), $version?->id);

        // Read the filters back off the query: it has already dropped anything
        // this game's rail does not offer.
        $filters = $query->filters();

        $builds = $query->results()
            ->with(['user:id,name,handle', 'game:id,slug', 'gameVersion:id,version'])
            ->limit(60)
            ->get();

        return Inertia::render('Games/Hub', [
            new PageMeta(
                title: "{$game->name} builds",
                description: $game->description ?? "Published {$game->name} builds, filterable by class, ascendancy, stage and budget.",
                ogImage: route('og-image'),
            ),
            'game' => $this->gameProps($game),
            'patch' => $version?->version,
            'builds' => BuildHubQuery::cards($builds),
            'filters' => $filters,
            'filterRail' => $profile->hubFilters(),
            'view' => $this->view($request),
            'facets' => [
                'classes' => $query->classFacets(),
            ],
            'options' => [
                'classes' => $reference->classes($game),
                'ascendancies' => $reference->ascendancies($game, $filters['classes']),
                'stages' => $reference->stages(),
                'sorts' => $profile->hubSorts(),
            ],
            'yourBuilds' => $request->user() === null ? [] : BuildHubQuery::cards(
                Build::query()
                    ->where('user_id', $request->user()->id)
                    ->where('game_id', $game->id)
                    ->with(['user:id,name,handle', 'game:id,slug', 'gameVersion:id,version'])
                    ->latest('updated_at')
                    ->limit(3)
                    ->get(),
            ),
        ]);
    }

    /**
     * Scope §3.5. Queued games collect votes and show their place in line.
     */
    protected function waitlist(Game $game): Response
    {
        $queue = Game::query()
            ->where('is_live', false)
            ->withCount('votes')
            ->get()
            ->sortByDesc(fn (Game $queued) => [$queued->votes_count, -$queued->sort_order])
            ->values();

        $position = $queue->search(fn (Game $queued) => $queued->id === $game->id);

        return Inertia::render('Games/Waitlist', [
            new PageMeta(
                title: "{$game->name} is not live yet",
                description: "{$game->name} is not wired up yet. Vote to move it up the queue and get told when it lands.",
                ogImage: route('og-image'),
            ),
            'game' => $this->gameProps($game),
            'votes' => $game->votes()->count(),
            'queuePosition' => $position === false ? null : $position + 1,
            'patch' => $game->activeVersion()?->version,
            'queue' => $queue
                ->map(fn (Game $queued, int $index) => [
                    'slug' => $queued->slug,
                    'name' => $queued->name,
                    'short_name' => $queued->short_name ?? $queued->name,
                    'accent' => $queued->accent,
                    'icon' => $queued->icon,
                    'description' => $queued->description,
                    'votes' => $queued->votes_count,
                    'position' => $index + 1,
                ])
                ->all(),
        ]);
    }

    /**
     * Query-string filters, normalised rather than validated: a hub with a
     * junk parameter renders the default list instead of erroring. Which of
     * them the game actually offers is BuildHubQuery::gate()'s call.
     *
     * @return array<string, mixed>
     */
    protected function filters(Request $request): array
    {
        $stage = $this->stringOrNull($request->query('stage'));

        return [
            'classes' => $this->stringList($request->query('classes')),
            'ascendancy' => $this->stringOrNull($request->query('ascendancy')),
            'stage' => in_array($stage, BuildStage::values(), true) ? $stage : null,
            'min_divine' => $this->numberOrNull($request->query('min_divine')),
            'max_divine' => $this->numberOrNull($request->query('max_divine')),
            'current_patch_only' => $request->boolean('current_patch_only'),
            'hardcore_viable' => $request->boolean('hardcore_viable'),
            'sort' => (string) $request->query('sort', 'updated'),
        ];
    }

    protected function view(Request $request): string
    {
        return $request->query('view') === 'list' ? 'list' : 'grid';
    }

    /** @return array<string, mixed> */
    protected function gameProps(Game $game): array
    {
        return [
            'slug' => $game->slug,
            'name' => $game->name,
            'short_name' => $game->short_name ?? $game->name,
            'accent' => $game->accent,
            'icon' => $game->icon,
            'description' => $game->description,
            'is_live' => $game->is_live,
        ];
    }

    /** @return list<string> */
    protected function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            $value = $value === null ? [] : [$value];
        }

        return array_values(array_filter(
            array_map(fn (mixed $item) => is_scalar($item) ? mb_substr((string) $item, 0, 50) : '', $value),
            fn (string $item) => $item !== '',
        ));
    }

    protected function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? mb_substr($value, 0, 50) : null;
    }

    protected function numberOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
