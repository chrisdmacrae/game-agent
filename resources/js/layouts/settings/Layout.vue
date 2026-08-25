<script setup lang="ts">
import { LABEL_CLASS } from '@/components/byb/controls';
import { cn } from '@/lib/utils';

/**
 * Account settings shell (scope §3.9): a 760px column inside the site shell.
 * There is one settings page, so there is no rail.
 */
</script>

<template>
    <div class="mx-auto w-full max-w-[760px] py-10">
        <p :class="cn(LABEL_CLASS, 'text-[var(--teal-400)]')">Account</p>
        <h1
            class="mt-3 mb-8 font-display text-[36px] leading-none font-bold tracking-[-0.02em] text-[var(--fg-1)]"
        >
            Settings
        </h1>

        <slot />
    </div>
</template>
