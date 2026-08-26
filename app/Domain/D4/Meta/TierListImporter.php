<?php

namespace App\Domain\D4\Meta;

use App\Models\D4\MetaBuild;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Imports Maxroll's editorial Diablo IV endgame tier list.
 *
 * The page is a server-rendered Remix app: the whole route payload is inlined
 * as `window.__remixContext = {...};` in a plain <script> tag, and the tier
 * list itself is a Gutenberg block inside the post:
 *
 *   state.loaderData.<route>.post.gutenbergBlock[] where blockName is
 *   "maxroll/tierlist" -> attributes.items[] (name, tier, icon, link, ...)
 *
 * This is editorial data (author rankings), not telemetry: Diablo IV has no
 * public build-usage statistics and no economy data at all.
 */
class TierListImporter
{
    public const SOURCE = 'maxroll';

    protected const CONTEXT_MARKER = 'window.__remixContext';

    protected const BLOCK_NAME = 'maxroll/tierlist';

    /**
     * Rows older than this are considered stale; a plain (non-forced) import of
     * fresher data skips the network entirely.
     */
    protected const FRESH_FOR_HOURS = 12;

    /**
     * @return array{count: int, season: string|null, fetched_at: CarbonInterface|null, fetched: bool}
     */
    public function import(bool $force = false): array
    {
        if (! $force && $this->isFresh()) {
            return $this->currentState(fetched: false);
        }

        $url = (string) config('games.diablo-4.tierlist_url');

        $response = Http::withHeaders(['User-Agent' => config('games.diablo-4.user_agent')])
            ->timeout(60)
            ->retry(2, 5000, throw: false)
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException("Maxroll tier list request failed (HTTP {$response->status()}) for {$url}.");
        }

        $payload = $this->extractContext($response->body(), $url);
        $post = $this->locatePost($payload, $url);
        $season = $this->season($post);
        $builds = $this->parseBuilds($post, $url);

        $this->replace($builds, $season, now());

