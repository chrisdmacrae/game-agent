<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { HTMLAttributes } from 'vue';
import Button from '@/components/byb/Button.vue';
import { LABEL_CLASS } from '@/components/byb/controls';
import Dialog from '@/components/byb/Dialog.vue';
import { cn } from '@/lib/utils';
import { destroy } from '@/routes/profile';

/**
 * The settings danger zone (scope §3.9). The dialog names the account email
 * and the build counts; the controller re-checks the address on the delete.
 */
const props = defineProps<{
    email: string;
    counts: {
        published: number;
        drafts: number;
    };
    class?: HTMLAttributes['class'];
}>();

const open = ref(false);

const form = useForm({ email: props.email });

function plural(count: number, noun: string): string {
    return `${count} ${noun}${count === 1 ? '' : 's'}`;
}

const consequences = computed(
    () =>
        `This unlists ${plural(props.counts.published, 'published build')} and deletes ${plural(props.counts.drafts, 'draft')}. It cannot be undone.`,
);

function deleteAccount(): void {
    form.delete(destroy().url, {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
}
</script>

<template>
    <div
        :class="
            cn(
                'rounded-[var(--radius-md)] border border-[var(--red-600)] bg-[var(--surface-card)] p-6',
                props.class,
            )
        "
    >
        <p :class="cn(LABEL_CLASS, 'mb-3 text-[var(--red-400)]')">
            Danger zone
        </p>

        <div class="flex flex-wrap items-center gap-6">
            <p
                class="min-w-[240px] flex-1 text-[13px] leading-[1.5] text-pretty text-[var(--fg-2)]"
            >
                Deleting the account removes your drafts and unlists your
                published builds. Endorsements other players left are lost.
            </p>

            <Button
                variant="danger"
                data-test="delete-user-button"
                @click="open = true"
            >
                Delete account
            </Button>
        </div>

        <Dialog
            v-model:open="open"
            eyebrow="Danger zone"
            title="Delete this account?"
            :width="460"
        >
            <p class="text-[15px] leading-[1.6] text-[var(--fg-2)]">
                {{ consequences }}
            </p>
            <p class="mt-4 font-mono text-[12px] break-all text-[var(--fg-3)]">
                {{ email }}
            </p>
            <p
                v-if="form.errors.email"
                class="mt-4 font-mono text-[12px] text-[var(--red-400)]"
            >
                {{ form.errors.email }}
            </p>

            <template #footer>
                <Button variant="ghost" @click="open = false">Keep it</Button>
                <Button
                    variant="danger"
                    :disabled="form.processing"
                    data-test="confirm-delete-user-button"
                    @click="deleteAccount"
                >
                    Delete account
                </Button>
            </template>
        </Dialog>
    </div>
</template>
