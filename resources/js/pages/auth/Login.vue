<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import Button from '@/components/byb/Button.vue';
import Input from '@/components/byb/Input.vue';
import SeoHead from '@/components/SeoHead.vue';
import { store } from '@/routes/login-link';

/**
 * Magic link request (scope §3.3, state 1). A malformed address comes back as
 * a danger toast flashed by the controller, not as an inline redirect; the
 * inline error below only carries the "link expired" case from consume().
 */
defineOptions({
    layout: {
        eyebrow: 'Sign in',
        title: 'No password. We send a link.',
        description:
            'Enter your email and we send a one-time link. It expires in 15 minutes and signs in one device.',
    },
});
</script>

<template>
    <SeoHead title="Sign in" noindex />

    <Form v-bind="store.form()" v-slot="{ errors, processing }">
        <Input
            label="Email"
            type="email"
            name="email"
            required
            autofocus
            autocomplete="email"
            placeholder="you@example.com"
            :error="errors.email"
        />

        <div class="mt-5">
            <Button
                type="submit"
                size="lg"
                variant="primary"
                full-width
                :disabled="processing"
                data-test="login-button"
            >
                Send magic link
            </Button>
        </div>

        <p
            class="mt-5 text-center text-[13px] leading-[1.5] text-pretty text-[var(--fg-3)]"
        >
            Browsing builds needs no account. Signing in lets you publish and
            edit your own.
        </p>
    </Form>
</template>
