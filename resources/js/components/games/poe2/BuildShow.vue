<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { TabsContent } from 'reka-ui';
import { computed, onMounted, ref, watch } from 'vue';
import Card from '@/components/byb/Card.vue';
import Icon from '@/components/byb/Icon.vue';
import Tabs from '@/components/byb/Tabs.vue';
import { entityIndex } from '@/components/games/poe2/build';
import BuildHeader from '@/components/games/poe2/build/BuildHeader.vue';
import BuildSidebar from '@/components/games/poe2/build/BuildSidebar.vue';
import EntityCard from '@/components/games/poe2/build/EntityCard.vue';
import GearTab from '@/components/games/poe2/build/GearTab.vue';
import NotesTab from '@/components/games/poe2/build/NotesTab.vue';
import OverviewTab from '@/components/games/poe2/build/OverviewTab.vue';
import PassivesTab from '@/components/games/poe2/build/PassivesTab.vue';
import SkillsTab from '@/components/games/poe2/build/SkillsTab.vue';
import type {
    Poe2BuildShowProps,
    Poe2Entity,
    Poe2Validation,
} from '@/components/games/poe2/types';

/**
 * The Path of Exile 2 build page (scope §3.7). Gems, support gems, spirit and
 * the passive tree are PoE 2 vocabulary, so this whole renderer is game
 * specific — `pages/Builds/Show.vue` only picks it.
 */
const props = defineProps<Poe2BuildShowProps>();

const page = usePage();

const signedIn = computed(() => Boolean(page.props.auth?.user));

const definition = computed(() => props.build.definition);

const validation = computed(() => props.build.validation as Poe2Validation);

const TABS = [
    { value: 'overview', label: 'Overview', icon: 'gauge' as const },
    { value: 'skills', label: 'Skills', icon: 'swords' as const },
    { value: 'gear', label: 'Gear', icon: 'shield' as const },
    { value: 'passives', label: 'Passives', icon: 'zap' as const },
    { value: 'notes', label: 'Notes', icon: 'book-open' as const },
];

const tab = ref('overview');

// The tab rides in the hash so a link can land on Gear or Passives directly.
onMounted(() => {
    const wanted = window.location.hash.replace('#', '');

    if (TABS.some((entry) => entry.value === wanted)) {
        tab.value = wanted;
    }
});

watch(tab, (value) => {
    if (typeof window === 'undefined') {
        return;
    }

    window.history.replaceState(
        window.history.state,
        '',
        `${window.location.pathname}${window.location.search}#${value}`,
    );
});

const showSidebar = computed(
    () => tab.value === 'overview' || tab.value === 'notes',
);

const index = computed(() => entityIndex(props.entities));

function entityFor(name: string): Poe2Entity | null {
    return index.value[name.toLowerCase()] ?? null;
}

// One floating hover card, driven by delegation so it also works for
// data-entity spans inside the server-rendered guide HTML.
const hovered = ref<Poe2Entity | null>(null);
const cardStyle = ref<Record<string, string>>({});
let hideTimer: ReturnType<typeof setTimeout> | null = null;

function showCardFor(target: HTMLElement): void {
    const name = target.dataset.entity;
    const entity = name ? entityFor(name) : null;

    if (!entity) {
        return;
    }

    if (hideTimer) {
        clearTimeout(hideTimer);
    }

    const rect = target.getBoundingClientRect();
    const cardWidth = 340;
    const left = Math.min(
        Math.max(8, rect.left),
        window.innerWidth - cardWidth - 8,
    );
    const below = rect.bottom + 8;
    const flip = below > window.innerHeight - 260;

    cardStyle.value = {
        left: `${left}px`,
        ...(flip
            ? { bottom: `${window.innerHeight - rect.top + 8}px` }
            : { top: `${below}px` }),
    };
    hovered.value = entity;
}

function onOver(event: MouseEvent): void {
    const target = (event.target as HTMLElement).closest<HTMLElement>(
        '[data-entity]',
    );

    if (target) {
        showCardFor(target);
    }
}

