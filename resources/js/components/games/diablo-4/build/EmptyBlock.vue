<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/**
 * A partial build says so. Nothing here invents a zero for a number the
 * publisher never entered.
 */
const props = withDefaults(
    defineProps<{
        message?: string;
        class?: HTMLAttributes['class'];
    }>(),
    {
        message: 'Not filled in yet.',
        class: undefined,
    },
);
</script>

<template>
    <div
        :class="
            cn(
                'rounded-[var(--radius-sm)] border border-dashed border-[var(--border-subtle)] px-4 py-6 text-center text-[13px] text-[var(--fg-3)]',
                props.class,
            )
        "
    >
        {{ props.message }}
    </div>
</template>
