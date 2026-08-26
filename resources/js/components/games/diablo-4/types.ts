/**
 * The Diablo IV build payload, as the MCP tools write it and
 * `App\Domain\D4\Validation\D4BuildRules` validates it.
 *
 * D4 anatomy shares nothing with PoE 2: there are no support gems, no spirit
 * and no passive tree — an action bar of six skills, paragon boards that rotate
 * on their sockets, and gear keyed by slot instead of listed. So these types
 * stay D4 specific and game prefixed, and the route pages under `pages/Builds`
 * are thin shells that pick the game's renderer.
 *
 * The unprefixed types at the bottom (`BuildGame`, `BuildViewer`, …) describe
 * props the controller sends for every game. They are restated here rather than
 * imported from the PoE 2 folder so the two game folders stay independent.
 */

export const D4_CLASSES = [
    'Barbarian',
    'Druid',
    'Necromancer',
    'Paladin',
    'Rogue',
    'Sorcerer',
    'Spiritborn',
    'Warlock',
] as const;

export const D4_CONTENT_TIERS = ['leveling', 'endgame', 'pit_push'] as const;

export type D4ContentTier = (typeof D4_CONTENT_TIERS)[number];

export const D4_RESISTANCES = [
    'fire',
    'cold',
    'lightning',
    'poison',
    'shadow',
] as const;

export type D4Resistance = (typeof D4_RESISTANCES)[number];

export const D4_RARITIES = [
    'common',
    'magic',
    'rare',
    'legendary',
    'unique',
    'mythic',
] as const;

export type D4Rarity = (typeof D4_RARITIES)[number];

/** The single-item slots. Weapons are a separate list: how many a character carries is per class. */
export const D4_GEAR_SLOTS = [
    'helm',
    'chest',
    'gloves',
    'pants',
    'boots',
    'amulet',
    'ring_1',
    'ring_2',
] as const;

export type D4GearSlot = (typeof D4_GEAR_SLOTS)[number];

export const D4_SLOT_LABELS: Record<string, string> = {
    helm: 'Helm',
    chest: 'Chest',
    gloves: 'Gloves',
    pants: 'Pants',
    boots: 'Boots',
    amulet: 'Amulet',
    ring_1: 'Ring 1',
    ring_2: 'Ring 2',
};

/** Six skills fit on the action bar. */
export const D4_MAX_EQUIPPED_SKILLS = 6;

/** The starting board plus up to nine more. */
export const D4_MAX_PARAGON_BOARDS = 10;

/** Boards attach to their neighbour on any edge; the entry stores which. */
export const D4_ROTATIONS = [0, 90, 180, 270] as const;

export type D4Rotation = (typeof D4_ROTATIONS)[number];

export const D4_MAX_WEAPONS = 4;

/** Masterworking runs to 12, with a crit every fourth level. */
export const D4_MAX_MASTERWORK = 12;

/** Four greater affixes is a perfect item. */
export const D4_MAX_GREATER_AFFIXES = 4;

/** One skill on the action bar, with its rank and the modifiers picked under it. */
export type D4EquippedSkill = {
    skill: string;
    rank?: number | null;
    role?: string | null;
    modifiers?: string[];
    reported?: string | null;
};

export type D4SkillPoint = {
    skill: string;
    points?: number | null;
};

/** One attached paragon board: which board, how it is turned, and its glyph. */
export type D4ParagonEntry = {
    board: string;
    rotation?: number | null;
    glyph?: string | null;
    glyph_level?: number | null;
    notables?: string[];
};

/** A tempered affix and the tier it rolled at. */
export type D4TemperedAffix = {
    affix: string;
    tier?: number | null;
};

export type D4GearItem = {
    name?: string | null;
    item_type?: string | null;
    rarity?: string | null;
    aspect?: string | null;
    affixes?: string[];
    greater_affixes?: number | null;
    tempered?: D4TemperedAffix[];
    masterwork_level?: number | null;
    /** Two runes make a runeword: one condition, one effect. */
    runes?: string[];
};

