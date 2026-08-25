<script setup lang="ts">
import { cva } from 'class-variance-authority';
import type { VariantProps } from 'class-variance-authority';
import { Primitive } from 'reka-ui';
import type { PrimitiveProps } from 'reka-ui';
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import Icon from '@/components/byb/Icon.vue';
import type { IconName, IconSize } from '@/components/byb/icons';
import { cn } from '@/lib/utils';

const bybButtonVariants = cva(
    'relative inline-flex shrink-0 items-center justify-center gap-2 rounded-[var(--radius-sm)] border font-mono font-bold whitespace-nowrap uppercase outline-none select-none [transition:var(--transition-control)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--focus-ring)] active:translate-y-px disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-40 motion-reduce:transition-none',
    {
        variants: {
            variant: {
                primary:
                    'border-[var(--teal-400)] bg-[var(--teal-400)] text-[var(--fg-inverse)] hover:border-[var(--teal-300)] hover:bg-[var(--teal-300)] hover:[box-shadow:var(--glow-teal)] active:border-[var(--teal-500)] active:bg-[var(--teal-500)] active:shadow-none',
                accent: 'border-[var(--mag-400)] bg-[var(--mag-400)] text-[var(--fg-inverse)] hover:[box-shadow:var(--glow-mag)] active:border-[var(--mag-500)] active:bg-[var(--mag-500)] active:shadow-none',
                ghost: 'border-[var(--border-subtle)] bg-transparent text-[var(--fg-2)] hover:border-[var(--border-strong)] hover:bg-[var(--surface-card-hover)] hover:text-[var(--fg-1)] active:bg-[var(--surface-card)]',
                danger: 'border-[var(--red-400)] bg-[var(--red-400)] text-[var(--fg-inverse)] active:border-[var(--red-600)] active:bg-[var(--red-600)]',
            },
            size: {
                sm: 'h-[var(--control-h-sm)] gap-1.5 px-2.5 text-[11px] tracking-[0.14em]',
                md: 'h-[var(--control-h)] px-3.5 text-[12px] tracking-[0.12em]',
                lg: 'h-[var(--control-h-lg)] px-5 text-[13px] tracking-[0.12em]',
            },
            fullWidth: {
                true: 'w-full',
                false: '',
            },
        },
        defaultVariants: {
            variant: 'primary',
            size: 'md',
            fullWidth: false,
        },
    },
);

type ButtonVariants = VariantProps<typeof bybButtonVariants>;

type Props = PrimitiveProps & {
    variant?: ButtonVariants['variant'];
    size?: ButtonVariants['size'];
    fullWidth?: boolean;
    icon?: IconName;
    iconRight?: IconName;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    as: 'button',
    variant: 'primary',
    size: 'md',
    fullWidth: false,
    icon: undefined,
    iconRight: undefined,
    class: undefined,
});

const iconSize = computed<IconSize>(() => (props.size === 'sm' ? 13 : 16));
</script>

<template>
    <Primitive
        data-slot="byb-button"
        :as="as"
        :as-child="asChild"
        :class="
            cn(
                bybButtonVariants({
                    variant: props.variant,
                    size: props.size,
                    fullWidth: props.fullWidth,
                }),
                props.class,
            )
        "
    >
        <Icon v-if="props.icon" :name="props.icon" :size="iconSize" />
        <slot />
        <Icon v-if="props.iconRight" :name="props.iconRight" :size="iconSize" />
    </Primitive>
</template>
