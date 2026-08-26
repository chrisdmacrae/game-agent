<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import Badge from '@/components/byb/Badge.vue';
import BuildCard from '@/components/byb/BuildCard.vue';
import Button from '@/components/byb/Button.vue';
import Card from '@/components/byb/Card.vue';
import Checkbox from '@/components/byb/Checkbox.vue';
import ConnectPanel from '@/components/byb/ConnectPanel.vue';
import { LABEL_CLASS } from '@/components/byb/controls';
import IconButton from '@/components/byb/IconButton.vue';
import Input from '@/components/byb/Input.vue';
import Radio from '@/components/byb/Radio.vue';
import RadioGroup from '@/components/byb/RadioGroup.vue';
import Select from '@/components/byb/Select.vue';
import Switch from '@/components/byb/Switch.vue';
import Tag from '@/components/byb/Tag.vue';
import { stageColor } from '@/components/byb/tokens';
import PublishBuildDialog from '@/components/PublishBuildDialog.vue';
import SeoHead from '@/components/SeoHead.vue';
import { buildCardProps, gameMcpUrl, stageLabel } from '@/lib/hub';
import { cn } from '@/lib/utils';
import { myBuilds } from '@/routes';
import { show as gameShow } from '@/routes/games';
import type {
    HubBuild,
    HubFacets,
    HubFilterDescriptor,
    HubFilterParam,
    HubFilters,
    HubGame,
    HubOptions,
    HubQueryState,
    HubView,
} from '@/types/hub';

/**
 * The game hub (scope §3.4). Every filter, the sort and the view toggle live in
 * the query string and round-trip through the server — the list is the point of
 * the page, so it is never filtered in the browser.
 *
 * The rail itself is drawn from `filterRail`, the descriptor the game's
 * GameBuildProfile hands over: which filters a game offers is a server-side
 * decision, so a new game means a profile edit and nothing here.
 */
const props = defineProps<{
    game: HubGame;
    patch: string | null;
    builds: HubBuild[];
    filters: HubFilters;
    filterRail: HubFilterDescriptor[];
    view: HubView;
    facets: HubFacets;
    options: HubOptions;
    yourBuilds: HubBuild[];
}>();

const page = usePage();

const user = computed(() => page.props.auth?.user ?? null);

const handle = computed(
    () => (user.value?.handle as string | undefined) ?? user.value?.name ?? '',
);

const mcpUrl = computed(() =>
    gameMcpUrl(page.props.mcpUrl, props.game.slug, page.props.mcpUrls),
);

const publishOpen = ref(false);

/** Only the list and the rail change; the connect panel and strip stay put. */
const RELOAD_ONLY = ['builds', 'filters', 'facets', 'options', 'view'];

/** The empty value of a select or radio group, i.e. "any". */
const ANY = '';

const SORT_LABELS: Record<string, string> = {
    updated: 'Newest',
    endorsements: 'Most endorsed',
    dps: 'Best DPS',
    cost: 'Cheapest',
};

const sortOptions = computed(() =>
    props.options.sorts.map((sort) => ({
        label: SORT_LABELS[sort] ?? sort,
        value: sort,
    })),
);

/** Toggles sit together at the foot of the rail; the rest are bordered rows. */
const railSections = computed(() =>
    props.filterRail.filter((filter) => filter.type !== 'toggle'),
);

const railToggles = computed(() =>
    props.filterRail.filter((filter) => filter.type === 'toggle'),
);

type Choice = { label: string; value: string };

/** A control's option list, normalised to label/value pairs. */
function choices(filter: HubFilterDescriptor): Choice[] {
    if (filter.options === 'ascendancies') {
        return props.options.ascendancies.map((ascendancy) => ({
            label: ascendancy.name,
            value: ascendancy.name,
        }));
    }

    if (filter.options === 'stages') {
        return props.options.stages.map((stage) => ({
            label: stageLabel(stage) ?? stage,
            value: stage,
        }));
    }

    if (filter.options === 'classes') {
        return props.options.classes.map((name) => ({
            label: name,
            value: name,
        }));
    }

    return [];
}

/** The same list with the "any" entry in front, for a select. */
function choicesWithAny(filter: HubFilterDescriptor): Choice[] {
    return [
        { label: filter.placeholder ?? 'Any', value: ANY },
        ...choices(filter),
    ];
}

/** Result counts per option, for the controls that report them. */
function countFor(filter: HubFilterDescriptor, value: string): number {
    return filter.options === 'classes'
        ? (props.facets.classes[value] ?? 0)
        : 0;
}

function listValue(param: HubFilterParam): string[] {
    const value = props.filters[param];

    return Array.isArray(value) ? value : [];
}

