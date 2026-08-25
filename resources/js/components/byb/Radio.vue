<script setup lang="ts">
import { RadioGroupIndicator, RadioGroupItem } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

type Props = {
    value: string | number;
    label: string;
    /** Optional mono count shown at the end of the row. */
    count?: number | string;
    disabled?: boolean;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    count: undefined,
    disabled: false,
    class: undefined,
});
</script>

<template>
    <label
        :class="
            cn(
                'group flex cursor-pointer items-center gap-2.5 text-[13px] text-[var(--fg-2)] [transition:var(--transition-control)] hover:text-[var(--fg-1)] has-disabled:cursor-not-allowed has-disabled:opacity-40',
                props.class,
            )
        "
    >
        <RadioGroupItem
            :value="props.value"
            :disabled="props.disabled"
            class="grid size-4 shrink-0 place-content-center rounded-full border border-[var(--border-subtle)] bg-[var(--surface-input)] outline-none [transition:var(--transition-control)] group-hover:border-[var(--border-strong)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--focus-ring)] data-[state=checked]:border-[var(--border-accent)] data-[state=checked]:bg-[var(--surface-accent-soft)]"
        >
            <RadioGroupIndicator
                class="size-1.5 rounded-full bg-[var(--teal-400)]"
            />
        </RadioGroupItem>
        <span class="flex-1">{{ props.label }}</span>
        <span
            v-if="props.count !== undefined"
            class="font-mono text-[12px] text-[var(--fg-3)]"
        >
            {{ props.count }}
        </span>
    </label>
</template>
