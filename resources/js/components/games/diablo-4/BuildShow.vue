<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { TabsContent } from 'reka-ui';
import { computed, onMounted, ref, watch } from 'vue';
import Card from '@/components/byb/Card.vue';
import Icon from '@/components/byb/Icon.vue';
import Tabs from '@/components/byb/Tabs.vue';
import BuildHeader from '@/components/games/diablo-4/build/BuildHeader.vue';
import BuildSidebar from '@/components/games/diablo-4/build/BuildSidebar.vue';
import GearTab from '@/components/games/diablo-4/build/GearTab.vue';
import NotesTab from '@/components/games/diablo-4/build/NotesTab.vue';
import OverviewTab from '@/components/games/diablo-4/build/OverviewTab.vue';
import ParagonTab from '@/components/games/diablo-4/build/ParagonTab.vue';
import SkillsTab from '@/components/games/diablo-4/build/SkillsTab.vue';
import type {
    D4BuildShowProps,
    D4Validation,
} from '@/components/games/diablo-4/types';

/**
 * The Diablo IV build page. The action bar, paragon boards and the keyed gear
 * map are D4 vocabulary, so this whole renderer is game specific —
 * `pages/Builds/Show.vue` only picks it.
 */
const props = defineProps<D4BuildShowProps>();

const page = usePage();

const signedIn = computed(() => Boolean(page.props.auth?.user));

const definition = computed(() => props.build.definition);

const validation = computed(() => props.build.validation as D4Validation);

const TABS = [
    { value: 'overview', label: 'Overview', icon: 'gauge' as const },
    { value: 'skills', label: 'Skills', icon: 'swords' as const },
    { value: 'gear', label: 'Gear', icon: 'shield' as const },
    { value: 'paragon', label: 'Paragon', icon: 'layout-grid' as const },
    { value: 'notes', label: 'Notes', icon: 'book-open' as const },
];

const tab = ref('overview');

// The tab rides in the hash so a link can land on Gear or Paragon directly.
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
</script>

<template>
    <div class="py-8">
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
                Passes every game-rule check: action bar size, skill ranks,
                paragon board count and item limits.
            </div>
        </Card>

        <Tabs v-model="tab" :tabs="TABS" class="mt-8">
            <div class="mt-6 flex flex-col items-start gap-6 lg:flex-row">
                <div class="min-w-0 flex-1">
                    <TabsContent value="overview">
                        <OverviewTab :definition="definition" />
                    </TabsContent>
                    <TabsContent value="skills">
                        <SkillsTab :definition="definition" />
                    </TabsContent>
                    <TabsContent value="gear">
                        <GearTab :definition="definition" />
                    </TabsContent>
                    <TabsContent value="paragon">
                        <ParagonTab
                            :definition="definition"
                            :boards="paragonBoards"
                        />
                    </TabsContent>
                    <TabsContent value="notes">
                        <NotesTab :guide-html="build.guide_html" />
                    </TabsContent>
                </div>

                <BuildSidebar
                    v-if="showSidebar"
                    :build-id="build.id"
                    :game-slug="game.slug"
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
            Blizzard Entertainment.
        </footer>
    </div>
</template>
