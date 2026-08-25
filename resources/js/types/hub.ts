/** A game as the hub, waitlist and my-builds pages receive it. */
export type HubGame = {
    slug: string;
    name: string;
    short_name: string;
    accent: string;
    icon: string;
    description?: string | null;
    is_live?: boolean;
    url?: string;
};

/** A build shaped for a BuildCard (see BuildHubQuery::card()). */
export type HubBuild = {
    id: string;
    name: string;
    summary: string | null;
    visibility: 'draft' | 'public';
    class: string | null;
    ascendancy: string | null;
    stage: string | null;
    tier: string | null;
    level: number | null;
    dps: number | null;
    ehp: number | null;
    cost_divine: number | null;
    hardcore_viable: boolean | null;
    endorsements: number;
    author: string | null;
    patch: string | null;
    url: string;
    updated_at: string | null;
};

export type HubFilters = {
    classes: string[];
    ascendancy: string | null;
    stage: string | null;
    min_divine: number | null;
    max_divine: number | null;
    current_patch_only: boolean;
    hardcore_viable: boolean;
    sort: string;
};

/** Every filter plus the view toggle, i.e. the hub's whole query string. */
export type HubQueryState = HubFilters & {
    view: HubView;
};

export type HubView = 'grid' | 'list';

/** Result counts per class, ignoring the class filter itself. */
export type HubFacets = {
    classes: Record<string, number>;
};

/** The filter rail's option lists (see GameReference). */
export type HubOptions = {
    classes: string[];
    ascendancies: HubAscendancy[];
    stages: string[];
    sorts: string[];
};

export type HubAscendancy = {
    name: string;
    class_name: string | null;
};

/** A queued game as the waitlist ranks it. */
export type QueuedGame = HubGame & {
    votes: number;
    position: number;
};

/** One game's builds on /my-builds, drafts already pinned to the top. */
export type MyBuildsGroup = {
    game: HubGame & { is_live: boolean; url: string };
    builds: HubBuild[];
};

export type MyBuildsStats = {
    published: number;
    drafts: number;
    endorsements: number;
    member_since: string | null;
};
