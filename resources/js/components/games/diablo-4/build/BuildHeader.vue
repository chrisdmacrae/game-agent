<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuPortal,
    DropdownMenuRoot,
    DropdownMenuTrigger,
} from 'reka-ui';
import { computed, onBeforeUnmount, ref } from 'vue';
import Badge from '@/components/byb/Badge.vue';
import Button from '@/components/byb/Button.vue';
import Card from '@/components/byb/Card.vue';
import Icon from '@/components/byb/Icon.vue';
import IconButton from '@/components/byb/IconButton.vue';
import StatBlock from '@/components/byb/StatBlock.vue';
import Tag from '@/components/byb/Tag.vue';
import { stageColor, tierColor } from '@/components/byb/tokens';
import type { BuildTier } from '@/components/byb/tokens';
import {
    compactNumber,
    contentTierLabel,
    D4_ACCENT,
    MISSING,
    plainNumber,
    stageLabel,
} from '@/components/games/diablo-4/build';
import type {
    BuildGame,
    BuildViewer,
    D4BuildDefinition,
} from '@/components/games/diablo-4/types';
import { bookmark, endorse } from '@/routes/games/builds';

/**
 * The Diablo IV identity header: class and level instead of class and
 * ascendancy, and Pit push instead of a divine budget.
 */
const props = defineProps<{
    build: {
        id: string;
        name: string;
        summary: string | null;
        visibility: string;
        definition: D4BuildDefinition;
        game_version: string | null;
        updated_at: string | null;
        endorsements: number | null;
        author: string | null;
        url: string;
        edit_url: string;
    };
    game: BuildGame;
    viewer: BuildViewer;
    signedIn: boolean;
}>();

const definition = computed(() => props.build.definition);

const stage = computed(() => stageLabel(definition.value));

const contentTier = computed(() => contentTierLabel(definition.value));

const tier = computed(() => definition.value.tier ?? null);

const TIER_TONES = {
    S: 'gold',
    A: 'accent',
    B: 'info',
    C: 'neutral',
} as const;

const isDraft = computed(() => props.build.visibility !== 'public');

const stats = computed(() => [
    { label: 'DPS', value: compactNumber(definition.value.dps) },
    { label: 'EHP', value: compactNumber(definition.value.ehp) },
    {
        label: 'Level',
        value: definition.value.level
            ? String(definition.value.level)
            : MISSING,
    },
    {
        label: 'Tier',
        value: tier.value ?? MISSING,
        tone: tier.value ? tierColor(tier.value) : 'var(--fg-3)',
    },
    {
        label: 'Endorsements',
        value: isDraft.value ? MISSING : plainNumber(props.build.endorsements),
        icon: 'flame' as const,
        tone: isDraft.value ? 'var(--fg-3)' : 'var(--teal-400)',
    },
]);

const routeArgs = computed<[string, string]>(() => [
    props.game.slug,
    props.build.id,
]);

function toggleEndorse(): void {
    const url = endorse.url(routeArgs.value);
    const options = { preserveScroll: true };

    if (props.viewer.endorsed) {
        router.delete(url, options);

        return;
    }

    router.post(url, {}, options);
}

function toggleBookmark(): void {
    const url = bookmark.url(routeArgs.value);
    const options = { preserveScroll: true };

    if (props.viewer.bookmarked) {
        router.delete(url, options);

        return;
    }

    router.post(url, {}, options);
}

// Share and the overflow menu. Diablo IV has no Path of Building export, so
// the export dropdown the PoE 2 header carries is not here.
const copied = ref<string | null>(null);
let copyTimer: ReturnType<typeof setTimeout> | undefined;

/** Whatever went wrong last, said plainly. Cleared on the next attempt. */
const actionError = ref<string | null>(null);

async function copy(key: string, text: string): Promise<void> {
    actionError.value = null;

    try {
        if (typeof navigator === 'undefined' || !navigator.clipboard) {
            throw new Error('no clipboard');
        }

        await navigator.clipboard.writeText(text);
    } catch {
        actionError.value = 'Your browser blocked the clipboard.';

        return;
    }

    copied.value = key;
    clearTimeout(copyTimer);
    copyTimer = setTimeout(() => {
        copied.value = null;
    }, 1600);
}

onBeforeUnmount(() => clearTimeout(copyTimer));

function shareLink(): void {
    const origin = typeof window === 'undefined' ? '' : window.location.origin;

    void copy('share', `${origin}${props.build.url}`);
}

const menuItemClass =
    'flex cursor-pointer items-center gap-2.5 rounded-[var(--radius-xs)] px-2.5 py-2 text-[13px] text-[var(--fg-2)] no-underline outline-none [transition:var(--transition-control)] data-highlighted:bg-[var(--surface-card-hover)] data-highlighted:text-[var(--fg-1)]';

const menuContentClass =
    'z-50 min-w-[240px] rounded-[var(--radius-md)] border border-[var(--border-subtle)] bg-[var(--surface-raised)] p-1.5 [box-shadow:var(--shadow-2)]';
</script>