        return $this->currentState(fetched: true);
    }

    protected function isFresh(): bool
    {
        $freshest = MetaBuild::where('source', self::SOURCE)->max('fetched_at');

        return $freshest !== null && Date::parse($freshest)->gt(now()->subHours(self::FRESH_FOR_HOURS));
    }

    /**
     * @return array{count: int, season: string|null, fetched_at: CarbonInterface|null, fetched: bool}
     */
    protected function currentState(bool $fetched): array
    {
        $rows = MetaBuild::where('source', self::SOURCE);
        $freshest = (clone $rows)->max('fetched_at');

        return [
            'count' => (clone $rows)->count(),
            'season' => (clone $rows)->value('season'),
            'fetched_at' => $freshest === null ? null : Date::parse($freshest),
            'fetched' => $fetched,
        ];
    }

    /**
     * Pulls the inlined Remix loader payload out of the HTML document.
     *
     * @return array<string, mixed>
     */
    protected function extractContext(string $html, string $url): array
    {
        $marker = strpos($html, self::CONTEXT_MARKER);

        if ($marker === false) {
            throw new RuntimeException('Maxroll tier list page has no embedded '.self::CONTEXT_MARKER." payload ({$url}); the page structure changed.");
        }

        $start = strpos($html, '{', $marker);
        $end = $start === false ? false : strpos($html, '</script>', $start);

        if ($start === false || $end === false) {
            throw new RuntimeException("Maxroll tier list payload is not enclosed in a script tag ({$url}); the page structure changed.");
        }

        $json = rtrim(trim(substr($html, $start, $end - $start)), ';');
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            throw new RuntimeException("Maxroll tier list payload is not valid JSON ({$url}): ".json_last_error_msg().'.');
        }

        return $decoded;
    }

    /**
     * The loader key is the Remix route id ("branch-posts" today), so find the
     * first loader entry that actually carries Gutenberg blocks instead of
     * hard-coding it.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function locatePost(array $payload, string $url): array
    {
        $loaders = $payload['state']['loaderData'] ?? null;

        if (! is_array($loaders)) {
            throw new RuntimeException("Maxroll tier list payload has no state.loaderData ({$url}); the page structure changed.");
        }

        foreach ($loaders as $loader) {
            if (is_array($loader) && isset($loader['post']['gutenbergBlock']) && is_array($loader['post']['gutenbergBlock'])) {
                return $loader['post'];
            }
        }

        throw new RuntimeException("Maxroll tier list payload has no post with gutenbergBlock ({$url}); the page structure changed.");
    }

    /**
     * Season comes from the post's own tags (e.g. "season-14-death-awakening").
     *
     * @param  array<string, mixed>  $post
     */
    protected function season(array $post): ?string
    {
        foreach ($post['tags'] ?? [] as $tag) {
            $slug = is_array($tag) ? ($tag['slug'] ?? null) : null;

            if (is_string($slug) && str_starts_with($slug, 'season-')) {
                return $slug;
            }
        }

        foreach ($post['taxonomies']['postTag'] ?? [] as $name) {
            if (is_string($name) && Str::startsWith(Str::lower($name), 'season')) {
                return Str::slug($name);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $post
     * @return list<array<string, mixed>>
     */
    protected function parseBuilds(array $post, string $url): array
    {
        $builds = [];

        foreach ($post['gutenbergBlock'] as $block) {
            if (! is_array($block) || ($block['blockName'] ?? null) !== self::BLOCK_NAME) {
                continue;
            }

            foreach ($block['attributes']['items'] ?? [] as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $name = is_string($item['name'] ?? null) ? trim($item['name']) : '';
                $tier = is_string($item['tier'] ?? null) ? trim($item['tier']) : '';

                if ($name === '' || $tier === '') {
                    continue;
                }

                $builds[] = [
                    'name' => $name,
                    'class_name' => $this->className($item['icon'] ?? null),
                    'tier' => Str::upper($tier),
                    'tags' => $this->tags($item),
                    'guide_url' => is_string($item['link'] ?? null) && $item['link'] !== '' ? $item['link'] : null,
                    'raw' => $item,
                ];
            }
        }

        if ($builds === []) {
            throw new RuntimeException("Maxroll tier list parsed 0 builds from {$url}; the page structure changed. Existing rows were left untouched.");
        }

        return $builds;
    }

    /**
     * Icons are slugs like "d4/barbarian"; that is the only class marker each
     * item carries.
     */
    protected function className(mixed $icon): ?string
    {
        if (! is_string($icon) || ! str_contains($icon, '/')) {
            return null;
        }

        $slug = Str::afterLast($icon, '/');

        return $slug === '' ? null : Str::title(str_replace(['-', '_'], ' ', $slug));
    }

    /**
     * Flags plus the tier-movement indicator, which is either a bare string or
     * a {value, label} object depending on how the entry was last edited.
     *
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    protected function tags(array $item): array
    {
        $tags = [];

        foreach (['isNew' => 'new', 'isBoss' => 'boss', 'isGift' => 'gift'] as $flag => $tag) {
            if (($item[$flag] ?? false) === true) {
                $tags[] = $tag;
            }
        }

        $indicator = $item['tierIndicator'] ?? null;
        $indicator = is_array($indicator) ? ($indicator['value'] ?? null) : $indicator;

        if (is_string($indicator) && $indicator !== '' && $indicator !== 'none') {
            $tags[] = Str::slug(Str::snake(Str::after($indicator, 'is')));
        }

        return array_values(array_unique($tags));
    }

    /**
     * Replaces this source's rows wholesale: a tier list is a snapshot, so an
     * entry dropped upstream must disappear here too. The delete and the insert
     * share a transaction, and parseBuilds() has already refused an empty set,
     * so good rows are never traded for nothing.
     *
     * @param  list<array<string, mixed>>  $builds
     */
    protected function replace(array $builds, ?string $season, CarbonInterface $fetchedAt): void
    {
        DB::transaction(function () use ($builds, $season, $fetchedAt): void {
            MetaBuild::where('source', self::SOURCE)->delete();

            foreach (array_chunk($builds, 100) as $chunk) {
                MetaBuild::insert(array_map(fn (array $build): array => [
                    'source' => self::SOURCE,
                    'season' => $season,
                    'name' => $build['name'],
                    'class_name' => $build['class_name'],
                    'tier' => $build['tier'],
                    'tags' => json_encode($build['tags']),
                    'guide_url' => $build['guide_url'],
                    'raw' => json_encode($build['raw']),
                    'fetched_at' => $fetchedAt,
                    'created_at' => $fetchedAt,
                    'updated_at' => $fetchedAt,
                ], $chunk));
            }
        });
    }
}
