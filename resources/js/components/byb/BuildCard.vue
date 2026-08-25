<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import Badge from '@/components/byb/Badge.vue';
import Card from '@/components/byb/Card.vue';
import Icon from '@/components/byb/Icon.vue';
import Tag from '@/components/byb/Tag.vue';
import { stageColor } from '@/components/byb/tokens';
import type { BuildStage, BuildTier } from '@/components/byb/tokens';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    /** The character class; always rendered in teal. */
    buildClass: string;
    ascendancy?: string;
    stage?: BuildStage;
    tier?: BuildTier;
    patch?: string;
    author?: string;
    updated?: string;
    dps?: string;
    ehp?: string;
    cost?: string;
    endorsements?: number | string;
    draft?: boolean;
    orientation?: 'grid' | 'list';
    href?: string;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    ascendancy: undefined,
    stage: undefined,
    tier: undefined,
    patch: undefined,
    author: undefined,
    updated: undefined,
    dps: undefined,
    ehp: undefined,
    cost: undefined,
    endorsements: undefined,
    draft: false,
    orientation: 'grid',
    href: undefined,
    class: undefined,
});

const TIER_TONES = {
    S: 'gold',
    A: 'accent',
    B: 'info',
    C: 'neutral',
} as const;

const isList = computed(() => props.orientation === 'list');

const meta = computed(() =>
    [
        props.dps ? `${props.dps} dps` : null,
        props.ehp ? `${props.ehp} ehp` : null,
        props.cost ? `${props.cost} div` : null,
        props.patch,
    ]
        .filter((part): part is string => Boolean(part))
        .join(' · '),
);
</script>

<template>
    <component
        :is="props.href ? Link : 'div'"
        :href="props.href"
        :class="cn('block', props.class)"
    >
        <Card
            :accent-edge="stageColor(props.stage)"
            :interactive="Boolean(props.href)"
            class="h-full"
        >
            <div
                :class="
                    cn(
                        'flex gap-5',
                        isList ? 'items-center' : 'flex-col items-stretch',
                    )
                "
            >
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge
                            v-if="props.tier"
                            :tone="TIER_TONES[props.tier]"
                            :solid="props.tier === 'S'"
                        >
                            {{ props.tier }} tier
                        </Badge>
                        <Tag v-if="props.stage" :dot="stageColor(props.stage)">
                            {{ props.stage }}
                        </Tag>
                        <Badge v-if="props.draft" tone="magenta"> Draft </Badge>
                    </div>

                    <h3
                        class="mt-3 truncate text-[18px] leading-[1.28] font-semibold text-[var(--fg-1)]"
                    >
                        {{ props.title }}
                    </h3>

                    <p class="mt-1 text-[13px] text-[var(--fg-2)]">
                        <span class="text-[var(--teal-400)]">
                            {{ props.buildClass }}
                        </span>
                        <span v-if="props.ascendancy">
                            <span class="text-[var(--ink-500)]"> / </span>
                            {{ props.ascendancy }}
                        </span>
                        <span v-if="props.author">
                            <span class="text-[var(--ink-500)]"> · </span>
                            {{ props.author }}
                        </span>
                    </p>

                    <p
                        v-if="meta"
                        class="mt-3 truncate font-mono text-[12px] text-[var(--fg-3)]"
                    >
                        {{ meta }}
                    </p>
                </div>

                <div
                    :class="
                        cn(
                            'flex items-center gap-4 font-mono text-[12px] text-[var(--fg-3)]',
                            isList
                                ? 'shrink-0 flex-col items-end gap-1'
                                : 'mt-1 border-t border-[var(--border-hairline)] pt-3',
                        )
                    "
                >
                    <span
                        v-if="props.endorsements !== undefined"
                        class="inline-flex items-center gap-1.5"
                    >
                        <Icon name="flame" :size="13" />
                        {{ props.endorsements }}
                    </span>
                    <span v-if="props.updated" class="ml-auto">
                        updated {{ props.updated }}
                    </span>
                </div>
            </div>
        </Card>
    </component>
</template>
