<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { LABEL_CLASS } from '@/components/byb/controls';
import SeoHead from '@/components/SeoHead.vue';
import { cn } from '@/lib/utils';

/**
 * Verifying (scope §3.3, state 3), shared by sign-in links and email-change
 * confirmations. The heading is a prop, so it is rendered here rather than
 * handed to the layout.
 *
 * The token is consumed by the POST this page fires on mount, never by the GET
 * that rendered it — mail scanners fetch emailed links, and the token is
 * single-use.
 */
const props = withDefaults(
    defineProps<{
        token: string;
        action: string;
        title?: string;
    }>(),
    { title: 'Signing you in' },
);

/** The bar is progress theatre: the POST below decides when the page leaves. */
const progress = ref(4);
const timers: ReturnType<typeof setTimeout>[] = [];

const maskedToken = computed(
    () => `token ${'•'.repeat(8)}${props.token.slice(-4)}`,
);

onMounted(() => {
    timers.push(setTimeout(() => (progress.value = 38), 60));
    timers.push(setTimeout(() => (progress.value = 72), 380));
    timers.push(setTimeout(() => (progress.value = 94), 900));

    router.post(props.action, {}, { preserveState: false });
});

onBeforeUnmount(() => timers.forEach((timer) => clearTimeout(timer)));
</script>

<template>
    <SeoHead />

    <div>
        <p :class="cn(LABEL_CLASS, 'text-[var(--teal-400)]')">Verifying</p>

        <h1
            class="mt-3 font-display text-[28px] leading-[1.14] font-bold tracking-[-0.02em] text-[var(--fg-1)]"
        >
            {{ title }}.
        </h1>

        <p
            class="mt-2 text-[13px] leading-[1.5] text-pretty text-[var(--fg-2)]"
        >
            Checking the token and loading your builds. This takes a second.
        </p>

        <div
            class="mt-6 h-1 overflow-hidden rounded-[var(--radius-pill)] bg-[var(--ink-700)]"
        >
            <div
                class="h-full bg-[var(--teal-400)] transition-[width] duration-[240ms] [transition-timing-function:var(--ease-out)] motion-reduce:transition-none"
                :style="{ width: `${progress}%` }"
            />
        </div>

        <p class="mt-4 font-mono text-[12px] text-[var(--fg-3)]">
            {{ maskedToken }}
        </p>
    </div>
</template>
