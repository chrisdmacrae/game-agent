<?php

namespace App\Http\Middleware;

use App\Models\Game;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'seo' => [
                'url' => $request->url(),
            ],
            'auth' => [
                'user' => $this->user($request->user()),
            ],
            'games' => fn () => $this->games(),
            'mcpUrl' => route('mcp.poe2'),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * The topbar game switcher. Every page needs it, so it stays deliberately
     * small: presentation columns only, in display order.
     *
     * @return list<array<string, mixed>>
     */
    protected function games(): array
    {
        return Game::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['slug', 'name', 'short_name', 'is_live', 'accent', 'icon'])
            ->map(fn (Game $game) => [
                'slug' => $game->slug,
                'name' => $game->name,
                'short_name' => $game->short_name ?? $game->name,
                'is_live' => $game->is_live,
                'accent' => $game->accent,
                'icon' => $game->icon,
                'url' => route('games.show', $game->slug),
            ])
            ->all();
    }

    /**
     * The signed-in user, trimmed to what the shell renders.
     *
     * @return array<string, mixed>|null
     */
    protected function user(mixed $user): ?array
    {
        if (! $user instanceof User) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'handle' => $user->handle,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
        ];
    }
}
