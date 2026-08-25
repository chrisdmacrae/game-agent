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
 */
const props = defineProps<{
    game: HubGame;
    patch: string | null;
    builds: HubBuild[];
    filters: HubFilters;
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

const mcpUrl = computed(() => gameMcpUrl(page.props.mcpUrl, props.game.slug));

const publishOpen = ref(false);

/** Only the list and the rail change; the connect panel and strip stay put. */
const RELOAD_ONLY = ['builds', 'filters', 'facets', 'options', 'view'];

const ANY_ASCENDANCY = '';
const ANY_STAGE = '';

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

const ascendancyOptions = computed(() => [
    { label: 'Any ascendancy', value: ANY_ASCENDANCY },
    ...props.options.ascendancies.map((ascendancy) => ({
        label: ascendancy.name,
        value: ascendancy.name,
    })),
]);

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
 * The ascendancy list is derived from the selected classes, so changing the
 * classes drops an ascendancy that may no longer be on offer.
 */
function toggleClass(name: string, selected: boolean): void {
    apply({
        classes: selected
            ? [...props.filters.classes, name]
            : props.filters.classes.filter((entry) => entry !== name),
        ascendancy: null,
    });
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

const minDivine = ref(toField(props.filters.min_divine));
const maxDivine = ref(toField(props.filters.max_divine));

function toField(value: number | null): string {
    return value === null ? '' : String(value);
}

function toNumber(value: string): number | null {
    const trimmed = value.trim();

    return trimmed === '' || Number.isNaN(Number(trimmed))
        ? null
        : Number(trimmed);
}

let divineTimer: ReturnType<typeof setTimeout> | undefined;

/** Typing a budget should not fire a request per keystroke. */
function onDivineInput(): void {
    clearTimeout(divineTimer);

    divineTimer = setTimeout(() => {
        apply({
            min_divine: toNumber(minDivine.value),
            max_divine: toNumber(maxDivine.value),
        });
    }, 350);
}

watch(
    () => [props.filters.min_divine, props.filters.max_divine] as const,
    ([min, max]) => {
        minDivine.value = toField(min);
        maxDivine.value = toField(max);
    },
);

onBeforeUnmount(() => clearTimeout(divineTimer));

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
            remove: () => toggleClass(name, false),
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
                    <div :class="railSection">
                        <p :class="LABEL_CLASS">Class</p>
                        <div
                            v-if="options.classes.length"
                            class="flex flex-col gap-3"
                        >
                            <Checkbox
                                v-for="name in options.classes"
                                :key="name"
                                :label="name"
                                :count="facets.classes[name] ?? 0"
                                :model-value="filters.classes.includes(name)"
                                @update:model-value="toggleClass(name, $event)"
                            />
                        </div>
                        <p v-else class="text-[13px] text-[var(--fg-3)]">
                            No class data imported for this game.
                        </p>
                    </div>

                    <div :class="railSection">
                        <p :class="LABEL_CLASS">Ascendancy</p>
                        <Select
                            size="sm"
                            :options="ascendancyOptions"
                            :model-value="filters.ascendancy ?? ANY_ASCENDANCY"
                            aria-label="Ascendancy"
                            :disabled="options.ascendancies.length === 0"
                            @update:model-value="
                                apply({ ascendancy: String($event) || null })
                            "
                        />
                    </div>

                    <div :class="railSection">
                        <p :class="LABEL_CLASS">Game stage</p>
                        <RadioGroup
                            :model-value="filters.stage ?? ANY_STAGE"
                            @update:model-value="
                                apply({ stage: String($event) || null })
                            "
                        >
                            <Radio :value="ANY_STAGE" label="Any stage" />
                            <Radio
                                v-for="stage in options.stages"
                                :key="stage"
                                :value="stage"
                                :label="stageLabel(stage) ?? stage"
                            />
                        </RadioGroup>
                    </div>

                    <div :class="railSection">
                        <p :class="LABEL_CLASS">Budget</p>
                        <div class="flex gap-3">
                            <Input
                                v-model="minDivine"
                                size="sm"
                                mono
                                inputmode="decimal"
                                placeholder="Min div"
                                aria-label="Minimum divine"
                                @update:model-value="onDivineInput"
                            />
                            <Input
                                v-model="maxDivine"
                                size="sm"
                                mono
                                inputmode="decimal"
                                placeholder="Max div"
                                aria-label="Maximum divine"
                                @update:model-value="onDivineInput"
                            />
                        </div>
                    </div>

                    <div class="flex flex-col gap-4">
                        <Switch
                            label="Current patch only"
                            :model-value="filters.current_patch_only"
                            @update:model-value="
                                apply({ current_patch_only: $event })
                            "
                        />
                        <Switch
                            label="Hardcore viable"
                            :model-value="filters.hardcore_viable"
                            @update:model-value="
                                apply({ hardcore_viable: $event })
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
