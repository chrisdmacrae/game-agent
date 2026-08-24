<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { home } from '@/routes';

interface SkillSetup {
    gem: string;
    supports?: string[];
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
    };
    resistances?: Record<string, number>;
    content_tier?: string;
}

interface Validation {
    valid: boolean;
    violations: string[];
    warnings: string[];
    suggestions: string[];
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
}>();

const def = props.build.definition;
const validation = props.build.validation as Validation;
const identity = [def.class, def.ascendancy].filter(Boolean).join(' · ');
</script>

<template>
    <Head :title="build.name" />
    <div class="min-h-screen bg-zinc-950 text-zinc-100">
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
                    <div v-for="setup in def.skills" :key="setup.gem" class="flex flex-wrap items-baseline gap-2 p-4">
                        <span class="font-semibold text-amber-400">{{ setup.gem }}</span>
                        <span v-if="setup.supports?.length" class="text-sm text-zinc-400">
                            ← {{ setup.supports.join(', ') }}
                        </span>
                    </div>
                </div>
                <p v-if="def.spirit_available" class="mt-2 text-sm text-zinc-500">
                    Spirit available: {{ def.spirit_available }}
                </p>
            </section>

            <!-- Passives + defenses -->
            <section
                v-if="def.passives?.keystones?.length || def.passives?.notables?.length || def.resistances"
                class="mb-10 grid gap-6 sm:grid-cols-2"
            >
                <div v-if="def.passives?.keystones?.length || def.passives?.notables?.length">
                    <h2 class="mb-3 text-lg font-semibold text-white">Key passives</h2>
                    <ul class="space-y-1 text-sm">
                        <li v-for="keystone in def.passives?.keystones ?? []" :key="keystone" class="text-amber-400">
                            {{ keystone }} <span class="text-zinc-600">(keystone)</span>
                        </li>
                        <li v-for="notable in def.passives?.notables ?? []" :key="notable" class="text-zinc-300">
                            {{ notable }}
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
    </div>
</template>

<style scoped>
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
