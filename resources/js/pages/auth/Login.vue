<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import SeoHead from '@/components/SeoHead.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login-link';

defineOptions({
    layout: {
        title: 'Sign in',
        description:
            "Enter your email and we'll send you a magic sign-in link. No password needed — new accounts are created automatically.",
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <SeoHead title="Sign in" noindex />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <Form
        v-bind="store.form()"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                :tabindex="2"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Email me a sign-in link
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            The link signs you in instantly and expires after 15 minutes.
        </div>
    </Form>
</template>
