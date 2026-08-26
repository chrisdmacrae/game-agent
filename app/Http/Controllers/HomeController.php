<?php

namespace App\Http\Controllers;

use App\Domain\Games\ModelDocRepository;
use App\Domain\Poe2\Queries\MetaQuery;
use App\Domain\Seo\PageMeta;
use App\Mcp\Servers\D4Server;
use App\Mcp\Servers\Poe2Server;
use App\Models\Build;
use App\Models\Game;
use App\Models\GameVersion;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use ReflectionClass;
use Throwable;

class HomeController extends Controller
{
    /**
     * Game slug → MCP server class. A live game appears in the homepage
     * toolkit list only when it is mapped here.
     *
     * @var array<string, class-string>
     */
    protected const array SERVERS = [
        'poe2' => Poe2Server::class,
        'diablo-4' => D4Server::class,
    ];

    public function __invoke(Request $request, ModelDocRepository $docs): Response
    {
        try {
            $meta = app(MetaQuery::class)->context();
        } catch (Throwable) {
            $meta = null; // No data imported yet.
        }

        return Inertia::render('Welcome', [
            new PageMeta(
                title: 'Theorycraft with your assistant, publish for everyone else',
                description: 'An MCP server that connects Claude or ChatGPT to real Path of Exile 2 game data. Ask for a build, get numbers back, and publish it for everyone else.',
                ogImage: route('og-image'),
            ),
            'meta' => $meta,
            'mcpUrl' => route('mcp.poe2'),
            'gameCards' => $this->gameCards(),
            'stats' => $this->stats(),
            'toolkits' => $this->toolkits($docs),
        ]);
    }

    /**
     * The game grid (scope §3.1). Live games show how many builds are
     * published; queued games show how many people voted for them.
     *
     * @return list<array<string, mixed>>
     */
    protected function gameCards(): array
    {
        return Game::query()
            ->withCount([
                'builds as builds_count' => fn ($query) => $query->where('visibility', Build::VISIBILITY_PUBLIC),
                'votes as votes_count',
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Game $game) => [
                'slug' => $game->slug,
                'name' => $game->name,
                'short_name' => $game->short_name ?? $game->name,
                'accent' => $game->accent,
                'icon' => $game->icon,
                'is_live' => $game->is_live,
                'description' => $game->description,
                'url' => route('games.show', $game->slug),
                'builds' => $game->is_live ? (int) $game->builds_count : null,
                'votes' => $game->is_live ? null : (int) $game->votes_count,
            ])
            ->all();
    }

    /**
     * The hero stat strip: builds published, games live, the PoE 2 patch the
     * data was imported from, and when that import ran.
     *
     * @return array{builds_published: int, games_live: int, patch: string|null, data_refreshed_at: string|null}
     */
    protected function stats(): array
    {
        $version = GameVersion::query()
            ->whereRelation('game', 'slug', 'poe2')
            ->where('is_active', true)
            ->latest('imported_at')
            ->first();

        return [
            'builds_published' => Build::query()->public()->count(),
            'games_live' => Game::query()->where('is_live', true)->count(),
            'patch' => $version?->version,
            'data_refreshed_at' => $version?->imported_at?->toIso8601String(),
        ];
    }

    /**
     * One toolkit per live game: the MCP tools its server registers and how
     * many game-model documents it serves. Each endpoint has its own toolset,
     * so the section renders them per game instead of one flat list.
     *
     * @return list<array{slug: string, name: string, short_name: string, tools: list<array{name: string, description: string}>, models: int}>
     */
    protected function toolkits(ModelDocRepository $docs): array
    {
        return Game::query()
            ->where('is_live', true)
            ->whereIn('slug', array_keys(static::SERVERS))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Game $game) => [
                'slug' => $game->slug,
                'name' => $game->name,
                'short_name' => $game->short_name ?? $game->name,
                'tools' => $this->serverTools(static::SERVERS[$game->slug]),
                'models' => $docs->all($game->slug)->count(),
            ])
            ->all();
    }

    /**
     * @param  class-string  $server
     * @return list<array{name: string, description: string}>
     */
    protected function serverTools(string $server): array
    {
        $property = new ReflectionClass($server)->getProperty('tools');

        return collect($property->getDefaultValue())
            ->map(fn (string $class) => [
                'name' => app($class)->name(),
                'description' => app($class)->description(),
            ])
            ->values()
            ->all();
    }
}
