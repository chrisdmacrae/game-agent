<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import SeoHead from '@/components/SeoHead.vue';
import { dashboard, login, ogImage } from '@/routes';

const page = usePage();

interface Meta {
    game: string;
    game_state: string;
    data_version: string;
    league: string | null;
    data_imported_at: string | null;
}

interface Tool {
    name: string;
    description: string;
}

interface ModelDoc {
    id: string;
    title: string;
    summary: string;
}

const props = defineProps<{
    meta: Meta | null;
    mcpUrl: string;
    tools: Tool[];
    models: ModelDoc[];
}>();

const copied = ref(false);

async function copyUrl() {
    await navigator.clipboard.writeText(props.mcpUrl);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}
</script>

<template>
    <SeoHead
        title="PoE2 Theorycrafter — an MCP toolkit for Path of Exile 2 builds"
        description="An MCP server that connects Claude or ChatGPT to datamined Path of Exile 2 game data — every gem, unique, affix, and passive of the current patch — plus curated game models and a build validator."
        :og-image="ogImage.url()"
    />
    <div class="min-h-screen bg-zinc-950 text-zinc-100">
        <div class="mx-auto max-w-4xl px-6 py-16">
            <!-- Hero -->
            <header class="mb-16">
                <div class="mb-8 flex items-center justify-end">
                    <Link
                        v-if="page.props.auth.user"
                        :href="dashboard()"
                        class="text-sm text-zinc-400 transition hover:text-white"
                    >
                        Your builds →
                    </Link>
                    <Link
                        v-else
                        :href="login()"
                        class="text-sm text-zinc-400 transition hover:text-white"
                    >
                        Sign in
                    </Link>
                </div>
                <p
                    class="mb-3 text-sm font-medium tracking-widest text-amber-500 uppercase"
                >
                    PoE2 Theorycrafter
                </p>
                <h1 class="mb-4 text-4xl font-bold tracking-tight text-white">
                    Give your AI real Path of Exile 2 knowledge.
                </h1>
                <p class="max-w-2xl text-lg leading-relaxed text-zinc-400">
                    An MCP server that connects Claude or ChatGPT to datamined
                    game data — every gem, unique, affix, and passive of the
                    current patch — plus curated game models and a build
                    validator. Theorycraft builds with an AI that queries facts
                    instead of hallucinating them.
                </p>
            </header>

            <!-- Connect -->
            <section class="mb-14">
                <h2 class="mb-4 text-xl font-semibold text-white">Connect</h2>
                <div
                    class="mb-6 flex items-center gap-3 rounded-lg border border-zinc-800 bg-zinc-900 p-4"
                >
                    <code
                        class="flex-1 truncate font-mono text-sm text-amber-400"
                        >{{ mcpUrl }}</code
                    >
                    <button
                        class="rounded-md bg-zinc-800 px-3 py-1.5 text-sm text-zinc-200 transition hover:bg-zinc-700"
                        @click="copyUrl"
                    >
                        {{ copied ? 'Copied!' : 'Copy' }}
                    </button>
                </div>
                <div class="grid gap-6 md:grid-cols-2">
                    <div
                        class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-5"
                    >
                        <h3 class="mb-2 font-semibold text-white">Claude</h3>
                        <ol
                            class="list-inside list-decimal space-y-1 text-sm leading-relaxed text-zinc-400"
                        >
                            <li>Open Settings → Connectors</li>
                            <li>Choose "Add custom connector"</li>
                            <li>Paste the server URL above</li>
                            <li>Ask: "Craft me a league starter build"</li>
                        </ol>
                    </div>
                    <div
                        class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-5"
                    >
                        <h3 class="mb-2 font-semibold text-white">ChatGPT</h3>
                        <ol
                            class="list-inside list-decimal space-y-1 text-sm leading-relaxed text-zinc-400"
                        >
                            <li>Open Settings → Connectors (developer mode)</li>
                            <li>Add a new MCP connector</li>
                            <li>Paste the server URL above</li>
                            <li>Enable it in a new conversation</li>
                        </ol>
                    </div>
                </div>
            </section>

            <!-- Status -->
            <section class="mb-14">
                <h2 class="mb-4 text-xl font-semibold text-white">
                    Data status
                </h2>
                <div v-if="meta" class="grid gap-4 sm:grid-cols-3">
                    <div
                        class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-4"
                    >
                        <p class="text-xs text-zinc-500 uppercase">
                            Game version
                        </p>
                        <p class="mt-1 font-mono text-lg text-white">
                            {{ meta.data_version }}
                        </p>
                    </div>
                    <div
                        class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-4"
                    >
                        <p class="text-xs text-zinc-500 uppercase">League</p>
                        <p class="mt-1 text-lg text-white">
                            {{ meta.league ?? '—' }}
                        </p>
                    </div>
                    <div
                        class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-4"
                    >
                        <p class="text-xs text-zinc-500 uppercase">
                            Data imported
                        </p>
                        <p class="mt-1 text-lg text-white">
                            {{
                                meta.data_imported_at
                                    ? new Date(
                                          meta.data_imported_at,
                                      ).toLocaleDateString()
                                    : '—'
                            }}
                        </p>
                    </div>
                </div>
                <p v-else class="text-sm text-zinc-500">
                    No game data imported yet.
                </p>
            </section>

            <!-- Tools -->
            <section class="mb-14">
                <h2 class="mb-4 text-xl font-semibold text-white">
                    Tools
                    <span class="text-sm font-normal text-zinc-500"
                        >({{ tools.length }})</span
                    >
                </h2>
                <div
                    class="divide-y divide-zinc-800 rounded-lg border border-zinc-800"
                >
                    <div v-for="tool in tools" :key="tool.name" class="p-4">
                        <code class="font-mono text-sm text-amber-400">{{
                            tool.name
                        }}</code>
                        <p class="mt-1 text-sm leading-relaxed text-zinc-400">
                            {{ tool.description }}
                        </p>
                    </div>
                </div>
            </section>

            <!-- Game models -->
            <section class="mb-14">
                <h2 class="mb-4 text-xl font-semibold text-white">
                    Game models
                    <span class="text-sm font-normal text-zinc-500"
                        >— how the AI learns the rules</span
                    >
                </h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div
                        v-for="doc in models"
                        :key="doc.id"
                        class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-4"
                    >
                        <h3 class="text-sm font-semibold text-white">
                            {{ doc.title }}
                        </h3>
                        <p class="mt-1 text-xs leading-relaxed text-zinc-500">
                            {{ doc.summary }}
                        </p>
                    </div>
                </div>
            </section>

            <footer
                class="border-t border-zinc-800 pt-6 text-xs leading-relaxed text-zinc-600"
            >
                This site is not affiliated with, funded by, or endorsed by
                Grinding Gear Games. Path of Exile 2 game data is the property
                of Grinding Gear Games. Data via the repoe-fork project, the
                official GGG passive tree export, the Path of Building
                community, and poe.ninja.
            </footer>
        </div>
    </div>
</template>
