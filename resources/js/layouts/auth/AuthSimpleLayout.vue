<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Card from '@/components/byb/Card.vue';
import { LABEL_CLASS } from '@/components/byb/controls';
import Toaster from '@/components/byb/Toaster.vue';
import { cn } from '@/lib/utils';
import { home } from '@/routes';

defineProps<{
    /** Mono uppercase kicker above the heading, e.g. "Sign in". */
    eyebrow?: string;
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center bg-[var(--surface-page)] bg-[image:var(--texture-grid)] [background-size:var(--texture-grid-size)] p-6"
    >
        <div class="w-full max-w-[420px]">
            <Link
                :href="home()"
                class="mb-8 flex items-center justify-center gap-2 no-underline hover:no-underline"
            >
                <span
                    class="inline-flex size-[34px] items-center justify-center rounded-[var(--radius-sm)] bg-[var(--teal-400)] font-display text-[15px] leading-none font-bold tracking-[-0.02em] text-[var(--fg-inverse)]"
                >
                    BYB
                </span>
                <span
                    class="font-display text-[18px] leading-none font-extrabold tracking-[-0.02em] text-[var(--fg-1)]"
                >
                    BUILD<span class="text-[var(--teal-400)]">/</span>YOUR<span
                        class="text-[var(--teal-400)]"
                        >/</span
                    >BUILD
                </span>
            </Link>

            <Card padding="var(--sp-8)">
                <p
                    v-if="eyebrow"
                    :class="cn(LABEL_CLASS, 'text-[var(--teal-400)]')"
                >
                    {{ eyebrow }}
                </p>
                <h1
                    v-if="title"
                    :class="
                        cn(
                            'font-display text-[28px] leading-[1.14] font-bold tracking-[-0.02em] text-[var(--fg-1)]',
                            eyebrow && 'mt-3',
                        )
                    "
                >
                    {{ title }}
                </h1>
                <p
                    v-if="description"
                    class="mt-2 text-[13px] leading-[1.5] text-pretty text-[var(--fg-2)]"
                >
                    {{ description }}
                </p>
                <div :class="eyebrow || title || description ? 'mt-6' : ''">
                    <slot />
                </div>
            </Card>
        </div>

        <Toaster />
    </div>
</template>
