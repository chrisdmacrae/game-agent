<?php

namespace App\Domain\D4\Import;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Materialises the DiabloTools/d4data source tree on disk so the importer can
 * read plain files from it.
 *
 * Two drivers sit behind one API: a sparse `git clone` for development, and a
 * pre-built tarball + manifest published by CI for production (which never has
 * git available). This class knows nothing about the game data itself beyond
 * the build version and the manifest.
 */
class D4DataSource
{
    /**
     * The only directories the importer reads. Kept in sync with the sparse
     * checkout performed by .github/workflows/d4-data-artifact.yml.
     *
     * @var list<string>
     */
    public const SPARSE_DIRS = [
        'json/base/meta/Power',
        'json/base/meta/PlayerClass',
        'json/base/meta/SkillKit',
        'json/base/meta/GenericSkillTree',
        'json/base/meta/ParagonBoard',
        'json/base/meta/ParagonNode',
        'json/base/meta/ParagonGlyph',
        'json/base/meta/ParagonGlyphAffix',
        'json/base/meta/ParagonThreshold',
        'json/base/meta/Item',
        'json/base/meta/ItemType',
        'json/base/meta/Aspect',
        'json/base/meta/Affix',
        'json/base/meta/GameBalance',
        'json/base/meta/Global',
        'json/base/meta/Season',
        'json/enUS_Text/meta/StringList',
    ];

    /**
     * File-level subsets of directories far too large to clone whole. The
     * Texture group holds ~142k files of 3D material art; only the 2DUI_* and
     * 2DInventory_* atlases (~6k files) carry the icon frame tables the
     * importer reads. These force the checkout into no-cone mode.
     *
     * @var list<string>
     */
    public const SPARSE_FILE_PATTERNS = [
        'json/base/meta/Texture/2DUI_*',
        'json/base/meta/Texture/2DInventory_*',
        // The skill tree's own chrome: gate / node / rune frame masks.
        'json/base/meta/Texture/UI_SkillTree*',
    ];

    /**
     * Top-level files the importer needs. No-cone checkouts match only what
     * the patterns name, so these are listed explicitly alongside SPARSE_DIRS.
     *
     * attributes.json / attributeList.json are the game's derived-attribute
     * formula graph — the backbone of the stat calculator.
     *
     * @var list<string>
     */
    public const ROOT_FILES = [
        'CoreTOC_flat.json',
        'GBID.json',
        'eGameBalanceType.json',
        'buildVersion.txt',
        'attributes.json',
        'attributeList.json',
    ];

    protected ?string $fingerprint = null;

    /** @var array<string, mixed>|null */
    protected ?array $manifest = null;

    /**
     * @param  string|null  $treePath  Point at an already-materialised tree instead of acquiring one.
     * @param  string|null  $fingerprint  Stand in for the manifest/git commit SHA of that tree.
     */
    public function __construct(
        protected bool $fromGit = false,
        protected ?string $treePath = null,
        protected ?string $fingerprintOverride = null,
    ) {}

    /**
     * Materialise the source tree at storage/app/d4-sources/tree. A tree passed
     * to the constructor is already materialised, so this is a no-op for it.
     */
    public function acquire(): void
    {
        if ($this->treePath !== null) {
            return;
        }

        $this->fromGit ? $this->acquireFromGit() : $this->acquireFromDist();
    }

    /**
     * The file names (not paths) inside a directory of the acquired tree,
     * sorted so an import walks them in a stable order. A directory the tree
     * does not carry lists as empty.
     *
     * @return list<string>
     */
    public function files(string $relativeDir): array
    {
        $path = $this->treePath().'/'.trim($relativeDir, '/');

        if (! is_dir($path)) {
            return [];
        }

        $names = [];

        foreach (scandir($path) ?: [] as $name) {
            if ($name !== '.' && $name !== '..' && is_file($path.'/'.$name)) {
                $names[] = $name;
            }
        }

        sort($names);

        return $names;
    }

    /**
     * Decode a JSON file that the tree may legitimately not carry — cross
     * references routinely point at objects outside a trimmed checkout.
     *
     * @return array<array-key, mixed>|null
     */
    public function optionalJson(string $relativePath): ?array
    {
        $path = $this->treePath().'/'.ltrim($relativePath, '/');

        return is_file($path) ? $this->json($relativePath) : null;
    }

