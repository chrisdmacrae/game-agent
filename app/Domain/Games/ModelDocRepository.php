<?php

namespace App\Domain\Games;

use Illuminate\Support\Collection;

/**
 * Game model documents: curated markdown explanations of how a game works,
 * stored in content/games/{game}/models/*.md with simple frontmatter.
 * These teach an AI how to reason about the game; the database provides facts.
 */
class ModelDocRepository
{
    /** @return Collection<int, array{id: string, title: string, summary: string, body: string}> */
    public function all(string $game): Collection
    {
        $files = glob(base_path("content/games/{$game}/models/*.md")) ?: [];

        return collect($files)
            ->map(fn (string $path) => $this->parse($path))
            ->filter()
            ->sortBy('order')
            ->values();
    }

    /** @return array{id: string, title: string, summary: string, body: string}|null */
    public function find(string $game, string $id): ?array
    {
        return $this->all($game)->firstWhere('id', $id);
    }

    /**
     * Naive full-text search over titles, summaries, and bodies.
     *
     * @return Collection<int, array{id: string, title: string, summary: string, snippet: string}>
     */
    public function search(string $game, string $term, int $limit = 5): Collection
    {
        $needle = mb_strtolower($term);
        $words = array_filter(preg_split('/\s+/', $needle));

        return $this->all($game)
            ->map(function (array $doc) use ($words) {
                $haystack = mb_strtolower($doc['title'].' '.$doc['summary'].' '.$doc['body']);

                $score = 0;

                foreach ($words as $word) {
                    $score += substr_count($haystack, $word);
                }

                return $score > 0 ? array_merge($doc, ['score' => $score]) : null;
            })
            ->filter()
            ->sortByDesc('score')
            ->take($limit)
            ->map(fn (array $doc) => [
                'id' => $doc['id'],
                'title' => $doc['title'],
                'summary' => $doc['summary'],
                'snippet' => $this->snippet($doc['body'], $words),
            ])
            ->values();
    }

    /** @return array{id: string, title: string, summary: string, order: int, body: string}|null */
    protected function parse(string $path): ?array
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        $meta = [];
        $body = $contents;

        if (preg_match('/\A---\n(.*?)\n---\n(.*)\z/s', $contents, $m)) {
            foreach (explode("\n", $m[1]) as $line) {
                if (preg_match('/^([a-z_]+):\s*(.+)$/', trim($line), $kv)) {
                    $meta[$kv[1]] = trim($kv[2], " \"'");
                }
            }

            $body = trim($m[2]);
        }

        return [
            'id' => $meta['id'] ?? basename($path, '.md'),
            'title' => $meta['title'] ?? basename($path, '.md'),
            'summary' => $meta['summary'] ?? '',
            'order' => (int) ($meta['order'] ?? 99),
            'body' => $body,
        ];
    }

    /** @param list<string> $words */
    protected function snippet(string $body, array $words, int $context = 300): string
    {
        $lower = mb_strtolower($body);

        foreach ($words as $word) {
            $pos = mb_strpos($lower, $word);

            if ($pos !== false) {
                $start = max(0, $pos - (int) ($context / 2));

                return ($start > 0 ? '…' : '').trim(mb_substr($body, $start, $context)).'…';
            }
        }

        return mb_substr($body, 0, $context).'…';
    }
}
