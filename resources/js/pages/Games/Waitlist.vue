<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Button from '@/components/byb/Button.vue';
import Card from '@/components/byb/Card.vue';
import { LABEL_CLASS } from '@/components/byb/controls';
import Icon from '@/components/byb/Icon.vue';
import Input from '@/components/byb/Input.vue';
import StatBlock from '@/components/byb/StatBlock.vue';
import SeoHead from '@/components/SeoHead.vue';
import { cn } from '@/lib/utils';
import { show as gameShow, vote } from '@/routes/games';
import type { HubGame, QueuedGame } from '@/types/hub';

/**
 * The waitlist for a queued game (scope §3.5). One email, one vote per game;
 * voting works signed out.
 */
const props = defineProps<{
    game: HubGame;
    votes: number;
    queuePosition: number | null;
    patch: string | null;
    queue: QueuedGame[];
}>();

const form = useForm({ email: '' });

/**
 * The server answers both a fresh vote and a repeat with a redirect plus a
 * flash toast, so the vote count before and after is what tells the two apart
 * for the inline confirmation.
 */
const result = ref<'counted' | 'already' | null>(null);

function castVote(): void {
    const before = props.votes;

    form.post(vote.url(props.game.slug), {
        preserveScroll: true,
        onSuccess: () => {
            result.value = props.votes > before ? 'counted' : 'already';
            form.reset('email');
        },
    });
}

/** Bars are relative to the leader, never to the total. */
const topVotes = computed(() =>
    Math.max(...props.queue.map((entry) => entry.votes), 1),
);

function barWidth(votes: number): string {
    return `${Math.max(Math.round((votes / topVotes.value) * 100), 2)}%`;
}
</script>

<template>
    <div>
        <SeoHead
            :title="`${game.name} — not live yet`"
            :description="`${game.name} is not wired up yet. Vote to move it up the queue.`"
        />

        <div class="max-w-[760px] py-10">
            <p :class="cn(LABEL_CLASS, 'text-[var(--mag-400)]')">
                Not live yet
            </p>

            <h1
                class="mt-4 font-display text-[48px] leading-[0.96] font-extrabold tracking-[-0.02em] text-[var(--fg-1)]"
            >
                {{ game.name }}
            </h1>

            <p class="mt-4 text-[17px] leading-[1.55] text-[var(--fg-2)]">
                The MCP server does not read
                {{ game.short_name ?? game.name }} data yet. Vote and we will
                tell you the day it does — the games with the most votes get
                built first.
            </p>

            <div class="mt-8 flex flex-wrap gap-8">
                <StatBlock label="Votes" :value="votes" icon="flame" />
                <StatBlock
                    label="Queue position"
                    :value="queuePosition ?? 'unranked'"
                />
                <StatBlock
                    label="Latest patch"
                    :value="patch ?? 'no patch data'"
                />
            </div>

            <Card padding="var(--sp-7)" class="mt-8">
                <p :class="LABEL_CLASS">Vote for this game</p>

                <form
                    class="mt-5 flex flex-wrap items-start gap-4"
                    @submit.prevent="castVote"
                >
                    <Input
                        v-model="form.email"
                        type="email"
                        name="email"
                        required
                        autocomplete="email"
                        placeholder="you@example.com"
                        aria-label="Email"
                        :error="form.errors.email"
                        class="w-[280px]"
                    />
                    <Button
                        type="submit"
                        variant="accent"
                        icon="plus"
                        :disabled="form.processing"
                    >
                        Cast vote
                    </Button>
                </form>

                <p
                    v-if="result"
                    class="mt-5 flex items-center gap-2 text-[13px] text-[var(--teal-400)]"
                >
                    <Icon
                        :name="result === 'counted' ? 'circle-check' : 'info'"
                        :size="13"
                    />
                    <template v-if="result === 'counted'">
                        Vote counted. We will email you when
                        {{ game.name }} goes live.
                    </template>
                    <template v-else>
                        That address already voted for {{ game.name }}. One
                        email, one vote.
                    </template>
                </p>

                <p v-else class="mt-5 text-[13px] text-[var(--fg-3)]">
                    One email, one vote per game. We use it for the launch
                    notice and nothing else.
                </p>
            </Card>

            <section class="mt-10">
                <p :class="LABEL_CLASS">Queue</p>

                <ul class="mt-5 flex list-none flex-col p-0">
                    <li
                        v-for="entry in queue"
                        :key="entry.slug"
                        class="flex items-center gap-5 border-t border-[var(--border-hairline)] py-4"
                    >
                        <span
                            class="w-6 shrink-0 font-mono text-[12px] text-[var(--fg-3)]"
                        >
                            {{ entry.position }}
                        </span>
                        <Link
                            :href="gameShow.url(entry.slug)"
                            :class="
                                cn(
                                    'min-w-0 flex-1 truncate text-[15px] font-semibold no-underline hover:no-underline',
                                    entry.slug === game.slug
                                        ? 'text-[var(--teal-400)]'
                                        : 'text-[var(--fg-1)]',
                                )
                            "
                        >
                            {{ entry.name }}
                        </Link>
                        <span
                            class="h-1.5 w-[160px] shrink-0 overflow-hidden rounded-[var(--radius-pill)] bg-[var(--ink-700)]"
                        >
                            <span
                                class="block h-full rounded-[var(--radius-pill)]"
                                :style="{
                                    width: barWidth(entry.votes),
                                    background:
                                        entry.slug === game.slug
                                            ? 'var(--teal-400)'
                                            : 'var(--ink-500)',
                                }"
                            />
                        </span>
                        <span
                            class="w-[70px] shrink-0 text-right font-mono text-[12px] text-[var(--fg-2)]"
                        >
                            {{ entry.votes }}
                        </span>
                    </li>
                </ul>
            </section>
        </div>
    </div>
</template>
