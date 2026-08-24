<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PassiveTreeView from '@/components/PassiveTreeView.vue';
import { computed, ref } from 'vue';
import { home } from '@/routes';

interface SkillSetup {
    gem: string;
    supports?: string[];
}

interface GearItem {
    slot: string;
    rarity: string;
    name?: string;
    base?: string;
    mods?: string[];
    instill?: { notable: string; emotions?: string[] };
}

interface JewelItem {
    name: string;
    rarity: string;
    socket_node_id?: number;
    mods?: string[];
}

interface BuildDefinition {
    class?: string;
    ascendancy?: string;
    level?: number;
    skills: SkillSetup[];
    spirit_available?: number;
    passives?: {
        keystones?: string[];
        notables?: string[];
        points_used?: number;
        node_ids?: number[];
        ascendancy_nodes?: string[];
        granted_nodes?: { node_id: number; source: string; detail?: string }[];
    };
    gear?: GearItem[];
    jewels?: JewelItem[];
    resistances?: Record<string, number>;
    content_tier?: string;
}

interface Validation {
    valid: boolean;
    violations: string[];
    warnings: string[];
    suggestions: string[];
}

interface Entity {
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
}

const props = defineProps<{
    build: {
        id: string;
        name: string;
        summary: string | null;
        definition: BuildDefinition;
        validation: Validation | Record<string, never>;
        game_version: string | null;
        created_at: string;
        guide_html: string | null;
    };
    entities: Record<string, Entity>;
    spriteUrl: string;
    treeUrl: string | null;
    ascendancyKey: string | null;
}>();

const def = props.build.definition;
const validation = props.build.validation as Validation;
const identity = [def.class, def.ascendancy].filter(Boolean).join(' · ');

// Case-insensitive entity lookup (guide mentions may differ in casing).
const entityIndex = computed(() => {
    const index: Record<string, Entity> = {};
    for (const entity of Object.values(props.entities)) {
        index[entity.name.toLowerCase()] = entity;
    }
    return index;
});

function entityFor(name: string): Entity | null {
    return entityIndex.value[name.toLowerCase()] ?? null;
}

// One floating hover card, driven by delegation so it also works for
// data-entity spans inside the server-rendered guide HTML.
const hovered = ref<Entity | null>(null);
const cardStyle = ref<Record<string, string>>({});
let hideTimer: ReturnType<typeof setTimeout> | null = null;

function showCardFor(target: HTMLElement) {
    const name = target.dataset.entity;
    const entity = name ? entityFor(name) : null;
    if (!entity) return;

    if (hideTimer) clearTimeout(hideTimer);

    const rect = target.getBoundingClientRect();
    const cardWidth = 340;
    const left = Math.min(Math.max(8, rect.left), window.innerWidth - cardWidth - 8);
    const below = rect.bottom + 8;
    const flip = below > window.innerHeight - 260;

    cardStyle.value = {
        left: `${left}px`,
        ...(flip ? { bottom: `${window.innerHeight - rect.top + 8}px` } : { top: `${below}px` }),
    };
    hovered.value = entity;
}

function onOver(event: MouseEvent) {
    const target = (event.target as HTMLElement).closest<HTMLElement>('[data-entity]');
    if (target) showCardFor(target);
}

function onOut(event: MouseEvent) {
    const target = (event.target as HTMLElement).closest<HTMLElement>('[data-entity]');
    if (!target) return;
    hideTimer = setTimeout(() => (hovered.value = null), 120);
}

const slotLabels: Record<string, string> = {
    helmet: 'Helmet',
    body: 'Body Armour',
    gloves: 'Gloves',
    boots: 'Boots',
    amulet: 'Amulet',
    ring1: 'Ring',
    ring2: 'Ring',
    belt: 'Belt',
    weapon1: 'Weapon',
    offhand1: 'Off-hand',
    weapon2: 'Weapon (Set II)',
    offhand2: 'Off-hand (Set II)',
};

