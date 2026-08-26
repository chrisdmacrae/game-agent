import type {
    D4BuildDefinition,
    D4EquippedSkill,
    D4Gear,
    D4GearItem,
    D4ParagonEntry,
} from '@/components/games/diablo-4/types';
import {
    D4_GEAR_SLOTS,
    D4_SLOT_LABELS,
} from '@/components/games/diablo-4/types';

/**
 * Reading helpers for the Diablo IV payload. Everything here tolerates a
 * partial build: the MCP writes what it knows and a human finishes the rest, so
 * a missing field renders as "—" rather than a zero.
 */

/** The game accent for Diablo IV, from the `games` row. */
export const D4_ACCENT = 'var(--red-400)';

/** `content_tier` is D4 vocabulary; `stage` is the site-wide taxonomy. */
const CONTENT_TIER_STAGES: Record<string, string> = {
    leveling: 'leveling',
    endgame: 'endgame',
    pit_push: 'bossing',
};

const STAGE_LABELS: Record<string, string> = {
    leveling: 'Leveling',
    mapping: 'Mapping',
    endgame: 'Endgame',
    bossing: 'Bossing',
};

const CONTENT_TIER_LABELS: Record<string, string> = {
    leveling: 'Leveling',
    endgame: 'Endgame',
    pit_push: 'Pit push',
};

export function contentTierLabel(definition: D4BuildDefinition): string | null {
    const value = definition.content_tier;

    return value ? (CONTENT_TIER_LABELS[value] ?? value) : null;
}

/**
 * The stored `stage`, falling back to the D4 `content_tier` the MCP writes.
 * Mirrors `BuildStage::fromBuild()`.
 */
export function stageValue(definition: D4BuildDefinition): string | null {
    if (definition.stage && STAGE_LABELS[definition.stage]) {
        return definition.stage;
    }

    if (definition.content_tier) {
        return CONTENT_TIER_STAGES[definition.content_tier] ?? null;
    }

    return null;
}

export function stageLabel(definition: D4BuildDefinition): string | null {
    const value = stageValue(definition);

    return value === null ? null : STAGE_LABELS[value];
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

/**
 * Item name colours. Diablo IV runs common → mythic; legendary borrows the
 * game accent because it is the rarity an endgame build is made of.
 */
const RARITY_COLORS: Record<string, string> = {
    common: 'var(--fg-2)',
    magic: 'var(--blue-400)',
    rare: 'var(--gold-400)',
    legendary: D4_ACCENT,
    unique: 'var(--mag-400)',
    mythic: 'var(--violet-400)',
};

export function rarityColor(rarity: string | null | undefined): string {
    return RARITY_COLORS[rarity ?? ''] ?? 'var(--fg-2)';
}

/** `lvl 12` — the mono meta line beside a skill on the action bar. */
export function skillMeta(skill: D4EquippedSkill): string {
    return skill.rank ? `rank ${skill.rank}` : '';
}

/** An item card's headline: the name, else the base type, else the slot. */
export function itemLabel(item: D4GearItem, fallback: string): string {
    return item.name || item.item_type || fallback;
}

/** True when the item has anything at all worth drawing a card for. */
export function hasItem(item: D4GearItem | null | undefined): boolean {
    if (!item) {
        return false;
    }

    return Boolean(
        item.name ||
        item.item_type ||
        item.aspect ||
        item.affixes?.length ||
        item.tempered?.length ||
        item.runes?.length ||
        item.masterwork_level,
    );
}

export type D4GearCell = {
    slot: string;
    label: string;
    item: D4GearItem | null;
};

/** The keyed slots in paperdoll order, empty ones included. */
export function gearCells(gear: D4Gear | null | undefined): D4GearCell[] {
    return D4_GEAR_SLOTS.map((slot) => ({
        slot,
        label: D4_SLOT_LABELS[slot],
        item: hasItem(gear?.[slot]) ? (gear?.[slot] ?? null) : null,
    }));
}

/** The weapons list, dropping entries with nothing on them. */
export function weaponList(gear: D4Gear | null | undefined): D4GearItem[] {
    return (gear?.weapons ?? []).filter((item) => hasItem(item));
}

/** `6 of 9 slots equipped` — the mono readout above the paperdoll. */
export function equippedSummary(
    gear: D4Gear | null | undefined,
): string | null {
    const cells = gearCells(gear);
    const total = cells.length + 1;
    const filled =
        cells.filter((cell) => cell.item !== null).length +
        (weaponList(gear).length > 0 ? 1 : 0);

    if (filled === 0) {
        return null;
    }

    return `${filled} of ${total} slots equipped`;
}

/** `4/12` masterworking, or null when the item was never masterworked. */
export function masterworkLabel(item: D4GearItem): string | null {
    return item.masterwork_level ? `MW ${item.masterwork_level}` : null;
}

/** `Might · lvl 21` — the glyph line under a paragon board. */
export function glyphMeta(entry: D4ParagonEntry): string | null {
    if (!entry.glyph) {
        return null;
    }

    return entry.glyph_level
        ? `${entry.glyph} · lvl ${entry.glyph_level}`
        : entry.glyph;
}

/**
 * Paragon points are earned per level from 60 to the cap, plus the renown
 * awards. Mirrors the budget D4BuildValidator reports against.
 */
export const PARAGON_UNLOCK_LEVEL = 60;

/** The resistance cap, and the ceiling max-resistance bonuses raise it to. */
export const RESISTANCE_CAP = 70;
