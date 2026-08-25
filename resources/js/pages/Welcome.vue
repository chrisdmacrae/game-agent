<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Badge from '@/components/byb/Badge.vue';
import Button from '@/components/byb/Button.vue';
import Card from '@/components/byb/Card.vue';
import ConnectPanel from '@/components/byb/ConnectPanel.vue';
import { LABEL_CLASS } from '@/components/byb/controls';
import Dialog from '@/components/byb/Dialog.vue';
import Icon from '@/components/byb/Icon.vue';
import { bybIcons } from '@/components/byb/icons';
import type { IconName } from '@/components/byb/icons';
import StatBlock from '@/components/byb/StatBlock.vue';
import Tag from '@/components/byb/Tag.vue';
import Tooltip from '@/components/byb/Tooltip.vue';
import SeoHead from '@/components/SeoHead.vue';
import { cn } from '@/lib/utils';
import type { HubGame } from '@/types/hub';

/**
 * Root landing page (scope §3.1). Sections, in order: hero, how it works,
 * a sample assistant conversation beside the connect panel, what the server
 * exposes, the game grid, and the FAQ.
 */
type GameCard = HubGame & {
    is_live: boolean;
    url: string;
    builds: number | null;
    votes: number | null;
};

type Stats = {
    builds_published: number;
    games_live: number;
    patch: string | null;
    data_refreshed_at: string | null;
};

type Tool = {
    name: string;
    description: string;
};

type ModelDoc = {
    id: string;
    title: string;
    summary: string;
};

const props = defineProps<{
    mcpUrl: string;
    gameCards: GameCard[];
    stats: Stats;
    tools: Tool[];
    models: ModelDoc[];
}>();

const connectOpen = ref(false);

const numberFormat = new Intl.NumberFormat('en-US');

/** Written-out numerals for the headings; anything larger falls back to digits. */
const NUMERALS = [
    'No',
    'One',
    'Two',
    'Three',
    'Four',
    'Five',
    'Six',
    'Seven',
    'Eight',
    'Nine',
];

function numeral(count: number): string {
    return NUMERALS[count] ?? String(count);
}

const liveGame = computed<GameCard | undefined>(() =>
    props.gameCards.find((game) => game.is_live),
);

const queuedCount = computed(
    () => props.gameCards.filter((game) => !game.is_live).length,
);

const gamesHeading = computed(() => {
    const live = props.stats.games_live;

    return `${numeral(live)} game${live === 1 ? '' : 's'} live, ${numeral(
        queuedCount.value,
    ).toLowerCase()} in the queue.`;
});

const refreshedOn = computed(() =>
    props.stats.data_refreshed_at
        ? props.stats.data_refreshed_at.slice(0, 10)
        : '—',
);

function gameIcon(name: string): IconName {
    return name in bybIcons ? (name as IconName) : 'sword';
}

function gameAccent(game: GameCard): string {
    return `var(--${game.accent})`;
}

function gameMeta(game: GameCard): string {
    if (game.is_live) {
        const patch = props.stats.patch ? `${props.stats.patch} · ` : '';

        return `${patch}${numberFormat.format(game.builds ?? 0)} builds`;
    }

    return `${numberFormat.format(game.votes ?? 0)} votes`;
}

const HOW_IT_WORKS = [
    {
        title: 'Add the server',
        body: 'Paste the server URL into Claude or ChatGPT settings.',
    },
    {
        title: 'Approve the tools',
        body: 'Confirm the connector and the build tools appear.',
    },
    {
        title: 'Ask, then publish',
        body: 'Ask for a build, then publish it to this hub.',
    },
];

const CONVERSATION = [
    {
        role: 'You',
        text: 'Cold witch that can clear tier 15 maps for under 15 divine. I hate builds that die to one phys hit.',
        meta: null,
    },
    {
        role: 'Assistant',
        text: 'Cold Snap Infernalist fits. It freeze-locks packs and detonates them, and it holds 18.9k EHP at level 92 — enough for tier 15 without buying anything exotic.',
        meta: '4.1M dps · 18.9k ehp · 12 div · 0.5.2',
    },
    {
        role: 'You',
        text: 'Publish it under my handle.',
        meta: null,
    },
    {
        role: 'Assistant',
        text: 'Published as a draft. Check the numbers, set it public, and it lands on the PoE 2 hub.',
        meta: 'byb://poe2/build/1 · draft',
    },
];

const FAQS = [
    {
        q: 'Do I need an account to read builds?',
        a: 'No. Every published build is public. You sign in only to publish or edit your own.',
    },
    {
        q: 'Where do the numbers come from?',
        a: 'The publisher simulates them and the server records what was submitted. We do not re-simulate, and we show the patch each build was tested on.',
    },
    {
        q: 'Which clients work?',
        a: 'Anything that speaks MCP. Claude and ChatGPT are the two we test against every release.',
    },
    {
        q: 'Is there a URL per game?',
        a: 'Yes. Each game has its own endpoint — one more per game as they go live.',
    },
    {
        q: 'What happens on a new patch?',
        a: 'Builds keep their tested patch string and get an untested flag until the author updates them.',
    },
];

