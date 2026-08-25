<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import Button from '@/components/byb/Button.vue';
import Card from '@/components/byb/Card.vue';
import { LABEL_CLASS } from '@/components/byb/controls';
import Input from '@/components/byb/Input.vue';
import Textarea from '@/components/byb/Textarea.vue';
import DeleteUser from '@/components/DeleteUser.vue';
import SeoHead from '@/components/SeoHead.vue';
import { cn } from '@/lib/utils';
import { update } from '@/routes/profile';

/**
 * Account settings (scope §3.9). The 760px column and the page heading come
 * from the settings layout.
 */
type Profile = {
    name: string;
    handle: string;
    discord_username: string | null;
    bio: string | null;
    email: string;
};

const props = defineProps<{
    profile: Profile;
    pendingEmail: string | null;
    buildCounts: {
        published: number;
        drafts: number;
    };
}>();

/**
 * `name` is not edited here — the public identity is the handle — but the
 * request validates it, so it rides along unchanged.
 */
const form = useForm({
    name: props.profile.name,
    handle: props.profile.handle,
    discord_username: props.profile.discord_username ?? '',
    bio: props.profile.bio ?? '',
    email: props.profile.email,
});

function save(): void {
    form.patch(update().url, { preserveScroll: true });
}
</script>

<template>
    <SeoHead title="Settings" noindex />

    <div class="flex flex-col gap-4">
        <form class="flex flex-col gap-4" @submit.prevent="save">
            <Card padding="var(--sp-7)">
                <p :class="cn(LABEL_CLASS, 'mb-4')">Profile</p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <Input
                        v-model="form.handle"
                        label="Handle / gamertag"
                        name="handle"
                        required
                        autocomplete="nickname"
                        hint="Shown on every build you publish"
                        :error="form.errors.handle"
                    />
                    <Input
                        v-model="form.discord_username"
                        label="Discord username"
                        name="discord_username"
                        autocomplete="off"
                        hint="Optional — for build questions"
                        :error="form.errors.discord_username"
                    />
                </div>

                <div class="mt-4">
                    <Textarea
                        v-model="form.bio"
                        label="Bio"
                        name="bio"
                        :rows="3"
                        :maxlength="180"
                        hint="Shown on your builds"
                        :error="form.errors.bio"
                    />
                </div>
            </Card>

            <Card padding="var(--sp-7)">
                <p :class="cn(LABEL_CLASS, 'mb-4')">Email</p>

                <Input
                    v-model="form.email"
                    label="Email"
                    type="email"
                    name="email"
                    required
                    autocomplete="email"
                    hint="Used for sign-in links only"
                    :error="form.errors.email"
                />

                <p class="mt-4 text-[13px] leading-[1.5] text-[var(--fg-2)]">
                    Changing this sends a magic link to the new address. The old
                    one keeps working until you use it.
                </p>

                <p
                    v-if="pendingEmail"
                    class="mt-4 rounded-[var(--radius-sm)] border border-[var(--border-subtle)] bg-[var(--surface-input)] px-3 py-2.5 font-mono text-[12px] leading-[1.45] break-all text-[var(--gold-400)]"
                >
                    Waiting on confirmation · {{ pendingEmail }}
                </p>
            </Card>

            <div class="flex items-center gap-3">
                <Button
                    type="submit"
                    variant="primary"
                    icon="check"
                    :disabled="form.processing"
                    data-test="update-profile-button"
                >
                    Save changes
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    :disabled="form.processing"
                    @click="form.reset()"
                >
                    Cancel
                </Button>
            </div>
        </form>

        <DeleteUser class="mt-4" :email="profile.email" :counts="buildCounts" />
    </div>
</template>