<template>
    <Card
        variant="grid"
        padding="var(--sp-8)"
        :accent-edge="stage ? stageColor(stage) : undefined"
    >
        <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <Badge
                        v-if="tier"
                        :tone="TIER_TONES[tier as BuildTier] ?? 'neutral'"
                        :solid="tier === 'S'"
                    >
                        {{ tier }} tier
                    </Badge>
                    <Tag v-if="stage" :dot="stageColor(stage)">{{ stage }}</Tag>
                    <Badge v-if="contentTier" tone="danger">
                        {{ contentTier }}
                    </Badge>
                    <Badge v-if="definition.hardcore_viable" tone="gold">
                        Hardcore viable
                    </Badge>
                    <Badge v-if="build.game_version" tone="info">
                        {{ build.game_version }}
                    </Badge>
                    <Badge v-if="isDraft" tone="magenta" icon="triangle-alert">
                        Draft — only you can see this
                    </Badge>
                </div>

                <h1
                    class="mt-4 mb-2 font-display text-[36px] leading-[1] font-bold tracking-[-0.02em] text-[var(--fg-1)]"
                >
                    {{ build.name }}
                </h1>

                <div
                    class="flex flex-wrap items-center gap-2 text-[13px] text-[var(--fg-2)]"
                >
                    <span v-if="definition.class" :style="{ color: D4_ACCENT }">
                        {{ definition.class }}
                    </span>
                    <template v-if="definition.level">
                        <span class="text-[var(--ink-500)]">/</span>
                        <span>Level {{ definition.level }}</span>
                    </template>
                    <template v-if="build.author">
                        <span class="text-[var(--ink-500)]">·</span>
                        <span class="inline-flex items-center gap-1.5">
                            <Icon name="user" :size="13" />
                            {{ build.author }}
                        </span>
                    </template>
                    <template v-if="build.updated_at">
                        <span class="text-[var(--ink-500)]">·</span>
                        <span class="font-mono text-[12px] text-[var(--fg-3)]">
                            updated {{ build.updated_at }}
                        </span>
                    </template>
                </div>

                <p
                    v-if="build.summary"
                    class="mt-5 max-w-[620px] text-[17px] leading-[1.55] [text-wrap:pretty] text-[var(--fg-2)]"
                >
                    {{ build.summary }}
                </p>
            </div>

            <div class="flex w-full flex-col gap-2 lg:w-[190px]">
                <!-- The icon lives inside the Link: `icon` renders outside the
                     button box when `as-child` is set. -->
                <Button
                    v-if="viewer.can_edit"
                    variant="primary"
                    full-width
                    as-child
                >
                    <Link :href="build.edit_url">
                        <Icon name="sliders-horizontal" :size="16" />
                        Edit build
                    </Link>
                </Button>
                <Button
                    v-else-if="signedIn"
                    variant="primary"
                    icon="flame"
                    full-width
                    :class="
                        viewer.endorsed
                            ? 'border-[var(--border-accent)] bg-[var(--surface-accent-soft)] text-[var(--teal-400)] hover:bg-[var(--surface-accent-soft-hover)] hover:text-[var(--teal-300)] hover:shadow-none'
                            : undefined
                    "
                    @click="toggleEndorse"
                >
                    {{ viewer.endorsed ? 'Endorsed' : 'Endorse' }}
                </Button>

                <Button
                    v-if="signedIn"
                    variant="ghost"
                    icon="bookmark"
                    full-width
                    @click="toggleBookmark"
                >
                    {{ viewer.bookmarked ? 'Saved' : 'Save' }}
                </Button>

                <div class="flex gap-2">
                    <IconButton
                        :icon="copied === 'share' ? 'check' : 'share-2'"
                        :label="copied === 'share' ? 'Link copied' : 'Share'"
                        class="flex-1"
                        @click="shareLink"
                    />

                    <DropdownMenuRoot>
                        <DropdownMenuTrigger as-child>
                            <IconButton
                                icon="ellipsis"
                                label="More"
                                class="flex-1"
                            />
                        </DropdownMenuTrigger>
                        <DropdownMenuPortal>
                            <DropdownMenuContent
                                :side-offset="6"
                                align="end"
                                :class="menuContentClass"
                            >
                                <DropdownMenuItem
                                    :class="menuItemClass"
                                    @select.prevent="copy('id', build.id)"
                                >
                                    <Icon
                                        :name="
                                            copied === 'id' ? 'check' : 'copy'
                                        "
                                        :size="13"
                                    />
                                    Copy build id
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    :class="menuItemClass"
                                    @select.prevent="
                                        copy(
                                            'uri',
                                            `byb://${game.slug}/build/${build.id}`,
                                        )
                                    "
                                >
                                    <Icon
                                        :name="
                                            copied === 'uri' ? 'check' : 'copy'
                                        "
                                        :size="13"
                                    />
                                    Copy MCP resource uri
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenuPortal>
                    </DropdownMenuRoot>
                </div>

                <p v-if="actionError" class="text-[13px] text-[var(--red-400)]">
                    {{ actionError }}
                </p>
            </div>
        </div>

        <div
            class="mt-8 flex flex-wrap gap-10 border-t border-[var(--border-hairline)] pt-5"
        >
            <StatBlock
                v-for="stat in stats"
                :key="stat.label"
                :label="stat.label"
                :value="stat.value"
                :icon="stat.icon"
                :tone="stat.tone"
            />
        </div>
    </Card>
</template>