const gemColors: Record<string, string> = {
    r: 'bg-red-500/20 text-red-400 border-red-500/40',
    g: 'bg-green-500/20 text-green-400 border-green-500/40',
    b: 'bg-blue-500/20 text-blue-400 border-blue-500/40',
    w: 'bg-zinc-500/20 text-zinc-300 border-zinc-500/40',
};

function gemBadgeClass(name: string): string {
    const color = entityFor(name)?.color ?? 'w';
    return gemColors[color] ?? gemColors.w;
}

// Icons render at the sheet's native size — scaling would require knowing the
// full sheet dimensions to keep background-size and -position in step.
function spriteStyle(entity: Entity): Record<string, string> | null {
    if (!entity.sprite) return null;
    const { x, y, w, h } = entity.sprite;
    return {
        width: `${w}px`,
        height: `${h}px`,
        backgroundImage: `url(${props.spriteUrl})`,
        backgroundPosition: `-${x}px -${y}px`,
        backgroundRepeat: 'no-repeat',
    };
}
</script>

<template>
    <Head :title="build.name" />
    <div class="min-h-screen bg-zinc-950 text-zinc-100" @mouseover="onOver" @mouseout="onOut">
        <div class="mx-auto max-w-3xl px-6 py-12">
            <Link :href="home()" class="text-sm text-zinc-500 hover:text-zinc-300">← PoE2 Theorycrafter</Link>

            <header class="mt-6 mb-10">
                <div class="mb-3 flex flex-wrap items-center gap-2 text-sm">
                    <span v-if="identity" class="rounded-full bg-amber-500/10 px-3 py-1 font-medium text-amber-400">{{ identity }}</span>
                    <span v-if="def.level" class="rounded-full bg-zinc-800 px-3 py-1 text-zinc-400">Level {{ def.level }}</span>
                    <span v-if="def.content_tier" class="rounded-full bg-zinc-800 px-3 py-1 text-zinc-400 capitalize">{{ def.content_tier.replace('_', ' ') }}</span>
                    <span v-if="build.game_version" class="rounded-full bg-zinc-800 px-3 py-1 text-zinc-400">Patch {{ build.game_version }}</span>
                </div>
                <h1 class="text-3xl font-bold tracking-tight text-white">{{ build.name }}</h1>
                <p v-if="build.summary" class="mt-2 text-lg text-zinc-400">{{ build.summary }}</p>
            </header>

            <!-- Validation banner -->
            <div
                v-if="validation.violations?.length"
                class="mb-8 rounded-lg border border-red-900 bg-red-950/40 p-4 text-sm text-red-300"
            >
                <p class="mb-1 font-semibold">This build has unresolved rule violations:</p>
                <ul class="list-inside list-disc space-y-1">
                    <li v-for="violation in validation.violations" :key="violation">{{ violation }}</li>
                </ul>
            </div>
            <div
                v-else-if="validation.valid"
                class="mb-8 rounded-lg border border-emerald-900 bg-emerald-950/40 px-4 py-3 text-sm text-emerald-300"
            >
                ✓ Passes all game-rule checks (support limits, spirit budget, passive existence).
            </div>

            <!-- Skills -->
            <section class="mb-10">
                <h2 class="mb-3 text-lg font-semibold text-white">Skill setups</h2>
                <div class="divide-y divide-zinc-800 rounded-lg border border-zinc-800">
                    <div v-for="setup in def.skills" :key="setup.gem" class="flex flex-wrap items-center gap-x-3 gap-y-2 p-4">
                        <img
                            v-if="entityFor(setup.gem)?.icon"
                            :src="entityFor(setup.gem)!.icon!"
                            :alt="setup.gem"
                            class="h-8 w-8 rounded-md border border-zinc-700 bg-zinc-900 object-contain"
                        />
                        <span
                            v-else
                            class="flex h-8 w-8 items-center justify-center rounded-md border font-bold"
                            :class="gemBadgeClass(setup.gem)"
                        >
                            {{ setup.gem.charAt(0) }}
                        </span>
                        <span class="entity-ref font-semibold text-amber-400" :data-entity="setup.gem">{{ setup.gem }}</span>
                        <span v-if="setup.supports?.length" class="flex flex-wrap items-center gap-1.5 text-sm text-zinc-400">
                            ←
                            <span
                                v-for="support in setup.supports"
                                :key="support"
                                class="entity-ref rounded bg-zinc-900 px-2 py-0.5"
                                :data-entity="support"
                            >{{ support }}</span>
                        </span>
                    </div>
                </div>
                <p v-if="def.spirit_available" class="mt-2 text-sm text-zinc-500">
                    Spirit available: {{ def.spirit_available }}
                </p>
            </section>

            <!-- Gear -->
            <section v-if="def.gear?.length || def.jewels?.length" class="mb-10">
                <h2 class="mb-3 text-lg font-semibold text-white">Gear</h2>
                <div class="divide-y divide-zinc-800 rounded-lg border border-zinc-800">
                    <div v-for="item in def.gear ?? []" :key="item.slot" class="flex flex-wrap items-baseline gap-x-3 gap-y-1 p-4">
                        <span class="w-32 shrink-0 text-sm text-zinc-500">{{ slotLabels[item.slot] ?? item.slot }}</span>
                        <div class="min-w-0 flex-1">
                            <p>
                                <span
                                    v-if="item.rarity === 'unique' && item.name"
                                    class="entity-ref font-semibold text-orange-300"
                                    :data-entity="item.name"
                                >{{ item.name }}</span>
                                <span v-else class="font-semibold" :class="item.rarity === 'rare' ? 'text-yellow-200' : 'text-zinc-200'">
                                    {{ item.name ?? (item.rarity.charAt(0).toUpperCase() + item.rarity.slice(1)) }}
                                </span>
                                <span v-if="item.base" class="ml-2 text-sm text-zinc-500">{{ item.base }}</span>
                            </p>
                            <p v-if="item.mods?.length" class="mt-0.5 text-sm text-sky-200/70">{{ item.mods.join(' · ') }}</p>
                            <p v-if="item.instill" class="mt-0.5 text-sm text-violet-300">
                                Instilled: <span class="entity-ref" :data-entity="item.instill.notable">{{ item.instill.notable }}</span>
                                <span v-if="item.instill.emotions?.length" class="text-zinc-500"> ({{ item.instill.emotions.join(' + ') }})</span>
                            </p>
                        </div>
                    </div>
                    <div v-for="jewel in def.jewels ?? []" :key="jewel.name" class="flex flex-wrap items-baseline gap-x-3 gap-y-1 p-4">
                        <span class="w-32 shrink-0 text-sm text-zinc-500">Jewel</span>
                        <div class="min-w-0 flex-1">
                            <p>
                                <span
                                    v-if="jewel.rarity === 'unique'"
                                    class="entity-ref font-semibold text-orange-300"
                                    :data-entity="jewel.name"
                                >{{ jewel.name }}</span>
                                <span v-else class="font-semibold text-yellow-200">{{ jewel.name }}</span>
                            </p>
                            <p v-if="jewel.mods?.length" class="mt-0.5 text-sm text-sky-200/70">{{ jewel.mods.join(' · ') }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Passives + defenses -->
            <section
                v-if="def.passives?.keystones?.length || def.passives?.notables?.length || def.resistances"
                class="mb-10 grid gap-6 sm:grid-cols-2"
            >
                <div v-if="def.passives?.keystones?.length || def.passives?.notables?.length">
                    <h2 class="mb-3 text-lg font-semibold text-white">Key passives</h2>
                    <ul class="space-y-2 text-sm">
                        <li
                            v-for="passive in [...(def.passives?.keystones ?? []), ...(def.passives?.notables ?? [])]"
                            :key="passive"
                            class="flex items-center gap-2"
                        >
                            <span
                                v-if="entityFor(passive)?.sprite"
                                class="inline-block shrink-0 rounded-sm"
                                :style="spriteStyle(entityFor(passive)!)!"
                            />
                            <span
                                class="entity-ref"
                                :class="entityFor(passive)?.passive_kind === 'keystone' ? 'text-amber-400' : 'text-zinc-300'"
                                :data-entity="passive"
                            >{{ passive }}</span>
                            <span v-if="entityFor(passive)?.passive_kind === 'keystone'" class="text-zinc-600">(keystone)</span>
                        </li>
                    </ul>
                </div>
                <div v-if="def.resistances">
                    <h2 class="mb-3 text-lg font-semibold text-white">Resistances</h2>
                    <ul class="space-y-1 text-sm text-zinc-300">
                        <li v-for="(value, element) in def.resistances" :key="element" class="capitalize">
                            {{ element }}: <span :class="value >= 75 ? 'text-emerald-400' : 'text-zinc-400'">{{ value }}%</span>
                        </li>
                    </ul>
                </div>
            </section>

            <!-- Passive tree -->
            <section
                v-if="treeUrl && (def.passives?.node_ids?.length || def.passives?.keystones?.length || def.passives?.notables?.length)"
                class="mb-10"
            >
                <h2 class="mb-3 text-lg font-semibold text-white">
                    Passive tree
                    <span class="text-sm font-normal text-zinc-500">
                        {{ def.passives?.node_ids?.length ? `— ${def.passives.node_ids.length} allocated nodes` : '— key nodes highlighted' }}
                    </span>
                </h2>
                <PassiveTreeView
                    :tree-url="treeUrl"
                    :sprite-url="spriteUrl"
                    :highlight-names="[...(def.passives?.keystones ?? []), ...(def.passives?.notables ?? [])]"
                    :ascendancy-nodes="def.passives?.ascendancy_nodes ?? []"
                    :node-ids="def.passives?.node_ids ?? []"
                    :granted-ids="(def.passives?.granted_nodes ?? []).map((g) => g.node_id)"
                    :class-name="def.class"
                    :ascendancy-key="ascendancyKey"
                    :ascendancy-name="def.ascendancy"
                />
            </section>

            <!-- Guide -->
            <section v-if="build.guide_html" class="mb-10">
                <h2 class="mb-3 text-lg font-semibold text-white">Guide</h2>
                <!-- eslint-disable-next-line vue/no-v-html — server-rendered markdown with HTML escaped -->
                <div class="guide-content leading-relaxed text-zinc-300" v-html="build.guide_html" />
            </section>

            <footer class="border-t border-zinc-800 pt-4 text-xs text-zinc-600">
                Saved {{ build.created_at }} · Build id {{ build.id }} · Generated with an AI agent connected to the
                PoE2 Theorycrafter MCP server. Not affiliated with Grinding Gear Games.
            </footer>
        </div>

        <!-- Floating entity hover card -->
        <Transition name="fade">
            <div
                v-if="hovered"
                class="pointer-events-none fixed z-50 w-[340px] rounded-lg border border-zinc-700 bg-zinc-900 p-4 shadow-xl shadow-black/50"
                :style="cardStyle"
            >
                <!-- Gem / support -->
                <template v-if="hovered.kind === 'gem' || hovered.kind === 'support'">
                    <div class="mb-1 flex items-center gap-2">
                        <img
                            v-if="hovered.icon"
                            :src="hovered.icon"
                            :alt="hovered.name"
                            class="h-8 w-8 rounded border border-zinc-700 object-contain"
                        />
                        <span
                            v-else
                            class="flex h-6 w-6 items-center justify-center rounded border text-xs font-bold"
                            :class="gemColors[hovered.color ?? 'w'] ?? gemColors.w"
                        >{{ hovered.name.charAt(0) }}</span>
                        <span class="font-semibold text-white">{{ hovered.name }}</span>
                        <span class="text-xs text-zinc-500 uppercase">{{ hovered.kind }}</span>
                    </div>
                    <p v-if="hovered.tags?.length" class="mb-2 text-xs text-zinc-500">{{ hovered.tags.join(' · ') }}</p>
                    <p v-if="hovered.description" class="mb-2 text-sm text-zinc-400">{{ hovered.description }}</p>
                    <p v-if="hovered.spirit_reservation" class="mb-2 text-sm text-sky-300">
                        Reserves {{ hovered.spirit_reservation }} Spirit
                    </p>
                    <ul v-if="hovered.stat_text?.length" class="space-y-0.5 text-sm text-sky-200/80">
                        <li v-for="line in hovered.stat_text" :key="line">{{ line }}</li>
                    </ul>
                </template>

                <!-- Passive -->
                <template v-else-if="hovered.kind === 'passive'">
                    <div class="mb-2 flex items-center gap-2">
                        <span v-if="hovered.sprite" class="inline-block shrink-0" :style="spriteStyle(hovered)!" />
                        <div>
                            <p class="font-semibold text-white">{{ hovered.name }}</p>
                            <p class="text-xs text-zinc-500 capitalize">{{ hovered.passive_kind }} passive</p>
                        </div>
                    </div>
                    <ul class="space-y-0.5 text-sm text-sky-200/80">
                        <li v-for="stat in hovered.stats" :key="stat" class="whitespace-pre-line">{{ stat }}</li>
                    </ul>
                </template>

                <!-- Unique -->
                <template v-else-if="hovered.kind === 'unique'">
                    <div class="mb-2 flex items-center gap-2">
                        <img
                            v-if="hovered.icon"
                            :src="hovered.icon"
                            :alt="hovered.name"
                            class="max-h-12 rounded border border-orange-500/30 bg-zinc-950 object-contain px-1"
                        />
                        <span
                            v-else
                            class="flex h-6 w-6 items-center justify-center rounded border border-orange-500/40 bg-orange-500/15 text-xs font-bold text-orange-400"
                        >U</span>
                        <div>
                            <p class="font-semibold text-orange-300">{{ hovered.name }}</p>
                            <p class="text-xs text-zinc-500">{{ hovered.base_name }} · {{ hovered.item_class }}</p>
                        </div>
                    </div>
                    <ul class="space-y-0.5 text-sm text-sky-200/80">
                        <li v-for="mod in hovered.mods" :key="mod">{{ mod }}</li>
                    </ul>
                </template>
            </div>
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
    transition: opacity 0.12s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.guide-content :deep(h1),
.guide-content :deep(h2),
.guide-content :deep(h3) {
    color: var(--color-white);
    font-weight: 600;
    margin: 1.5rem 0 0.5rem;
}

.guide-content :deep(h1) {
    font-size: 1.375rem;
}

.guide-content :deep(h2) {
    font-size: 1.125rem;
}

.guide-content :deep(p) {
    margin: 0.75rem 0;
}

.guide-content :deep(ul),
.guide-content :deep(ol) {
    margin: 0.75rem 0;
    padding-left: 1.5rem;
    list-style: disc;
}

.guide-content :deep(ol) {
    list-style: decimal;
}

.guide-content :deep(li) {
    margin: 0.25rem 0;
}

.guide-content :deep(strong) {
    color: var(--color-zinc-100);
}

.guide-content :deep(code) {
    background: var(--color-zinc-900);
    border-radius: 0.25rem;
    padding: 0.125rem 0.375rem;
    font-size: 0.875em;
    color: var(--color-amber-400);
}

.guide-content :deep(table) {
    width: 100%;
    margin: 1rem 0;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.guide-content :deep(th),
.guide-content :deep(td) {
    border: 1px solid var(--color-zinc-800);
    padding: 0.5rem 0.75rem;
    text-align: left;
}

.guide-content :deep(th) {
    color: var(--color-white);
}

.guide-content :deep(a) {
    color: var(--color-amber-400);
    text-decoration: underline;
}

.guide-content :deep(blockquote) {
    border-left: 3px solid var(--color-zinc-700);
    padding-left: 1rem;
    color: var(--color-zinc-400);
    margin: 0.75rem 0;
}
</style>