function onOut(event: MouseEvent): void {
    const target = (event.target as HTMLElement).closest<HTMLElement>(
        '[data-entity]',
    );

    if (!target) {
        return;
    }

    hideTimer = setTimeout(() => {
        hovered.value = null;
    }, 120);
}
</script>

<template>
    <div class="py-8" @mouseover="onOver" @mouseout="onOut">
        <BuildHeader
            :build="build"
            :game="game"
            :viewer="viewer"
            :signed-in="signedIn"
        />

        <Card
            v-if="validation.violations?.length"
            class="mt-4 border-[var(--red-600)]"
        >
            <div class="flex items-start gap-3">
                <Icon
                    name="triangle-alert"
                    :size="16"
                    class="mt-0.5 shrink-0 text-[var(--red-400)]"
                />
                <div>
                    <p class="text-[15px] font-semibold text-[var(--fg-1)]">
                        This build breaks game rules the checker knows about.
                    </p>
                    <ul class="mt-2 flex flex-col gap-1">
                        <li
                            v-for="violation in validation.violations"
                            :key="violation"
                            class="text-[13px] text-[var(--red-400)]"
                        >
                            {{ violation }}
                        </li>
                    </ul>
                </div>
            </div>
        </Card>
        <Card v-else-if="validation.valid" class="mt-4">
            <div class="flex items-center gap-3 text-[13px] text-[var(--fg-2)]">
                <Icon
                    name="circle-check"
                    :size="16"
                    class="shrink-0 text-[var(--teal-400)]"
                />
                Passes every game-rule check: support limits, spirit budget,
                passive existence.
            </div>
        </Card>

        <Tabs v-model="tab" :tabs="TABS" class="mt-8">
            <div class="mt-6 flex flex-col items-start gap-6 lg:flex-row">
                <div class="min-w-0 flex-1">
                    <TabsContent value="overview">
                        <OverviewTab :definition="definition" />
                    </TabsContent>
                    <TabsContent value="skills">
                        <SkillsTab
                            :definition="definition"
                            :entity-for="entityFor"
                        />
                    </TabsContent>
                    <TabsContent value="gear">
                        <GearTab
                            :definition="definition"
                            :gear-view="gearView"
                        />
                    </TabsContent>
                    <TabsContent value="passives">
                        <PassivesTab
                            :definition="definition"
                            :entity-for="entityFor"
                            :sprite-url="spriteUrl"
                            :tree-url="treeUrl"
                            :ascendancy-key="ascendancyKey"
                            :ascendancy-path-ids="ascendancyPathIds"
                        />
                    </TabsContent>
                    <TabsContent value="notes">
                        <NotesTab :guide-html="build.guide_html" />
                    </TabsContent>
                </div>

                <BuildSidebar
                    v-if="showSidebar"
                    :build-id="build.id"
                    :game-short-name="game.short_name"
                    :similar-builds="similarBuilds"
                />
            </div>
        </Tabs>

        <footer
            class="mt-12 border-t border-[var(--border-subtle)] pt-4 font-mono text-[12px] text-[var(--fg-3)]"
        >
            Saved {{ build.created_at }} · build id {{ build.id }} · published
            through the Build Your Build MCP server. Not affiliated with
            Grinding Gear Games.
        </footer>

        <!-- Floating entity hover card -->
        <Transition name="fade">
            <EntityCard
                v-if="hovered"
                :entity="hovered"
                :sprite-url="spriteUrl"
                :style="cardStyle"
            />
        </Transition>
    </div>
</template>

<style scoped>
:deep(.entity-ref) {
    cursor: help;
    text-decoration: underline dotted;
    text-decoration-color: color-mix(in srgb, currentColor 45%, transparent);
    text-underline-offset: 3px;
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.12s var(--ease-out, ease);
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
    .fade-enter-active,
    .fade-leave-active {
        transition: none;
    }
}
</style>
