import { BUILD_STAGES, BUILD_TIERS } from '@/components/byb/tokens';
import type { BuildStage, BuildTier } from '@/components/byb/tokens';
import type { HubBuild } from '@/types/hub';

/**
 * Shaping helpers shared by the game hub, the waitlist and /my-builds. Every
 * figure they return is destined for Azeret Mono, so they return strings the
 * mono meta lines can join with `·` rather than formatted prose.
 */

/** `4100000` becomes `4.1M`, `18900` becomes `18.9k`. */
export function compactNumber(
    value: number | null | undefined,
): string | undefined {
    if (value === null || value === undefined || Number.isNaN(value)) {
        return undefined;
    }

    const magnitude = Math.abs(value);

    if (magnitude >= 1_000_000) {
        return `${trimZero(value / 1_000_000)}M`;
    }

    if (magnitude >= 1_000) {
        return `${trimZero(value / 1_000)}k`;
    }

    return String(value);
}

function trimZero(value: number): string {
    return value.toFixed(1).replace(/\.0$/, '');
}

/**
 * Stages are stored lowercase (`endgame`); the `--stage-*` taxonomy labels are
 * title case and must not be renamed.
 */
export function stageLabel(stage?: string | null): BuildStage | undefined {
    if (!stage) {
        return undefined;
    }

    const label = stage.charAt(0).toUpperCase() + stage.slice(1).toLowerCase();

    return (BUILD_STAGES as readonly string[]).includes(label)
        ? (label as BuildStage)
        : undefined;
}

export function tierLabel(tier?: string | null): BuildTier | undefined {
    if (!tier) {
        return undefined;
    }

    const label = tier.toUpperCase();

    return (BUILD_TIERS as readonly string[]).includes(label)
        ? (label as BuildTier)
        : undefined;
}

/** A `HubBuild` as `BuildCard` wants it. */
export function buildCardProps(build: HubBuild) {
    return {
        title: build.name,
        buildClass: build.class ?? 'Unknown class',
        ascendancy: build.ascendancy ?? undefined,
        stage: stageLabel(build.stage),
        tier: tierLabel(build.tier),
        patch: build.patch ?? undefined,
        author: build.author ?? undefined,
        updated: build.updated_at ?? undefined,
        dps: compactNumber(build.dps),
        ehp: compactNumber(build.ehp),
        cost:
            build.cost_divine === null ? undefined : String(build.cost_divine),
        endorsements: build.endorsements,
        draft: build.visibility === 'draft',
        href: build.url,
    };
}

/**
 * The per-game MCP endpoint, derived from the shared `mcpUrl` so the origin
 * matches whatever the app is served from.
 */
export function gameMcpUrl(shared: unknown, slug: string): string {
    return typeof shared === 'string' && shared !== ''
        ? shared.replace(/\/mcp\/[^/]+$/, `/mcp/${slug}`)
        : `/mcp/${slug}`;
}