/** Gear is a map keyed by slot, plus the flexible weapons list. */
export type D4Gear = Partial<Record<D4GearSlot, D4GearItem>> & {
    weapons?: D4GearItem[];
};

export type D4Mercenary = {
    hired?: string | null;
    reinforcement?: string | null;
};

export type D4Milestone = {
    level: number;
    text: string;
};

export type D4StatRow = {
    label: string;
    value: string;
};

export type D4BuildDefinition = {
    class?: string | null;
    level?: number | null;
    armor?: number | null;
    resistances?: Partial<Record<D4Resistance, number>> | null;
    content_tier?: string | null;
    stage?: string | null;
    tier?: string | null;
    dps?: number | null;
    ehp?: number | null;
    hardcore_viable?: boolean | null;
    equipped_skills?: D4EquippedSkill[];
    skill_points?: D4SkillPoint[];
    paragon?: D4ParagonEntry[];
    gear?: D4Gear | null;
    seasonal_power?: string | null;
    mercenary?: D4Mercenary | null;
    milestones?: D4Milestone[];
    stats?: {
        offence?: D4StatRow[];
        defence?: D4StatRow[];
    } | null;
    how_it_plays?: string[];
    works_because?: string[];
    watch_out_for?: string[];
};

export type D4Validation = {
    valid?: boolean;
    violations?: string[];
    warnings?: string[];
    suggestions?: string[];
};

/**
 * One cell of an imported paragon board, as `d4_paragon_boards.grid` stores it.
 * A null cell is a hole in the board rather than an unallocated node.
 */
export type D4ParagonCell = {
    name?: string | null;
    key?: string | null;
    rarity?: string | null;
    is_gate?: boolean;
    has_socket?: boolean;
    attributes?: string[];
};

/**
 * A board's grid, keyed by the board name the build entry refers to. Optional
 * everywhere: the page renders the whole paragon plan from the build definition
 * alone and only draws grids when the server had them to send.
 */
export type D4ParagonBoardGrid = {
    name: string;
    class_name?: string | null;
    grid: (D4ParagonCell | null)[][];
};

/** The build page's viewer state. Shared across games. */
export type BuildViewer = {
    can_edit: boolean;
    endorsed: boolean;
    bookmarked: boolean;
};

export type SimilarBuild = {
    id: string;
    title: string;
    meta: string;
    url: string;
};

export type BuildGame = {
    slug: string;
    name: string;
    short_name: string;
    accent?: string | null;
};

export type PublishCheck = {
    key: string;
    label: string;
    passed: boolean;
    detail: string | null;
};

export type D4BuildShowProps = {
    build: {
        id: string;
        name: string;
        summary: string | null;
        visibility: string;
        definition: D4BuildDefinition;
        validation: D4Validation | Record<string, never>;
        game_version: string | null;
        created_at: string;
        updated_at: string | null;
        endorsements: number | null;
        author: string | null;
        url: string;
        edit_url: string;
        guide_html: string | null;
    };
    game: BuildGame;
    viewer: BuildViewer;
    similarBuilds: SimilarBuild[];
    /** Grid data for the boards this build attaches, when the server has it. */
    paragonBoards?: D4ParagonBoardGrid[];
};

export type D4BuildEditProps = {
    game: BuildGame;
    build: {
        id: string;
        name: string;
        summary: string | null;
        guide_markdown: string | null;
        visibility: 'draft' | 'public';
        definition: D4BuildDefinition;
        validation: D4Validation | null;
        game_version: string | null;
        updated_at: string | null;
        url: string;
    };
    options: {
        classes: string[];
        /**
         * Always empty for Diablo IV — a character has no second class layer.
         * The select is hidden rather than rendered with nothing in it.
         */
        ascendancies: { name: string; class_name: string | null }[];
        stages: string[];
        tiers: string[];
    };
    checklist: PublishCheck[];
    paragonBoards?: D4ParagonBoardGrid[];
};
