<script setup lang="ts">
import { computed } from 'vue';
import type { CSSProperties, HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

type Props = {
    /** `card` is the flat slate surface, `grid` is the hairline planner canvas. */
    variant?: 'card' | 'grid';
    /** Card padding, defaults to 16px (`--sp-5`). */
    padding?: string;
    /** A token reference such as `var(--stage-endgame)` drawn as a 2px top edge. */
    accentEdge?: string;
    interactive?: boolean;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    variant: 'card',
    padding: 'var(--sp-5)',
    accentEdge: undefined,
    interactive: false,
    class: undefined,
});

const style = computed<CSSProperties>(() => ({
    padding: props.padding,
}));
</script>

<template>
    <div
        data-slot="byb-card"
        :style="style"
        :class="
            cn(
                'relative overflow-hidden rounded-[var(--radius-md)] border border-[var(--border-subtle)] [box-shadow:var(--shadow-1)] [transition:var(--transition-control)]',
                props.variant === 'grid'
                    ? 'bg-[var(--ink-950)] bg-[image:var(--texture-grid)] [background-size:var(--texture-grid-size)]'
                    : 'bg-[var(--surface-card)]',
                props.interactive &&
                    'cursor-pointer hover:border-[var(--border-strong)] hover:bg-[var(--surface-card-hover)]',
                props.class,
            )
        "
    >
        <span
            v-if="props.accentEdge"
            aria-hidden="true"
            class="absolute inset-x-0 top-0 h-0.5"
            :style="{ background: props.accentEdge }"
        />
        <slot />
    </div>
</template>