    /**
     * Decode a JSON file from the acquired source tree.
     *
     * @return array<array-key, mixed>
     */
    public function json(string $relativePath): array
    {
        $decoded = json_decode($this->text($relativePath), true);

        if (! is_array($decoded)) {
            throw new RuntimeException("Failed to decode {$relativePath} from the d4data source tree.");
        }

        return $decoded;
    }

    /**
     * Read a raw file from the acquired source tree.
     */
    public function text(string $relativePath): string
    {
        $path = $this->treePath().'/'.ltrim($relativePath, '/');

        if (! is_file($path)) {
            throw new RuntimeException("Missing {$relativePath} in the d4data source tree. Run acquire() first.");
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Failed to read {$relativePath} from the d4data source tree.");
        }

        return $contents;
    }

    /**
     * The game build the source tree was datamined from, e.g. "1.4.0.62462".
     */
    public function buildVersion(): string
    {
        $path = $this->treePath().'/buildVersion.txt';

        if (is_file($path)) {
            $version = trim((string) file_get_contents($path));

            if ($version !== '') {
                return $version;
            }
        }

        $version = $this->manifest()['buildVersion'] ?? null;

        if (! is_string($version) || $version === '') {
            throw new RuntimeException('Could not determine the d4data build version (no buildVersion.txt and no manifest entry).');
        }

        return $version;
    }

    /**
     * The d4data commit the source tree came from.
     */
    public function fingerprint(): string
    {
        if ($this->fingerprintOverride !== null) {
            return $this->fingerprintOverride;
        }

        if ($this->fingerprint !== null) {
            return $this->fingerprint;
        }

        if ($this->fromGit) {
            return $this->fingerprint = trim($this->run(['git', 'rev-parse', 'HEAD'], $this->repoPath()));
        }

        $commit = $this->manifest()['commit'] ?? null;

        if (! is_string($commit) || $commit === '') {
            throw new RuntimeException('The d4data manifest is missing its source commit SHA.');
        }

        return $this->fingerprint = $commit;
    }

    public function treePath(): string
    {
        return $this->treePath ?? $this->basePath().'/tree';
    }

    protected function repoPath(): string
    {
        return $this->basePath().'/repo';
    }

    protected function basePath(): string
    {
        return storage_path('app/d4-sources');
    }

    /**
     * Sparse-clone (or update) the upstream repo, then point the tree at it.
     * Development only — production has no git and no network access to GitHub.
     */
    protected function acquireFromGit(): void
    {
        $repo = $this->repoPath();
        $ref = (string) config('games.diablo-4.repo_ref');

        if (! is_dir($repo.'/.git')) {
            $this->ensureDirectory(dirname($repo));

            $this->run([
                'git', 'clone', '--depth', '1', '--filter=blob:none', '--sparse',
                '--branch', $ref,
                (string) config('games.diablo-4.repo_url'),
                $repo,
            ], dirname($repo));
        } else {
            $this->run(['git', 'fetch', '--depth', '1', 'origin', $ref], $repo);
            $this->run(['git', 'checkout', '--force', 'FETCH_HEAD'], $repo);
        }

        $this->run(array_merge(['git', 'sparse-checkout', 'set', '--no-cone'], self::sparsePatterns()), $repo);

        $this->linkTreeTo($repo);
    }

    /**
     * The no-cone sparse-checkout pattern list. Root-anchored so a directory
     * name can never match deeper in the tree, and shared with the CI workflow
     * that builds the production tarball — keep the two in sync.
     *
     * @return list<string>
     */
    public static function sparsePatterns(): array
    {
        return array_merge(
            array_map(fn (string $file): string => '/'.$file, self::ROOT_FILES),
            array_map(fn (string $dir): string => '/'.$dir.'/', self::SPARSE_DIRS),
            array_map(fn (string $pattern): string => '/'.$pattern, self::SPARSE_FILE_PATTERNS),
        );
    }

    /**
     * Download the CI-built tarball, extract it and verify every file against
     * the manifest's sha256 map.
     */
    protected function acquireFromDist(): void
    {
        $archive = $this->basePath().'/dist.tar.gz';

        $this->ensureDirectory($this->basePath());
        file_put_contents($archive, $this->downloadDist());

        $tree = $this->treePath();

        $this->resetDirectory($tree);
        $this->run(['tar', '-xzf', $archive, '-C', $tree], $this->basePath());

        $this->manifest = null;
        $this->verifyManifest();
    }

