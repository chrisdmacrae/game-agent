<?php

namespace App\Domain\Poe2\Import;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Downloads PoE2 source data (repoe-fork JSON, GGG tree export, PoB2 unique
 * item text) with a local file cache so re-imports don't hammer the sources.
 */
class DataSourceClient
{
    /** @var array<string, string> sha256 per fetched resource, for fingerprinting */
    public array $hashes = [];

    public function __construct(
        protected bool $refresh = false,
    ) {}

    /** @return array<string, mixed> */
    public function repoeJson(string $file): array
    {
        $url = config('games.poe2.repoe_base_url')."/{$file}.min.json";

        $contents = $this->fetch("repoe/{$file}.json", $url);

        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            throw new RuntimeException("Failed to decode {$file}.min.json from repoe-fork.");
        }

        return $decoded;
    }

    /** @return array<string, mixed> */
    public function treeJson(): array
    {
        $contents = $this->fetch('tree/data.json', config('games.poe2.tree_url'));

        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Failed to decode passive tree data.json.');
        }

        return $decoded;
    }

    public function pobUniquesLua(string $file): string
    {
        $url = config('games.poe2.pob_uniques_base_url')."/{$file}.lua";

        return $this->fetch("pob-uniques/{$file}.lua", $url);
    }

    public function fingerprint(): string
    {
        ksort($this->hashes);

        return hash('sha256', implode('|', array_map(
            fn (string $key, string $hash) => "{$key}:{$hash}",
            array_keys($this->hashes),
            $this->hashes,
        )));
    }

    protected function fetch(string $cachePath, string $url): string
    {
        $disk = Storage::disk('local');
        $path = "poe2-sources/{$cachePath}";

        if ($this->refresh || ! $disk->exists($path)) {
            $response = Http::withHeaders(['User-Agent' => config('games.poe2.user_agent')])
                ->timeout(120)
                ->retry(2, 2000)
                ->get($url);

            if (! $response->successful()) {
                throw new RuntimeException("Failed to download {$url} (HTTP {$response->status()}).");
            }

            $disk->put($path, $response->body());
        }

        $contents = $disk->get($path);

        $this->hashes[$cachePath] = hash('sha256', $contents);

        return $contents;
    }
}
