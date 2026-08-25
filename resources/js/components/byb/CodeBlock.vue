<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue';
import type { HTMLAttributes } from 'vue';
import { LABEL_CLASS } from '@/components/byb/controls';
import Icon from '@/components/byb/Icon.vue';
import { cn } from '@/lib/utils';

type Props = {
    code: string;
    /** Caption row above the well, e.g. "poe2 server url". */
    filename?: string;
    copyable?: boolean;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    filename: undefined,
    copyable: true,
    class: undefined,
});

const copied = ref(false);
let resetTimer: ReturnType<typeof setTimeout> | undefined;

async function copy(): Promise<void> {
    if (typeof navigator === 'undefined' || !navigator.clipboard) {
        return;
    }

    await navigator.clipboard.writeText(props.code);
    copied.value = true;
    clearTimeout(resetTimer);
    resetTimer = setTimeout(() => {
        copied.value = false;
    }, 1600);
}

onBeforeUnmount(() => clearTimeout(resetTimer));
</script>

<template>
    <div
        :class="
            cn(
                'overflow-hidden rounded-[var(--radius-sm)] border border-[var(--border-subtle)] bg-[var(--ink-950)]',
                props.class,
            )
        "
    >
        <div
            v-if="props.filename || props.copyable"
            class="flex items-center gap-3 border-b border-[var(--border-hairline)] px-3 py-2"
        >
            <span v-if="props.filename" :class="LABEL_CLASS">
                {{ props.filename }}
            </span>
            <button
                v-if="props.copyable"
                type="button"
                class="ml-auto inline-flex items-center gap-1.5 rounded-[var(--radius-xs)] px-1.5 py-1 font-mono text-[11px] font-bold tracking-[0.14em] text-[var(--fg-3)] uppercase outline-none [transition:var(--transition-control)] hover:bg-[var(--surface-raised)] hover:text-[var(--fg-1)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--focus-ring)]"
                @click="copy"
            >
                <Icon
                    :name="copied ? 'check' : 'copy'"
                    :size="11"
                    :class="copied ? 'text-[var(--teal-400)]' : undefined"
                />
                {{ copied ? 'Copied' : 'Copy' }}
            </button>
        </div>
        <pre
            class="overflow-x-auto px-3 py-2.5 font-mono text-[13px] leading-[1.5] break-all whitespace-pre-wrap text-[var(--fg-1)]"
        ><code>{{ props.code }}</code></pre>
    </div>
</template>
