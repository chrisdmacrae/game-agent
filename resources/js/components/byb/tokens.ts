/**
 * Fixed domain taxonomies. `--stage-*` and `--tier-*` are part of the design
 * system contract and must never be recolored.
 */
export const BUILD_STAGES = [
    'Leveling',
    'Mapping',
    'Endgame',
    'Bossing',
] as const;

export type BuildStage = (typeof BUILD_STAGES)[number];

export const BUILD_TIERS = ['S', 'A', 'B', 'C'] as const;

export type BuildTier = (typeof BUILD_TIERS)[number];

const STAGE_COLORS: Record<BuildStage, string> = {
    Leveling: 'var(--stage-leveling)',
    Mapping: 'var(--stage-mapping)',
    Endgame: 'var(--stage-endgame)',
    Bossing: 'var(--stage-boss)',
};

const TIER_COLORS: Record<BuildTier, string> = {
    S: 'var(--tier-s)',
    A: 'var(--tier-a)',
    B: 'var(--tier-b)',
    C: 'var(--tier-c)',
};

export function stageColor(stage?: string): string {
    return STAGE_COLORS[stage as BuildStage] ?? 'var(--border-subtle)';
}

export function tierColor(tier?: string): string {
    return TIER_COLORS[tier as BuildTier] ?? 'var(--fg-3)';
}
