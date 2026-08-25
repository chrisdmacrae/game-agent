<script setup lang="ts">
import { Primitive } from 'reka-ui';
import type { PrimitiveProps } from 'reka-ui';
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import Icon from '@/components/byb/Icon.vue';
import type { IconName, IconSize } from '@/components/byb/icons';
import { cn } from '@/lib/utils';

type Props = PrimitiveProps & {
    icon: IconName;
    /** Required: an icon is never the only carrier of meaning. */
    label: string;
    size?: 'sm' | 'md' | 'lg';
    active?: boolean;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    as: 'button',
    size: 'md',
    active: false,
    class: undefined,
});

const sizeClass = computed(
    () =>
        ({
            sm: 'size-[var(--control-h-sm)]',
            md: 'size-[var(--control-h)]',
            lg: 'size-[var(--control-h-lg)]',
        })[props.size],
);

const iconSize = computed<IconSize>(() => (props.size === 'sm' ? 13 : 16));
</script>

<template>
    <Primitive
        data-slot="byb-icon-button"
        :as="as"
        :as-child="asChild"
        :aria-label="props.label"
        :title="props.label"
        :aria-pressed="props.active ? 'true' : undefined"
        :class="
            cn(
                'inline-flex shrink-0 items-center justify-center rounded-[var(--radius-sm)] border outline-none [transition:var(--transition-control)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--focus-ring)] active:translate-y-px disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-40 motion-reduce:transition-none',
                sizeClass,
                props.active
                    ? 'border-[var(--border-accent)] bg-[var(--surface-accent-soft)] text-[var(--teal-400)] hover:bg-[var(--surface-accent-soft-hover)]'
                    : 'border-[var(--border-subtle)] bg-transparent text-[var(--fg-2)] hover:border-[var(--border-strong)] hover:bg-[var(--surface-card-hover)] hover:text-[var(--fg-1)]',
                props.class,
            )
        "
    >
        <Icon :name="props.icon" :size="iconSize" />
    </Primitive>
</template>
