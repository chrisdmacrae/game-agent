<script setup lang="ts">
import { SwitchRoot, SwitchThumb } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

type Props = {
    label?: string;
    disabled?: boolean;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    label: undefined,
    disabled: false,
    class: undefined,
});

const model = defineModel<boolean>({ default: false });
</script>

<template>
    <label
        :class="
            cn(
                'flex cursor-pointer items-center gap-3 text-[13px] text-[var(--fg-2)] [transition:var(--transition-control)] hover:text-[var(--fg-1)] has-disabled:cursor-not-allowed has-disabled:opacity-40',
                props.class,
            )
        "
    >
        <SwitchRoot
            v-model="model"
            :disabled="props.disabled"
            class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-[var(--radius-pill)] border border-[var(--border-subtle)] bg-[var(--surface-input)] p-px outline-none [transition:var(--transition-control)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--focus-ring)] data-[state=checked]:border-[var(--teal-400)] data-[state=checked]:bg-[var(--teal-400)]"
        >
            <SwitchThumb
                class="block size-3.5 translate-x-0.5 rounded-full bg-[var(--ink-500)] [transition:transform_var(--dur-2)_var(--ease-snap),background-color_var(--dur-2)_var(--ease-out)] data-[state=checked]:translate-x-[18px] data-[state=checked]:bg-[var(--fg-inverse)] motion-reduce:transition-none"
            />
        </SwitchRoot>
        <span v-if="props.label" class="flex-1">{{ props.label }}</span>
    </label>
</template>
