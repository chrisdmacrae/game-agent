<script setup lang="ts">
import { computed } from 'vue';
import type { CSSProperties, HTMLAttributes } from 'vue';
import Icon from '@/components/byb/Icon.vue';
import type { IconName } from '@/components/byb/icons';
import { cn } from '@/lib/utils';

type BadgeTone = 'accent' | 'magenta' | 'gold' | 'info' | 'danger' | 'neutral';

type Props = {
    tone?: BadgeTone;
    solid?: boolean;
    icon?: IconName;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    tone: 'neutral',
    solid: false,
    icon: undefined,
    class: undefined,
});

const TONE_COLORS: Record<BadgeTone, string> = {
    accent: 'var(--teal-400)',
    magenta: 'var(--mag-400)',
    gold: 'var(--gold-400)',
    info: 'var(--blue-400)',
    danger: 'var(--red-400)',
    neutral: 'var(--fg-3)',
};

const style = computed<CSSProperties>(() => {
    const color = TONE_COLORS[props.tone];

    if (props.solid) {
        return {
            background: color,
            borderColor: color,
            color: 'var(--fg-inverse)',
        };
    }

    return {
        background: `color-mix(in srgb, ${color} 12%, transparent)`,
        borderColor: `color-mix(in srgb, ${color} 45%, transparent)`,
        color,
    };
});
</script>

<template>
    <span
        data-slot="byb-badge"
        :style="style"
        :class="
            cn(
                'inline-flex h-5 items-center gap-1 rounded-[var(--radius-xs)] border px-1.5 font-mono text-[11px] leading-none font-bold tracking-[0.14em] uppercase',
                props.class,
            )
        "
    >
        <Icon v-if="props.icon" :name="props.icon" :size="11" />
        <slot />
    </span>
</template>