    /**
     * Pull the tarball bytes from the configured disk or URL.
     */
    protected function downloadDist(): string
    {
        $url = config('games.diablo-4.dist_url');
        $disk = config('games.diablo-4.dist_disk');

        if (is_string($disk) && $disk !== '') {
            $path = is_string($url) && $url !== '' ? $url : 'd4data/latest.tar.gz';

            // Rebuild the disk with throw enabled: the default s3 disk swallows
            // Flysystem errors (auth, endpoint, path style) and returns null,
            // which is indistinguishable from a genuinely missing object.
            try {
                $contents = Storage::build(array_merge(
                    config("filesystems.disks.{$disk}", []),
                    ['throw' => true],
                ))->get($path);
            } catch (Throwable $exception) {
                throw new RuntimeException(
                    "Failed to read the d4data artifact [{$path}] from the [{$disk}] disk: {$exception->getMessage()}",
                    previous: $exception,
                );
            }

            if ($contents === null) {
                throw new RuntimeException("The d4data artifact [{$path}] is missing from the [{$disk}] disk.");
            }

            return $contents;
        }

        if (! is_string($url) || $url === '') {
            throw new RuntimeException('No d4data artifact configured: configure D4_DATA_DIST_URL or run with --from-git.');
        }

        $response = Http::withHeaders(['User-Agent' => config('games.diablo-4.user_agent')])
            ->timeout(600)
            ->retry(2, 2000)
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException("Failed to download the d4data artifact from {$url} (HTTP {$response->status()}).");
        }

        return $response->body();
    }

    /**
     * Fail loudly when the extracted tree does not match what CI published.
     */
    protected function verifyManifest(): void
    {
        $files = $this->manifest()['files'] ?? null;

        if (! is_array($files) || $files === []) {
            throw new RuntimeException('The d4data manifest lists no files; refusing to import an unverified tree.');
        }

        foreach ($files as $relativePath => $expected) {
            $path = $this->treePath().'/'.ltrim((string) $relativePath, '/');

            if (! is_file($path)) {
                throw new RuntimeException("The d4data artifact is missing {$relativePath} listed in its manifest.");
            }

            if (! hash_equals((string) $expected, hash_file('sha256', $path))) {
                throw new RuntimeException("Checksum mismatch for {$relativePath} in the d4data artifact.");
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function manifest(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        $path = $this->treePath().'/manifest.json';

        if (! is_file($path)) {
            throw new RuntimeException('No manifest.json in the d4data source tree. Run acquire() first.');
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Failed to decode the d4data manifest.json.');
        }

        return $this->manifest = $decoded;
    }

    /**
     * The tree is the importer's only entry point, so the git driver exposes
     * the checkout through it rather than duplicating gigabytes of JSON.
     */
    protected function linkTreeTo(string $target): void
    {
        $tree = $this->treePath();

        if (is_link($tree)) {
            unlink($tree);
        } elseif (is_dir($tree)) {
            $this->run(['rm', '-rf', $tree], $this->basePath());
        }

        $this->ensureDirectory($this->basePath());

        if (! symlink($target, $tree)) {
            throw new RuntimeException("Failed to link the d4data source tree to {$target}.");
        }
    }

    protected function ensureDirectory(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0755, true) && ! is_dir($path)) {
            throw new RuntimeException("Failed to create directory {$path}.");
        }
    }

    protected function resetDirectory(string $path): void
    {
        if (is_link($path)) {
            unlink($path);
        } elseif (is_dir($path)) {
            $this->run(['rm', '-rf', $path], dirname($path));
        }

        $this->ensureDirectory($path);
    }

    /**
     * @param  list<string>  $command
     */
    protected function run(array $command, string $cwd): string
    {
        $process = new Process($command, $cwd, timeout: 1800);
        $process->run();

        if (! $process->isSuccessful()) {
            $rendered = implode(' ', $command);

            throw new RuntimeException("Command failed: {$rendered}\n".trim($process->getErrorOutput()));
        }

        return $process->getOutput();
    }
}
