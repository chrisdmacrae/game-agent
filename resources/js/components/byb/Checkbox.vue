<script setup lang="ts">
import { CheckboxIndicator, CheckboxRoot } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import Icon from '@/components/byb/Icon.vue';
import { cn } from '@/lib/utils';

type Props = {
    label: string;
    /** Optional mono count shown at the end of the row, e.g. a facet count. */
    count?: number | string;
    disabled?: boolean;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    count: undefined,
    disabled: false,
    class: undefined,
});

const model = defineModel<boolean>({ default: false });
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
        <CheckboxRoot
            v-model="model"
            :disabled="props.disabled"
            class="grid size-4 shrink-0 place-content-center rounded-[var(--radius-xs)] border border-[var(--border-subtle)] bg-[var(--surface-input)] text-[var(--teal-400)] outline-none [transition:var(--transition-control)] group-hover:border-[var(--border-strong)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--focus-ring)] data-[state=checked]:border-[var(--border-accent)] data-[state=checked]:bg-[var(--surface-accent-soft)]"
        >
            <CheckboxIndicator class="grid place-content-center">
                <Icon name="check" :size="11" />
            </CheckboxIndicator>
        </CheckboxRoot>
        <span class="flex-1">{{ props.label }}</span>
        <span
            v-if="props.count !== undefined"
            class="font-mono text-[12px] text-[var(--fg-3)]"
        >
            {{ props.count }}
        </span>
    </label>
</template>
