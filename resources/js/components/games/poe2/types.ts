/**
 * The Path of Exile 2 build payload, as the MCP tools write it and
 * `App\Domain\Poe2\Validation\BuildRules` validates it.
 *
 * Every other game will have a different anatomy — no support gems, no spirit,
 * different slots — so these types stay PoE 2 specific on purpose. The route
 * pages under `pages/Builds` are thin shells that pick the game's renderer.
 */

/** A support gem. Older rows still hold plain names; readers accept both. */
export type Poe2SkillSupport = {
    name: string;
    effect?: string | null;
};

export type Poe2SkillSetup = {
    gem: string;
    supports?: (string | Poe2SkillSupport)[];
    role?: string | null;
    level?: number | null;
    quality?: number | null;
    cost?: string | null;
    tags?: string[];
    reported?: string | null;
};

export type Poe2Instill = {
    notable: string;
    emotions?: string[];
};

export type Poe2GearItem = {
    slot: string;
    rarity: string;
    name?: string | null;
    base?: string | null;
    mods?: string[];
    /** One entry per rune socket; null or an empty string is an empty socket. */
    runes?: (string | null)[];
    instill?: Poe2Instill | null;
};

export type Poe2Jewel = {
    name: string;
    rarity: string;
    socket_node_id?: number;
    mods?: string[];
};

/** Charms and flasks share the name/note shape. */
export type Poe2NamedNote = {
    name: string;
    note?: string | null;
};

export type Poe2Milestone = {
    level: number;
    text: string;
};

export type Poe2StatRow = {
    label: string;
    value: string;
};

export type Poe2GrantedNode = {
    node_id: number;
    source: string;
    detail?: string;
};

export type Poe2Passives = {
    keystones?: string[];
    notables?: string[];
    points_used?: number;
    /** Pasted from the in-game planner by a human; never invented. */
    import_string?: string | null;
    node_ids?: number[];
    ascendancy_nodes?: string[];
    granted_nodes?: Poe2GrantedNode[];
};

export type Poe2BuildDefinition = {
    class?: string | null;
    ascendancy?: string | null;
    level?: number | null;
    stage?: string | null;
    tier?: string | null;
    dps?: number | null;
    ehp?: number | null;
    cost_divine?: number | null;
    hardcore_viable?: boolean | null;
    spirit_available?: number | null;
    content_tier?: string | null;
    resistances?: Record<string, number> | null;
    skills?: Poe2SkillSetup[];
    gear?: Poe2GearItem[];
    jewels?: Poe2Jewel[];
    charms?: Poe2NamedNote[];
    flasks?: Poe2NamedNote[];
    milestones?: Poe2Milestone[];
    passives?: Poe2Passives;
    stats?: {
        offence?: Poe2StatRow[];
        defence?: Poe2StatRow[];
    } | null;
    how_it_plays?: string[];
    works_because?: string[];
    watch_out_for?: string[];
};

export type Poe2Validation = {
    valid?: boolean;
    violations?: string[];
    warnings?: string[];
    suggestions?: string[];
};

/** A gem, support, passive or unique resolved against the game data. */
export type Poe2Entity = {
    kind: 'gem' | 'support' | 'passive' | 'unique';
    name: string;
    color?: string | null;
    description?: string | null;
    tags?: string[];
    spirit_reservation?: number | null;
    stat_text?: string[];
    passive_kind?: string;
    stats?: string[];
    sprite?: { x: number; y: number; w: number; h: number } | null;
    icon?: string | null;
    base_name?: string;
    item_class?: string | null;
    mods?: string[];
};

/** One paperdoll cell, resolved by `BuildPageEnricher::gearItemView()`. */
export type Poe2GearViewItem = {
    slot: string | null;
    rarity: string;
    name: string | null;
    base: string | null;
    icon: string | null;
    implicits: string[];
    mods: string[];
    runes: (string | null)[];
    instill?: Poe2Instill | null;
};

export type Poe2GearView = {
    slots: Record<string, Poe2GearViewItem>;
    jewels: Poe2GearViewItem[];
};

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

export type Poe2BuildShowProps = {
    build: {
        id: string;
        name: string;
        summary: string | null;
        visibility: string;
        definition: Poe2BuildDefinition;
        validation: Poe2Validation | Record<string, never>;
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
    entities: Record<string, Poe2Entity>;
    spriteUrl: string;
    treeUrl: string | null;
    ascendancyKey: string | null;
    gearView: Poe2GearView;
    ascendancyPathIds: number[];
    similarBuilds: SimilarBuild[];
};

export type PublishCheck = {
    key: string;
    label: string;
    passed: boolean;
    detail: string | null;
};

export type Poe2BuildEditProps = {
    game: BuildGame;
    build: {
        id: string;
        name: string;
        summary: string | null;
        guide_markdown: string | null;
        visibility: 'draft' | 'public';
        definition: Poe2BuildDefinition;
        validation: Poe2Validation | null;
        game_version: string | null;
        updated_at: string | null;
        url: string;
    };
    options: {
        classes: string[];
        ascendancies: { name: string; class_name: string | null }[];
        stages: string[];
        tiers: string[];
    };
    checklist: PublishCheck[];
};

/** The gear slots PoE 2 exposes, in the order the editor lists them. */
export const POE2_GEAR_SLOTS = [
    'weapon1',
    'offhand1',
    'weapon2',
    'offhand2',
    'helmet',
    'body',
    'gloves',
    'boots',
    'amulet',
    'ring1',
    'ring2',
    'belt',
] as const;

export const POE2_SLOT_LABELS: Record<string, string> = {
    helmet: 'Helmet',
    body: 'Body armour',
    gloves: 'Gloves',
    boots: 'Boots',
    amulet: 'Amulet',
    ring1: 'Ring 1',
    ring2: 'Ring 2',
    belt: 'Belt',
    weapon1: 'Weapon',
    offhand1: 'Off-hand',
    weapon2: 'Weapon (set II)',
    offhand2: 'Off-hand (set II)',
};

export const POE2_RARITIES = ['normal', 'magic', 'rare', 'unique'] as const;
