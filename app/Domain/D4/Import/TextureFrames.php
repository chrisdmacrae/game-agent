<?php

namespace App\Domain\D4\Import;

/**
 * Resolves the dump's 32-bit icon handles (`hIconNormal`, `hIconOverride`,
 * `tInvImages[].hDefaultImage`, ...) to the texture atlas frame that carries
 * the art.
 *
 * The handles are not SNO ids and not reversible hashes: every 2D atlas's
 * `.tex.json` lists its frames as `ptFrame[]` records whose `hImageHandle`
 * shares the icon-handle namespace, so handle -> (texture SNO, frame, UV rect)
 * is a pure forward join over the Texture group. UVs are kept as fractions of
 * the sheet, which makes the extracted atlas images resolution-independent.
 */
class TextureFrames
{
    protected const DIR_TEXTURE = 'json/base/meta/Texture';

    /** @var array<int, array<string, mixed>>|null hImageHandle => frame record */
    protected ?array $index = null;

    /** @var array<int, string> texture SNO id => atlas object name (file basename) */
    protected array $atlasNames = [];

    /** @var array<int, true> handles looked up but absent from every atlas */
    protected array $misses = [];

    public function __construct(protected D4DataSource $source) {}

    /**
     * The atlas frame behind an icon handle, or null when no cloned atlas
     * carries it (a few talent passives and most base items ship none).
     *
     * @return array{texture: int, frame: int, u0: float, v0: float, u1: float, v1: float}|null
     */
    public function resolve(mixed $handle): ?array
    {
        if (! is_numeric($handle) || (int) $handle === 0) {
            return null;
        }

        $frame = $this->index()[(int) $handle] ?? null;

        if ($frame === null) {
            $this->misses[(int) $handle] = true;
        }

        return $frame;
    }

    /**
     * Every handle resolve() was asked for and could not answer.
     *
     * @return list<int>
     */
    public function unresolved(): array
    {
        return array_keys($this->misses);
    }

    /**
     * Texture SNO id => atlas object name for every scanned atlas — what the
     * offline extractor uses to locate a sheet inside the game's CASC storage.
     *
     * @return array<int, string>
     */
    public function atlases(): array
    {
        $this->index();

        return $this->atlasNames;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function index(): array
    {
        if ($this->index !== null) {
            return $this->index;
        }

        $index = [];

        foreach ($this->source->files(self::DIR_TEXTURE) as $file) {
            if (! str_ends_with($file, '.tex.json')) {
                continue;
            }

            $texture = $this->source->optionalJson(self::DIR_TEXTURE.'/'.$file);

            if ($texture === null) {
                continue;
            }

            $snoId = $texture['__snoID__'] ?? null;
            $width = $texture['dwWidth'] ?? null;
            $height = $texture['dwHeight'] ?? null;

            if (! is_numeric($snoId) || ! is_numeric($width) || ! is_numeric($height) || $width <= 0 || $height <= 0) {
                continue;
            }

            $this->atlasNames[(int) $snoId] = substr($file, 0, -strlen('.tex.json'));

            foreach ($texture['ptFrame'] ?? [] as $position => $frame) {
                $handle = is_array($frame) ? ($frame['hImageHandle'] ?? null) : null;

                if (! is_numeric($handle) || (int) $handle === 0 || isset($index[(int) $handle])) {
                    continue;
                }

                $index[(int) $handle] = [
                    'texture' => (int) $snoId,
                    'frame' => (int) $position,
                    'u0' => round((float) ($frame['flU0'] ?? 0), 6),
                    'v0' => round((float) ($frame['flV0'] ?? 0), 6),
                    'u1' => round((float) ($frame['flU1'] ?? 0), 6),
                    'v1' => round((float) ($frame['flV1'] ?? 0), 6),
                ];
            }
        }

        return $this->index = $index;
    }
}
