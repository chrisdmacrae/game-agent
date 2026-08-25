import type {
    Poe2BuildDefinition,
    Poe2Entity,
    Poe2GearItem,
    Poe2SkillSetup,
    Poe2SkillSupport,
} from '@/components/games/poe2/types';

/**
 * Reading helpers for the PoE 2 payload. Everything here tolerates a partial
 * build: the MCP writes what it knows and a human finishes the rest, so a
 * missing field renders as "—" rather than a zero.
 */

/** The `--stage-*` taxonomy uses title case; the payload stores lower case. */
const CONTENT_TIER_STAGES: Record<string, string> = {
    campaign: 'leveling',
    early_endgame: 'mapping',
    endgame: 'endgame',
    pinnacle: 'bossing',
};

const STAGE_LABELS: Record<string, string> = {
    leveling: 'Leveling',
    mapping: 'Mapping',
    endgame: 'Endgame',
    bossing: 'Bossing',
};

export function stageLabel(definition: Poe2BuildDefinition): string | null {
    const value = stageValue(definition);

    return value === null ? null : STAGE_LABELS[value];
}

/**
 * The stored `stage` value, falling back to the legacy `content_tier` the MCP
 * wrote before the taxonomy landed. Mirrors `BuildStage::fromBuild()`.
 */
export function stageValue(definition: Poe2BuildDefinition): string | null {
    if (definition.stage && STAGE_LABELS[definition.stage]) {
        return definition.stage;
    }

    if (definition.content_tier) {
        return CONTENT_TIER_STAGES[definition.content_tier] ?? null;
    }

    return null;
}

/** The em dash the design system uses wherever a number is not known. */
export const MISSING = '—';

/** 4,100,000 reads as 4.1M; 18,900 as 18.9k. */
export function compactNumber(value: number | null | undefined): string {
    if (value === null || value === undefined || Number.isNaN(value)) {
        return MISSING;
    }

    if (Math.abs(value) >= 1_000_000) {
        return `${trimZero(value / 1_000_000)}M`;
    }

    if (Math.abs(value) >= 1_000) {
        return `${trimZero(value / 1_000)}k`;
    }

    return String(value);
}

function trimZero(value: number): string {
    return value.toFixed(1).replace(/\.0$/, '');
}

export function plainNumber(value: number | null | undefined): string {
    if (value === null || value === undefined || Number.isNaN(value)) {
        return MISSING;
    }

    return value.toLocaleString();
}

/** Support gems arrive as names or as {name, effect}; normalise to objects. */
export function skillSupports(skill: Poe2SkillSetup): Poe2SkillSupport[] {
    return (skill.supports ?? [])
        .map((support) =>
            typeof support === 'string' ? { name: support } : support,
        )
        .filter((support): support is Poe2SkillSupport =>
            Boolean(support?.name),
        );
}

/** `lvl 20 / 20% · 38 mana` — the mono meta line on a skill panel. */
export function skillMeta(skill: Poe2SkillSetup): string {
    const gemLevel = [
        skill.level ? `lvl ${skill.level}` : null,
        skill.quality !== null && skill.quality !== undefined
            ? `${skill.quality}%`
            : null,
    ]
        .filter(Boolean)
        .join(' / ');

    return [gemLevel, skill.cost].filter(Boolean).join(' · ');
}

/** Rune sockets across the whole build: `5 of 8 rune sockets filled`. */
export function socketSummary(gear: Poe2GearItem[]): string | null {
    const sockets = gear.flatMap((item) => item.runes ?? []);

    if (sockets.length === 0) {
        return null;
    }

    const filled = sockets.filter((rune) => Boolean(rune)).length;
    const noun = sockets.length === 1 ? 'rune socket' : 'rune sockets';

    return `${filled} of ${sockets.length} ${noun} filled`;
}

/** Item name colours: Unique gold, Rare blue, Magic/Normal neutral. */
export function rarityColor(rarity: string | null | undefined): string {
    if (rarity === 'unique') {
        return 'var(--gold-400)';
    }

    if (rarity === 'rare') {
        return 'var(--blue-400)';
    }

    return 'var(--fg-2)';
}

/** Case-insensitive lookup: guide mentions differ in casing from the payload. */
export function entityIndex(
    entities: Record<string, Poe2Entity>,
): Record<string, Poe2Entity> {
    const index: Record<string, Poe2Entity> = {};

    for (const entity of Object.values(entities)) {
        index[entity.name.toLowerCase()] = entity;
    }

    return index;
}

/**
 * Sprite icons render at the sheet's native size — scaling would require the
 * full sheet dimensions to keep background-size and -position in step.
 */
export function spriteStyle(
    entity: Poe2Entity,
    spriteUrl: string,
): Record<string, string> | null {
    if (!entity.sprite) {
        return null;
    }

    const { x, y, w, h } = entity.sprite;

    return {
        width: `${w}px`,
        height: `${h}px`,
        backgroundImage: `url(${spriteUrl})`,
        backgroundPosition: `-${x}px -${y}px`,
        backgroundRepeat: 'no-repeat',
    };
}

/** The gem colour letter tiles, kept from the pre-rebrand build page. */
export const GEM_COLORS: Record<string, string> = {
    r: 'var(--red-400)',
    g: 'var(--teal-400)',
    b: 'var(--blue-400)',
    w: 'var(--fg-3)',
};

export function gemColor(entity: Poe2Entity | null): string {
    return GEM_COLORS[entity?.color ?? 'w'] ?? GEM_COLORS.w;
}

/**
 * Mirrors `BuildValidator::passivePointBudget()` — one point a level plus the
 * campaign quest rewards. Kept in step with the PHP side by hand.
 */
export function passivePointBudget(level: number | null | undefined): number {
    if (!level) {
        return 0;
    }

    return Math.min(level - 1, 99) + 24;
}
