<?php

namespace App\Domain\Builds;

/**
 * The game stage a build targets. The MCP payload historically carried
 * PoE2-flavoured `content_tier` values; `stage` is the game-agnostic taxonomy
 * used by the web UI, and content_tier maps onto it one-for-one.
 */
enum BuildStage: string
{
    case Leveling = 'leveling';
    case Mapping = 'mapping';
    case Endgame = 'endgame';
    case Bossing = 'bossing';

    /**
     * @return array<string, string> content_tier value => stage value
     */
    public static function contentTierMap(): array
    {
        return [
            'campaign' => self::Leveling->value,
            'early_endgame' => self::Mapping->value,
            'endgame' => self::Endgame->value,
            'pinnacle' => self::Bossing->value,
        ];
    }

    public static function fromContentTier(?string $contentTier): ?self
    {
        if ($contentTier === null) {
            return null;
        }

        $value = self::contentTierMap()[$contentTier] ?? null;

        return $value === null ? null : self::from($value);
    }

    /**
     * Resolve the stage from a build payload: an explicit `stage` wins,
     * otherwise fall back to the legacy `content_tier`.
     *
     * @param  array<string, mixed>  $build
     */
    public static function fromBuild(array $build): ?self
    {
        $stage = $build['stage'] ?? null;

        if (is_string($stage) && ($case = self::tryFrom($stage)) !== null) {
            return $case;
        }

        $contentTier = $build['content_tier'] ?? null;

        return is_string($contentTier) ? self::fromContentTier($contentTier) : null;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
