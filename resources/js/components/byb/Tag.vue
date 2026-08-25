<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import Icon from '@/components/byb/Icon.vue';
import { cn } from '@/lib/utils';

type Props = {
    /** A token reference such as `var(--stage-endgame)`; renders a leading dot. */
    dot?: string;
    /** Renders the tag as a removable filter chip. */
    removable?: boolean;
    removeLabel?: string;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    dot: undefined,
    removable: false,
    removeLabel: 'Remove filter',
    class: undefined,
});

defineEmits<{
    remove: [];
}>();
</script>

<template>
    <span
        data-slot="byb-tag"
        :class="
            cn(
                'inline-flex h-[22px] items-center gap-2 rounded-[var(--radius-pill)] border border-[var(--border-subtle)] bg-transparent pr-2.5 pl-2.5 font-mono text-[12px] leading-none text-[var(--fg-2)] [transition:var(--transition-control)]',
                props.removable && 'pr-1.5',
                props.class,
            )
        "
    >
        <span
            v-if="props.dot"
            class="size-1.5 shrink-0 rounded-full"
            :style="{ background: props.dot }"
        />
        <slot />
        <button
            v-if="props.removable"
            type="button"
            :aria-label="props.removeLabel"
            class="-mr-0.5 inline-flex size-4 items-center justify-center rounded-[var(--radius-xs)] text-[var(--fg-3)] outline-none [transition:var(--transition-control)] hover:bg-[var(--surface-raised)] hover:text-[var(--fg-1)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--focus-ring)]"
            @click="$emit('remove')"
        >
            <Icon name="x" :size="11" />
        </button>
    </span>
</template>
