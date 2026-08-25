<?php

namespace App\Http\Controllers;

use App\Domain\Builds\BuildHubQuery;
use App\Domain\Seo\PageMeta;
use App\Models\Build;
use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * `/my-builds` (scope §3.6). There is no separate dashboard: this is the
 * signed-in view of the same builds everyone else sees, plus the drafts.
 */
class MyBuildsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $builds = Build::query()
            ->where('user_id', $user->id)
            ->with(['user:id,name,handle', 'game:id,slug', 'gameVersion:id,version'])
            ->latest('updated_at')
            ->get();

        return Inertia::render('MyBuilds', [
            new PageMeta(title: 'My builds', noindex: true),
            'groups' => $this->groups($builds),
            'stats' => [
                'published' => $builds->where('visibility', Build::VISIBILITY_PUBLIC)->count(),
                'drafts' => $builds->where('visibility', Build::VISIBILITY_DRAFT)->count(),
                'endorsements' => (int) $builds->sum('endorsements_count'),
                'member_since' => $user->created_at?->format('M Y'),
            ],
            'handle' => $user->handle ?? $user->name,
        ]);
    }

    /**
     * Grouped by game in the games' display order, drafts pinned to the top of
     * each group. Live games the user has no builds for still get a group, so
     * the page can invite them to publish; queued games only appear when they
     * already hold a build.
     *
     * @param  Collection<int, Build>  $builds
     * @return list<array<string, mixed>>
     */
    protected function groups(Collection $builds): array
    {
        $byGame = $builds->groupBy('game_id');

        return Game::query()
            ->where(fn ($query) => $query->where('is_live', true)->orWhereIn('id', $byGame->keys()))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Game $game) => [
                'game' => [
                    'slug' => $game->slug,
                    'name' => $game->name,
                    'short_name' => $game->short_name ?? $game->name,
                    'accent' => $game->accent,
                    'icon' => $game->icon,
                    'is_live' => $game->is_live,
                    'url' => route('games.show', $game->slug),
                ],
                // The query already ordered by updated_at desc and PHP's sort
                // is stable, so this only lifts the drafts to the top.
                'builds' => BuildHubQuery::cards(
                    ($byGame->get($game->id) ?? collect())
                        ->sortBy(fn (Build $build) => $build->isDraft() ? 0 : 1)
                        ->values(),
                ),
            ])
            ->values()
            ->all();
    }
}
