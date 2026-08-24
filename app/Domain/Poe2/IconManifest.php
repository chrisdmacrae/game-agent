<?php

namespace App\Domain\Poe2;

use App\Models\Poe2\Gem;
use App\Models\Poe2\ItemBase;
use App\Models\Poe2\UniqueItem;

/**
 * The icon manifest lists every game art file (.dds) the web app wants as an
 * icon, keyed by a stable content hash of its path. The offline extractor tool
 * (tools/icon-extractor) reads this manifest, pulls the files from the game's
 * patch CDN, and produces public/games/poe2/icons/{key}.png files.
 */
class IconManifest
{
    /** Wearable/socketable item classes whose base art the gear screen uses. */
    public const EQUIPMENT_CLASSES = [
        'Amulet', 'Belt', 'Body Armour', 'Boots', 'Bow', 'Buckler', 'Claw',
        'Crossbow', 'Dagger', 'Flail', 'Focus', 'Gloves', 'Helmet', 'Jewel',
        'LifeFlask', 'ManaFlask', 'One Hand Axe', 'One Hand Mace',
        'One Hand Sword', 'Quiver', 'Ring', 'Sceptre', 'Shield', 'Spear',
        'Staff', 'Talisman', 'Two Hand Axe', 'Two Hand Mace', 'Two Hand Sword',
        'Wand', 'Warstaff',
    ];

    public function __construct(protected Poe2Context $context) {}

    public static function keyFor(string $ddsPath): string
    {
        return md5($ddsPath);
    }

    public static function iconUrlFor(?string $ddsPath): ?string
    {
        if ($ddsPath === null || $ddsPath === '') {
            return null;
        }

        $key = self::keyFor($ddsPath);

        return is_file(public_path("games/poe2/icons/{$key}.png"))
            ? asset("games/poe2/icons/{$key}.png")
            : null;
    }

    /**
     * A handful of entries in the game data carry junk art paths (e.g. "4k/")
     * that would abort the extractor.
     */
    public static function isValidDdsPath(string $path): bool
    {
        return str_starts_with($path, 'Art/') && str_ends_with(strtolower($path), '.dds');
    }

    /** @return array{generated_at: string, game_version: string, icons: array<string, string>} */
    public function build(): array
    {
        $versionId = $this->context->versionId();

        $icons = [];

        Gem::forVersion($versionId)
            ->where('is_released', true)
            ->pluck('raw')
            ->each(function ($raw) use (&$icons) {
                $dds = (is_array($raw) ? $raw : json_decode((string) $raw, true))['icon_dds_file'] ?? null;

                if ($dds && self::isValidDdsPath($dds)) {
                    $icons[self::keyFor($dds)] = $dds;
                }
            });

        ItemBase::forVersion($versionId)
            ->whereIn('item_class', self::EQUIPMENT_CLASSES)
            ->where('release_state', 'released')
            ->pluck('raw')
            ->each(function ($raw) use (&$icons) {
                $dds = (is_array($raw) ? $raw : json_decode((string) $raw, true))['visual_identity']['dds_file'] ?? null;

                if ($dds && self::isValidDdsPath($dds)) {
                    $icons[self::keyFor($dds)] = $dds;
                }
            });

        UniqueItem::forVersion($versionId)
            ->pluck('raw')
            ->each(function ($raw) use (&$icons) {
                $dds = (is_array($raw) ? $raw : json_decode((string) $raw, true))['dds'] ?? null;

                if ($dds && self::isValidDdsPath($dds)) {
                    $icons[self::keyFor($dds)] = $dds;
                }
            });

        return [
            'generated_at' => now()->toIso8601String(),
            'game_version' => $this->context->version()->version,
            'icons' => $icons,
        ];
    }

    public function write(): int
    {
        $manifest = $this->build();

        $directory = public_path('games/poe2');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents(
            "{$directory}/icon-manifest.json",
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );

        return count($manifest['icons']);
    }
}
