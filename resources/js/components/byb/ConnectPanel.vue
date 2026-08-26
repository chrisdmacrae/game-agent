<script setup lang="ts">
import { computed, ref } from 'vue';
import type { HTMLAttributes } from 'vue';
import CodeBlock from '@/components/byb/CodeBlock.vue';
import { LABEL_CLASS } from '@/components/byb/controls';
import Tabs from '@/components/byb/Tabs.vue';
import { cn } from '@/lib/utils';
import type { ConnectGame } from '@/types/hub';

type Props = {
    /**
     * The hosted MCP endpoint, e.g. https://buildyourbuild.com/mcp/poe2.
     * Used as-is when `games` is empty (game-scoped pages), and as the
     * fallback when it is not.
     */
    mcpUrl?: string;
    /**
     * Live games to pick an endpoint from. With more than one, the panel
     * shows a game selector and the code block follows the selection.
     */
    games?: ConnectGame[];
    /** Caption on the code block; the game selector overrides it. */
    filename?: string;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    mcpUrl: '',
    games: () => [],
    filename: 'server url',
    class: undefined,
});

type ConnectStep = {
    title: string;
    body: string;
};

const CONNECT_STEPS: Record<'claude' | 'chatgpt', ConnectStep[]> = {
    claude: [
        {
            title: 'Open Settings',
            body: 'Click your name in the bottom left, then Settings.',
        },
        {
            title: 'Go to Connectors',
            body: 'Pick Connectors in the sidebar, then Add custom connector.',
        },
        {
            title: 'Paste the server URL',
            body: 'Name it Build Your Build and paste the URL below.',
        },
        {
            title: 'Click Connect',
            body: 'Approve the tools when Claude asks. That is it.',
        },
    ],
    chatgpt: [
        {
            title: 'Open Settings',
            body: 'Click your avatar, then Settings.',
        },
        {
            title: 'Turn on developer mode',
            body: 'Connectors, then Advanced, then Developer mode.',
        },
        {
            title: 'Add the server',
            body: 'Connectors, then Create, and paste the URL below.',
        },
        {
            title: 'Click Connect',
            body: 'Approve the tools when ChatGPT asks. That is it.',
        },
    ],
};

const client = ref<'claude' | 'chatgpt'>('claude');

const steps = computed(() => CONNECT_STEPS[client.value]);

const selectedGame = ref(props.games[0]?.slug ?? '');

const activeGame = computed(() =>
    props.games.find((game) => game.slug === selectedGame.value),
);

const activeUrl = computed(() => activeGame.value?.mcpUrl ?? props.mcpUrl);

const activeFilename = computed(() =>
    activeGame.value ? `${activeGame.value.slug} server url` : props.filename,
);

const gameTabs = computed(() =>
    props.games.map((game) => ({ value: game.slug, label: game.label })),
);
</script>

<template>
    <div :class="cn('flex flex-col gap-6', props.class)">
        <div v-if="gameTabs.length > 1" class="flex flex-col gap-2">
            <p :class="LABEL_CLASS">Game</p>
            <Tabs
                v-model="selectedGame"
                variant="segmented"
                size="sm"
                :tabs="gameTabs"
            />
        </div>

        <div class="flex flex-col gap-2">
            <p v-if="gameTabs.length > 1" :class="LABEL_CLASS">Client</p>
            <Tabs
                v-model="client"
                variant="segmented"
                size="sm"
                :tabs="[
                    { value: 'claude', label: 'Claude' },
                    { value: 'chatgpt', label: 'ChatGPT' },
                ]"
            />
        </div>

        <ol class="flex list-none flex-col gap-4 p-0">
            <li
                v-for="(step, index) in steps"
                :key="step.title"
                class="flex gap-3"
            >
                <span
                    class="inline-flex size-5 shrink-0 items-center justify-center rounded-[var(--radius-xs)] bg-[var(--surface-accent-soft)] font-mono text-[11px] text-[var(--teal-400)]"
                >
                    {{ index + 1 }}
                </span>
                <div>
                    <p
                        class="text-[15px] leading-[1.5] font-semibold text-[var(--fg-1)]"
                    >
                        {{ step.title }}
                    </p>
                    <p class="mt-0.5 text-[13px] text-[var(--fg-2)]">
                        {{ step.body }}
                    </p>
                </div>
            </li>
        </ol>

        <CodeBlock :code="activeUrl" :filename="activeFilename" />
    </div>
</template>