const sectionClass = 'pt-[72px]';

const headingClass =
    'mt-3 mb-8 font-display text-[28px] leading-[1.14] font-bold tracking-[-0.02em] text-[var(--fg-1)]';
</script>

<template>
    <!-- Title, description and card come from HomeController's PageMeta. -->
    <SeoHead />

    <div class="pb-16">
        <!-- Hero -->
        <section
            class="-mx-[var(--layout-gutter)] border-b border-[var(--border-subtle)] bg-[image:var(--texture-grid)] [background-size:var(--texture-grid-size)] px-[var(--layout-gutter)] pt-20 pb-[72px]"
        >
            <p :class="cn(LABEL_CLASS, 'text-[var(--teal-400)]')">
                MCP server · Claude &amp; ChatGPT
            </p>

            <h1
                class="mt-5 max-w-[920px] font-display text-[40px] leading-[0.98] font-extrabold tracking-[-0.02em] text-pretty text-[var(--fg-1)] md:text-[64px] md:leading-[0.94]"
            >
                Theorycraft with your assistant, publish for everyone else.
            </h1>

            <p
                class="mt-5 max-w-[600px] text-[17px] leading-[1.55] text-pretty text-[var(--fg-2)]"
            >
                Connect the MCP server, describe what you want to play, and get
                a build back with real numbers. Browsing is free and needs no
                account — you sign in when you want to publish.
            </p>

            <div class="mt-8 flex flex-wrap items-center gap-3">
                <Button
                    size="lg"
                    variant="primary"
                    icon="plug-zap"
                    @click="connectOpen = true"
                >
                    Connect the server
                </Button>
                <!-- `icon`/`iconRight` render outside the child under as-child, so the glyph goes in the Link. -->
                <Button v-if="liveGame" size="lg" variant="ghost" as-child>
                    <Link :href="liveGame.url">
                        Browse {{ liveGame.short_name }} builds
                        <Icon name="chevron-right" :size="16" />
                    </Link>
                </Button>
            </div>

            <div class="mt-[72px] flex flex-wrap gap-8">
                <StatBlock
                    label="Builds published"
                    :value="numberFormat.format(stats.builds_published)"
                />
                <StatBlock
                    label="Games live"
                    :value="stats.games_live"
                    :unit="`/ ${gameCards.length}`"
                />
                <StatBlock label="PoE 2 patch" :value="stats.patch ?? '—'" />
                <StatBlock label="Data refresh" :value="refreshedOn" />
            </div>
        </section>

        <!-- How it works -->
        <section :class="sectionClass">
            <p :class="LABEL_CLASS">How it works</p>
            <h2 :class="headingClass">
                Three steps, then it is just conversation.
            </h2>

            <div class="grid gap-4 md:grid-cols-3">
                <Card
                    v-for="(step, index) in HOW_IT_WORKS"
                    :key="step.title"
                    padding="var(--sp-7)"
                >
                    <span
                        class="inline-flex size-6 items-center justify-center rounded-[var(--radius-xs)] bg-[var(--surface-accent-soft)] font-mono text-[12px] text-[var(--teal-400)]"
                    >
                        {{ index + 1 }}
                    </span>
                    <p
                        class="mt-4 mb-2 text-[18px] leading-[1.28] font-semibold text-[var(--fg-1)]"
                    >
                        {{ step.title }}
                    </p>
                    <p class="text-[13px] text-pretty text-[var(--fg-2)]">
                        {{ step.body }}
                    </p>
                </Card>
            </div>
        </section>

        <!-- Sample assistant conversation -->
        <section :class="sectionClass">
            <div class="grid items-start gap-10 lg:grid-cols-[1fr_380px]">
                <div>
                    <p :class="LABEL_CLASS">In your assistant</p>
                    <h2 :class="headingClass">
                        Ask in plain language, get numbers back.
                    </h2>

                    <div class="flex flex-col gap-3">
                        <div
                            v-for="(message, index) in CONVERSATION"
                            :key="index"
                            :class="
                                cn(
                                    'rounded-[var(--radius-md)] border p-4',
                                    message.role === 'You'
                                        ? 'max-w-[80%] self-start border-[var(--border-hairline)] bg-[var(--ink-750)]'
                                        : 'max-w-[88%] self-end border-[var(--border-subtle)] bg-[var(--surface-card)]',
                                )
                            "
                        >
                            <p
                                :class="
                                    cn(
                                        LABEL_CLASS,
                                        'mb-2',
                                        message.role === 'Assistant' &&
                                            'text-[var(--teal-400)]',
                                    )
                                "
                            >
                                {{ message.role }}
                            </p>
                            <p
                                class="text-[15px] leading-[1.6] text-pretty text-[var(--fg-1)]"
                            >
                                {{ message.text }}
                            </p>
                            <p
                                v-if="message.meta"
                                class="mt-3 border-t border-[var(--border-hairline)] pt-3 font-mono text-[12px] text-[var(--fg-3)]"
                            >
                                {{ message.meta }}
                            </p>
                        </div>
                    </div>
                </div>

                <Card padding="var(--sp-7)">
                    <p :class="cn(LABEL_CLASS, 'mb-4')">Connect it</p>
                    <ConnectPanel
                        :mcp-url="mcpUrl"
                        filename="poe2 server url"
                    />
                </Card>
            </div>
        </section>

        <!-- What the server exposes -->
        <section :class="sectionClass">
            <p :class="LABEL_CLASS">What the server exposes</p>
            <h2 :class="headingClass">
                {{ tools.length }} tools, one endpoint per game.
            </h2>

            <div class="flex flex-wrap gap-2">
                <Tooltip
                    v-for="tool in tools"
                    :key="tool.name"
                    :text="tool.description"
                >
                    <Tag>{{ tool.name }}</Tag>
                </Tooltip>
            </div>

            <p
                v-if="models.length"
                class="mt-6 text-[13px] leading-[1.5] text-[var(--fg-3)]"
            >
                It also serves
                <span class="font-mono">{{ models.length }}</span> game models —
                the rules of the game written down, so the assistant reasons
                from them instead of guessing.
            </p>
        </section>

        <!-- Game grid -->
        <section :class="sectionClass">
            <p :class="LABEL_CLASS">Games</p>
            <h2 :class="headingClass">{{ gamesHeading }}</h2>

            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="game in gameCards"
                    :key="game.slug"
                    :href="game.url"
                    class="block no-underline hover:no-underline"
                >
                    <Card
                        interactive
                        padding="var(--sp-7)"
                        :accent-edge="gameAccent(game)"
                        class="h-full"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                class="inline-flex size-9 shrink-0 items-center justify-center rounded-[var(--radius-sm)] border border-[var(--border-subtle)] bg-[var(--ink-750)]"
                                :style="{ color: gameAccent(game) }"
                            >
                                <Icon :name="gameIcon(game.icon)" :size="20" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate text-[18px] leading-[1.28] font-semibold text-[var(--fg-1)]"
                                >
                                    {{ game.name }}
                                </p>
                                <p
                                    class="font-mono text-[12px] text-[var(--fg-3)]"
                                >
                                    {{ gameMeta(game) }}
                                </p>
                            </div>
                            <Badge :tone="game.is_live ? 'accent' : 'neutral'">
                                {{ game.is_live ? 'Live' : 'Queued' }}
                            </Badge>
                        </div>
                        <p
                            v-if="game.description"
                            class="mt-4 text-[13px] text-pretty text-[var(--fg-2)]"
                        >
                            {{ game.description }}
                        </p>
                    </Card>
                </Link>
            </div>
        </section>

        <!-- FAQ -->
        <section :class="sectionClass">
            <p :class="LABEL_CLASS">Questions</p>
            <h2 :class="headingClass">Before you connect it.</h2>

            <div class="grid max-w-[1000px] gap-x-8 gap-y-4 md:grid-cols-2">
                <div
                    v-for="faq in FAQS"
                    :key="faq.q"
                    class="border-t border-[var(--border-subtle)] pt-4"
                >
                    <p
                        class="mb-1.5 text-[15px] leading-[1.5] font-semibold text-[var(--fg-1)]"
                    >
                        {{ faq.q }}
                    </p>
                    <p class="text-[13px] text-pretty text-[var(--fg-2)]">
                        {{ faq.a }}
                    </p>
                </div>
            </div>
        </section>

        <!-- Disclaimers -->
        <section
            class="mt-[72px] border-t border-[var(--border-subtle)] pt-6 text-[13px] leading-[1.5] text-[var(--fg-3)]"
        >
            <p class="max-w-[720px]">
                Build data is community-published. Numbers are simulated by the
                publisher, not by us.
            </p>
            <p class="mt-2 max-w-[720px]">
                This site is not affiliated with, funded by, or endorsed by
                Grinding Gear Games. Path of Exile 2 game data is the property
                of Grinding Gear Games. Data via the repoe-fork project, the
                official GGG passive tree export, the Path of Building
                community, and poe.ninja.
            </p>
        </section>

        <Dialog
            v-model:open="connectOpen"
            eyebrow="MCP server"
            title="Connect Build Your Build"
            description="Add it in your client settings. Pick the client you use."
            :width="560"
        >
            <ConnectPanel :mcp-url="mcpUrl" filename="poe2 server url" />
        </Dialog>
    </div>
</template>
