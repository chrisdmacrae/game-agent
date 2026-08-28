<?php

namespace App\Domain\D4;

use App\Models\D4\Aspect;
use App\Models\D4\CalcTable;
use App\Models\D4\ParagonBoard;
use App\Models\D4\Skill;
use App\Models\D4\UniqueItem;

/**
 * The icon manifest lists every texture atlas the web app wants pixels for,
 * keyed by Texture SNO id. The offline extractor tool (tools/d4-icon-extractor)
 * reads this manifest on a machine with a Diablo IV install, pulls the
 * textures out of the game's CASC storage, and produces
 * public/games/diablo-4/icons/{sno}.webp atlas sheets. The app then crops
 * individual icons out of a sheet with the fractional UV rects the importer
 * stored, so sheets can be extracted at any resolution.
 */
class IconManifest
{
    public function __construct(protected D4Context $context) {}

    /**
     * The URL of an extracted atlas sheet, or null while its pixels have not
     * been extracted yet — the UI falls back to a letter badge.
     */
    public static function atlasUrlFor(mixed $textureSno): ?string
    {
        if (! is_numeric($textureSno)) {
            return null;
        }

        $sno = (int) $textureSno;

        return is_file(public_path("games/diablo-4/icons/{$sno}.webp"))
            ? asset("games/diablo-4/icons/{$sno}.webp")
            : null;
    }

    /**
     * @return array{generated_at: string, game_version: string, textures: array<int, array{name: string|null, refs: int}>}
     */
    public function build(): array
    {
        $versionId = $this->context->versionId();

        /** @var array<int, int> $textures texture sno => referencing entity count */
        $textures = [];

        $collect = function (mixed $icon) use (&$textures): void {
            $sno = is_array($icon) ? ($icon['texture'] ?? null) : null;

            if (is_numeric($sno)) {
                $textures[(int) $sno] = ($textures[(int) $sno] ?? 0) + 1;
            }
        };

        foreach ([Skill::class, Aspect::class, UniqueItem::class] as $model) {
            $model::forVersion($versionId)
                ->whereNotNull('icon')
                ->pluck('icon')
                ->each(fn ($icon) => $collect(is_array($icon) ? $icon : json_decode((string) $icon, true)));
        }

        ParagonBoard::forVersion($versionId)
            ->get(['grid', 'raw'])
            ->each(function (ParagonBoard $board) use ($collect) {
                $collect($board->raw['legendary_node_icon'] ?? null);

                foreach (is_array($board->grid) ? $board->grid : [] as $row) {
                    foreach ($row as $cell) {
                        $collect(is_array($cell) ? ($cell['icon'] ?? null) : null);
                    }
                }
            });

        ksort($textures);

        $atlasNames = CalcTable::forVersion($versionId)
            ->where('key', 'texture_atlases')
            ->value('data') ?? [];

        return [
            'generated_at' => now()->toIso8601String(),
            'game_version' => $this->context->version()->version,
            'textures' => collect($textures)
                ->map(fn (int $refs, int $sno) => [
                    'name' => $atlasNames[(string) $sno] ?? $atlasNames[$sno] ?? null,
                    'refs' => $refs,
                ])
                ->all(),
        ];
    }

    public function write(): int
    {
        // The fixture import runs inside the test suite and must never
        // clobber the repo's committed manifest with the fixtures' seven
        // textures — that poisoned manifest once starved the offline
        // extractor down to seven sheets.
        if (app()->runningUnitTests()) {
            return count($this->build()['textures']);
        }

        $manifest = $this->build();

        $directory = public_path('games/diablo-4');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents(
            "{$directory}/icon-manifest.json",
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );

        return count($manifest['textures']);
    }
}