function stringValue(param: HubFilterParam): string {
    const value = props.filters[param];

    return typeof value === 'string' ? value : ANY;
}

function boolValue(param: HubFilterParam): boolean {
    return props.filters[param] === true;
}

function setParam(param: HubFilterParam, value: unknown): void {
    apply({ [param]: value } as Partial<HubQueryState>);
}

/** Drop defaults so a pristine hub keeps a clean URL. */
function queryFrom(overrides: Partial<HubQueryState>) {
    const state: HubQueryState = {
        ...props.filters,
        view: props.view,
        ...overrides,
    };

    return {
        classes: state.classes.length ? state.classes : undefined,
        ascendancy: state.ascendancy || undefined,
        stage: state.stage || undefined,
        min_divine: state.min_divine ?? undefined,
        max_divine: state.max_divine ?? undefined,
        current_patch_only: state.current_patch_only ? 1 : undefined,
        hardcore_viable: state.hardcore_viable ? 1 : undefined,
        sort: state.sort === 'updated' ? undefined : state.sort,
        view: state.view === 'grid' ? undefined : state.view,
    };
}

function apply(overrides: Partial<HubQueryState>): void {
    router.get(
        gameShow.url(props.game.slug, { query: queryFrom(overrides) }),
        {},
        {
            only: RELOAD_ONLY,
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

/**
 * Add or drop one value of a multi-select filter. The ascendancy list is
 * derived from the selected classes, so changing them drops an ascendancy that
 * may no longer be on offer — a no-op on a hub that has no ascendancy filter.
 */
function toggleChoice(
    param: HubFilterParam,
    value: string,
    selected: boolean,
): void {
    const current = listValue(param);

    apply({
        [param]: selected
            ? [...current, value]
            : current.filter((entry) => entry !== value),
        ascendancy: null,
    } as Partial<HubQueryState>);
}

function clearAll(): void {
    router.get(
        gameShow.url(props.game.slug, {
            query: { view: props.view === 'grid' ? undefined : props.view },
        }),
        {},
        {
            only: RELOAD_ONLY,
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

/** The typed-in value of every number_range input the rail offers. */
const rangeDraft = ref<Record<string, string>>(draftFromFilters());

function rangeParams(): HubFilterParam[] {
    return props.filterRail.flatMap((filter) =>
        filter.fields.map((field) => field.param),
    );
}

function draftFromFilters(): Record<string, string> {
    const draft: Record<string, string> = {};

    rangeParams().forEach((param) => {
        const value = props.filters[param];

        draft[param] = typeof value === 'number' ? String(value) : '';
    });

    return draft;
}

function toNumber(value: string): number | null {
    const trimmed = value.trim();

    return trimmed === '' || Number.isNaN(Number(trimmed))
        ? null
        : Number(trimmed);
}

let rangeTimer: ReturnType<typeof setTimeout> | undefined;

/** Typing a budget should not fire a request per keystroke. */
function onRangeInput(): void {
    clearTimeout(rangeTimer);

    rangeTimer = setTimeout(() => {
        const overrides: Record<string, number | null> = {};

        rangeParams().forEach((param) => {
            overrides[param] = toNumber(rangeDraft.value[param] ?? '');
        });

        apply(overrides as Partial<HubQueryState>);
    }, 350);
}

watch(
    () => props.filters,
    () => {
        rangeDraft.value = draftFromFilters();
    },
    { deep: true },
);

onBeforeUnmount(() => clearTimeout(rangeTimer));

type ActiveFilter = {
    key: string;
    label: string;
    dot?: string;
    remove: () => void;
};

const activeFilters = computed<ActiveFilter[]>(() => {
    const active: ActiveFilter[] = [];

    props.filters.classes.forEach((name) =>
        active.push({
            key: `class:${name}`,
            label: name,
            remove: () => toggleChoice('classes', name, false),
        }),
    );

    if (props.filters.ascendancy) {
        active.push({
            key: 'ascendancy',
            label: props.filters.ascendancy,
            remove: () => apply({ ascendancy: null }),
        });
    }

    if (props.filters.stage) {
        const label = stageLabel(props.filters.stage);

        active.push({
            key: 'stage',
            label: label ?? props.filters.stage,
            dot: stageColor(label),
            remove: () => apply({ stage: null }),
        });
    }

    if (props.filters.min_divine !== null) {
        active.push({
            key: 'min_divine',
            label: `${props.filters.min_divine} div and up`,
            remove: () => apply({ min_divine: null }),
        });
    }

    if (props.filters.max_divine !== null) {
        active.push({
            key: 'max_divine',
            label: `${props.filters.max_divine} div and under`,
            remove: () => apply({ max_divine: null }),
        });
    }

    if (props.filters.current_patch_only) {
        active.push({
            key: 'current_patch_only',
            label: 'Current patch only',
            remove: () => apply({ current_patch_only: false }),
        });
    }

    if (props.filters.hardcore_viable) {
        active.push({
            key: 'hardcore_viable',
            label: 'Hardcore viable',
            remove: () => apply({ hardcore_viable: false }),
        });
    }

    return active;
});

const railSection =
    'border-b border-[var(--border-hairline)] pb-6 mb-6 flex flex-col gap-4';
</script>

<template>
    <div>
        <!-- Title, description and card come from GameHubController's PageMeta. -->
        <SeoHead />

        <div class="py-10">
            <Card variant="grid" padding="var(--sp-8)" class="mb-10">
                <div class="grid gap-8 lg:grid-cols-2">
                    <div>
                        <p :class="cn(LABEL_CLASS, 'text-[var(--teal-400)]')">
                            Get started
                        </p>
                        <h1
                            class="mt-4 font-display text-[28px] leading-[1.14] font-bold tracking-[-0.02em] text-[var(--fg-1)]"
                        >
                            Theorycraft {{ game.name }} builds from your
                            assistant
                        </h1>
                        <p
                            class="mt-4 text-[15px] leading-[1.6] text-[var(--fg-2)]"
                        >
                            Add the server to Claude or ChatGPT, then ask for a
                            build. It reads the current
                            <span
                                v-if="patch"
                                class="font-mono text-[var(--fg-1)]"
                                >{{ patch }}</span
                            >
                            <span v-else>live</span>
                            data and writes back here when you publish.
                        </p>
                        <p class="mt-6 text-[13px] text-[var(--fg-3)]">
                            One URL, no install, no config file. The server is
                            hosted — you point your client at it.
                        </p>
                    </div>

                    <ConnectPanel :mcp-url="mcpUrl" filename="server url" />
                </div>
            </Card>

            <section v-if="user && yourBuilds.length" class="mb-10">
                <div class="mb-5 flex flex-wrap items-end gap-4">
                    <div>
                        <p :class="cn(LABEL_CLASS, 'text-[var(--teal-400)]')">
                            Your builds here
                        </p>
                        <h2
                            class="mt-1.5 font-display text-[22px] leading-[1.2] font-bold tracking-[-0.02em] text-[var(--fg-1)]"
                        >
                            {{ handle }} · {{ game.name }}
                        </h2>
                    </div>
                    <div class="ml-auto flex items-center gap-3">
                        <Button
                            size="sm"
                            variant="ghost"
                            icon-right="chevron-right"
                            as-child
                        >
                            <Link :href="myBuilds()">All my builds</Link>
                        </Button>
                        <Button
                            size="sm"
                            variant="accent"
                            icon="plus"
                            @click="publishOpen = true"
                        >
                            Publish build
                        </Button>
                    </div>
                </div>

                <div class="grid gap-5 md:grid-cols-3">
                    <BuildCard
                        v-for="build in yourBuilds"
                        :key="build.id"
                        v-bind="buildCardProps(build)"
                    />
                </div>
            </section>

            <div class="mb-6 flex flex-wrap items-end gap-4">
                <div>
                    <p :class="LABEL_CLASS">Published builds</p>
                    <h2
                        class="mt-1.5 font-display text-[28px] leading-[1.14] font-bold tracking-[-0.02em] text-[var(--fg-1)]"
                    >
                        {{ game.name }}
                    </h2>
                </div>

                <Badge v-if="patch" tone="info" class="mb-1.5">
                    {{ patch }}
                </Badge>

                <div class="ml-auto flex items-center gap-3">
                    <Select
                        size="sm"
                        :options="sortOptions"
                        :model-value="filters.sort"
                        aria-label="Sort builds"
                        class="w-[150px]"
                        @update:model-value="apply({ sort: String($event) })"
                    />
                    <IconButton
                        size="sm"
                        icon="layout-grid"
                        label="Grid view"
                        :active="view === 'grid'"
                        @click="apply({ view: 'grid' })"
                    />
                    <IconButton
                        size="sm"
                        icon="list"
                        label="List view"
                        :active="view === 'list'"
                        @click="apply({ view: 'list' })"
                    />
                </div>
            </div>

            <div class="flex flex-col items-start gap-8 lg:flex-row">
                <aside class="w-full lg:w-[var(--layout-rail)] lg:shrink-0">
                    <div
                        v-for="filter in railSections"
                        :key="filter.key"
                        :class="railSection"
                    >
                        <p :class="LABEL_CLASS">{{ filter.label }}</p>

                        <template v-if="filter.type === 'checkboxes'">
                            <div
                                v-if="choices(filter).length"
                                class="flex flex-col gap-3"
                            >
                                <Checkbox
                                    v-for="choice in choices(filter)"
                                    :key="choice.value"
                                    :label="choice.label"
                                    :count="countFor(filter, choice.value)"
                                    :model-value="
                                        listValue(filter.params[0]).includes(
                                            choice.value,
                                        )
                                    "
                                    @update:model-value="
                                        toggleChoice(
                                            filter.params[0],
                                            choice.value,
                                            $event,
                                        )
                                    "
                                />
                            </div>
                            <p v-else class="text-[13px] text-[var(--fg-3)]">
                                No {{ filter.label.toLowerCase() }} data
                                imported for this game.
                            </p>
                        </template>

                        <Select
                            v-else-if="filter.type === 'select'"
                            size="sm"
                            :options="choicesWithAny(filter)"
                            :model-value="stringValue(filter.params[0])"
                            :aria-label="filter.label"
                            :disabled="choices(filter).length === 0"
                            @update:model-value="
                                setParam(
                                    filter.params[0],
                                    String($event) || null,
                                )
                            "
                        />

                        <RadioGroup
                            v-else-if="filter.type === 'radio'"
                            :model-value="stringValue(filter.params[0])"
                            @update:model-value="
                                setParam(
                                    filter.params[0],
                                    String($event) || null,
                                )
                            "
                        >
                            <Radio
                                v-for="choice in choicesWithAny(filter)"
                                :key="choice.value"
                                :value="choice.value"
                                :label="choice.label"
                            />
                        </RadioGroup>

                        <div
                            v-else-if="filter.type === 'number_range'"
                            class="flex gap-3"
                        >
                            <Input
                                v-for="field in filter.fields"
                                :key="field.param"
                                v-model="rangeDraft[field.param]"
                                size="sm"
                                mono
                                inputmode="decimal"
                                :placeholder="field.placeholder"
                                :aria-label="field.label"
                                @update:model-value="onRangeInput"
                            />
                        </div>
                    </div>

                    <div v-if="railToggles.length" class="flex flex-col gap-4">
                        <Switch
                            v-for="filter in railToggles"
                            :key="filter.key"
                            :label="filter.label"
                            :model-value="boolValue(filter.params[0])"
                            @update:model-value="
                                setParam(filter.params[0], $event)
                            "
                        />
                    </div>
                </aside>

                <section class="w-full min-w-0 flex-1">
                    <div class="mb-5 flex flex-wrap items-center gap-3">
                        <span class="font-mono text-[12px] text-[var(--fg-3)]">
                            {{ builds.length }} results
                        </span>
                        <Tag
                            v-for="filter in activeFilters"
                            :key="filter.key"
                            :dot="filter.dot"
                            removable
                            :remove-label="`Remove ${filter.label} filter`"
                            @remove="filter.remove()"
                        >
                            {{ filter.label }}
                        </Tag>
                    </div>

                    <div
                        v-if="builds.length"
                        :class="
                            cn(
                                'grid gap-5',
                                view === 'grid'
                                    ? 'md:grid-cols-2'
                                    : 'grid-cols-1',
                            )
                        "
                    >
                        <BuildCard
                            v-for="build in builds"
                            :key="build.id"
                            :orientation="view"
                            v-bind="buildCardProps(build)"
                        />
                    </div>

                    <div
                        v-else
                        class="rounded-[var(--radius-md)] border border-dashed border-[var(--border-subtle)] p-8 text-center"
                    >
                        <p class="text-[15px] text-[var(--fg-2)]">
                            <template v-if="activeFilters.length">
                                No builds match these filters.
                            </template>
                            <template v-else>
                                Nothing published for {{ game.name }} yet.
                            </template>
                        </p>
                        <p class="mt-1.5 text-[13px] text-[var(--fg-3)]">
                            <template v-if="activeFilters.length">
                                Drop a filter or clear them all.
                            </template>
                            <template v-else>
                                Connect the server, ask for a build, and publish
                                it here.
                            </template>
                        </p>
                        <Button
                            v-if="activeFilters.length"
                            class="mt-6"
                            size="sm"
                            variant="ghost"
                            icon="x"
                            @click="clearAll"
                        >
                            Clear filters
                        </Button>
                    </div>
                </section>
            </div>
        </div>

        <PublishBuildDialog v-model:open="publishOpen" :mcp-url="mcpUrl" />
    </div>
</template>
