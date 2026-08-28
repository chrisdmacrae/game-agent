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

/**
 * An icon cropped out of an extracted texture atlas: the sheet URL plus the
 * fractional UV rect. Entities whose sheet has not been extracted carry null
 * and render as letter badges.
 */
export type D4EntityIcon = {
    url: string;
    u0: number;
    v0: number;
    u1: number;
    v1: number;
    /** Crop pixel size on the original sheet; drives the aspect ratio. */
    w?: number | null;
    h?: number | null;
};

/** One hover-card entity from the build page's entity dictionary. */
export type D4Entity = {
    kind: 'skill' | 'aspect' | 'unique' | 'glyph' | 'paragon-node';
    name: string;
    icon: D4EntityIcon | null;
    category?: string | null;
    class_name?: string | null;
    rank?: number | null;
    max_rank?: number | null;
    description?: string | null;
    tags?: string[];
    item_types?: string[];
    item_type?: string | null;
    is_mythic?: boolean;
    effects?: string[];
    board?: string | null;
    rarity?: string | null;
    attributes?: string[];
};

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

/** A cell address in a board's PRE-rotation grid, 0-based row/col. */
export type D4ParagonNodeRef = {
    row: number;
    col: number;
};

/**
 * How a board hangs off the tree of boards: the index of the earlier entry it
 * attaches to and the gate cell (pre-rotation) it is entered through. The
 * start board carries no attach.
 */
export type D4ParagonAttach = {
    to?: number | null;
    gate?: D4ParagonNodeRef | null;
};

/** One attached paragon board: which board, how it is turned, and its glyph. */
export type D4ParagonEntry = {
    board: string;
    rotation?: number | null;
    glyph?: string | null;
    glyph_level?: number | null;
    /** The allocated path. Builds saved before the path model have none. */
    nodes?: D4ParagonNodeRef[];
    attach?: D4ParagonAttach | null;
    notables?: string[];
};

/**
 * One rolled affix line. Legacy payloads store the display string; the
 * structured object carries the affix key, rolled value and greater flag the
 * stat calculator counts. Always read through `affixLabel()` in build.ts.
 */
export type D4AffixEntry =
    | string
    | {
          text?: string | null;
          affix?: string | null;
          value?: number | null;
          greater?: boolean | null;
      };

/** A tempered affix and the tier it rolled at. */
export type D4TemperedAffix = {
    affix: string;
    tier?: number | null;
    value?: number | null;
};

export type D4GearItem = {
    name?: string | null;
    item_type?: string | null;
    rarity?: string | null;
    aspect?: string | null;
    affixes?: D4AffixEntry[];
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

/**
 * The stat calculator's block on a saved build: engine-computed baselines
 * plus the assumptions they rest on. `wrote` names the payload fields the
 * calculator itself filled, so recomputation updates its own numbers while
 * hand-entered ones stand.
 */
export type D4ComputedStats = {
    dps: number | null;
    ehp: number | null;
    life?: number | null;
    armor?: number | null;
    item_power?: number | null;
    weapon?: {
        name?: string | null;
        dps?: number;
        average_hit?: number;
        attacks_per_second?: number;
        item_type?: string;
        speed_class?: string;
        item_power?: number;
    } | null;
    skills?: {
        skill: string;
        rank: number;
        weapon_damage_percent: number;
        dps: number;
    }[];
    coverage?: Record<string, number>;
    assumptions?: string[];
    wrote?: string[];
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
    computed?: D4ComputedStats | null;
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
/**
 * An atlas crop reference as stored in imported data (no URL — the sheet may
 * or may not be extracted; the client tries `/games/diablo-4/icons/{texture}.webp`).
 */
export type D4CellIcon = {
    texture: number;
    frame: number;
    u0: number;
    v0: number;
    u1: number;
    v1: number;
    w?: number | null;
    h?: number | null;
};

export type D4ParagonCell = {
    name?: string | null;
    key?: string | null;
    rarity?: string | null;
    is_gate?: boolean;
    has_socket?: boolean;
    attributes?: string[];
    /** The node's icon mask crop, when the importer resolved one. */
    icon?: D4CellIcon | null;
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
    /** The hover-card dictionary, keyed by entity display name. */
    entities: Record<string, D4Entity>;
    /** Grid data for the boards this build attaches, when the server has it. */
    paragonBoards?: D4ParagonBoardGrid[];
    /** The class skill tree (SkillKit nodes + edges), when the server has it. */
    skillTree?: D4SkillTree | null;
};

/** One node of a class skill tree, positioned as the game lays it out. */
export type D4SkillTreeNode = {
    id: number;
    x: number;
    y: number;
    level: number;
    kind: 'hub' | 'skill' | 'passive' | 'modifier' | 'socket';
    name?: string | null;
    power_sno?: number | null;
    max_ranks?: number | null;
};

export type D4SkillTree = {
    nodes: D4SkillTreeNode[];
    edges: [number, number][];
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
