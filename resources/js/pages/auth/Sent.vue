<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import Button from '@/components/byb/Button.vue';
import SeoHead from '@/components/SeoHead.vue';
import { login } from '@/routes';
import { store } from '@/routes/login-link';

/**
 * "Check your inbox" (scope §3.3, state 2). Reached only after a request, so
 * there is always an address to echo.
 */
defineOptions({
    layout: {
        eyebrow: 'Check your inbox',
        title: 'Link sent.',
    },
});

defineProps<{
    email: string;
    status?: string;
}>();
</script>

<template>
    <SeoHead />

    <div>
        <p class="text-[13px] leading-[1.5] text-[var(--fg-2)]">
            We sent a sign-in link to
        </p>

        <p
            class="mt-3 rounded-[var(--radius-sm)] border border-[var(--border-subtle)] bg-[var(--surface-input)] px-3 py-2.5 font-mono text-[14px] break-all text-[var(--fg-1)] [box-shadow:var(--shadow-inset-well)]"
        >
            {{ email }}
        </p>

        <p
            v-if="status"
            class="mt-3 font-mono text-[12px] leading-[1.45] text-[var(--teal-400)]"
        >
            {{ status }}
        </p>

        <div class="mt-5 flex flex-col gap-3">
            <Form v-bind="store.form()" v-slot="{ processing }">
                <input type="hidden" name="email" :value="email" />
                <Button
                    type="submit"
                    variant="primary"
                    full-width
                    :disabled="processing"
                    data-test="resend-link-button"
                >
                    Resend
                </Button>
            </Form>

            <Button variant="ghost" full-width as-child>
                <Link :href="login()">Change email</Link>
            </Button>
        </div>

        <p class="mt-5 text-center font-mono text-[12px] text-[var(--fg-3)]">
            Expires in 15:00 · one use
        </p>
    </div>
</template>
