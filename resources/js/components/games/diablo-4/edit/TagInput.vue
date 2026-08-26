<script setup lang="ts">
import { ref } from 'vue';
import { CONTROL_SURFACE, LABEL_CLASS } from '@/components/byb/controls';
import Icon from '@/components/byb/Icon.vue';
import { cn } from '@/lib/utils';

/**
 * A tag-style list input: type an entry, press Enter or comma to commit it,
 * Backspace on an empty field takes the last one back.
 *
 * Skill modifiers, item affixes, runes and board notables are all short free
 * strings the assistant fills in and a human corrects one at a time, which is
 * what this is for — a comma-separated text field makes editing the third
 * entry mean retyping the line.
 */
const model = defineModel<string[]>({ required: true });

const props = withDefaults(
    defineProps<{
        label?: string;
        placeholder?: string;
        /** Refuses to add past this many entries; the game caps most of these. */
        max?: number;
        error?: string;
    }>(),
    {
        label: undefined,
        placeholder: 'Add an entry',
        max: undefined,
        error: undefined,
    },
);

const draft = ref('');

const full = () => props.max !== undefined && model.value.length >= props.max;

function commit(): void {
    const value = draft.value.trim();

    if (value === '' || full()) {
        draft.value = '';

        return;
    }

    model.value = [...model.value, value];
    draft.value = '';
}

function remove(index: number): void {
    model.value = model.value.filter((_, position) => position !== index);
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' || event.key === ',') {
        event.preventDefault();
        commit();

        return;
    }

    if (event.key !== 'Backspace') {
        return;
    }

    // Backspace on an empty field puts the last tag back in the field so a
    // typo is corrected rather than retyped.
    if (draft.value !== '' || model.value.length === 0) {
        return;
    }

    draft.value = model.value[model.value.length - 1];
    model.value = model.value.slice(0, -1);
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <div v-if="props.label || props.max" class="flex items-center gap-2">
            <span v-if="props.label" :class="LABEL_CLASS">
                {{ props.label }}
            </span>
            <span
                v-if="props.max"
                class="ml-auto font-mono text-[12px] text-[var(--fg-3)]"
            >
                {{ model.length }} / {{ props.max }}
            </span>
        </div>

        <div v-if="model.length" class="flex flex-wrap gap-1.5">
            <span
                v-for="(tag, index) in model"
                :key="`${index}-${tag}`"
                class="inline-flex items-center gap-1.5 rounded-[var(--radius-pill)] border border-[var(--border-subtle)] bg-[var(--surface-card-hover)] py-1 pr-1.5 pl-2.5 font-mono text-[12px] leading-none text-[var(--fg-2)]"
            >
                {{ tag }}
                <button
                    type="button"
                    :aria-label="`Remove ${tag}`"
                    class="-mr-0.5 inline-flex size-4 items-center justify-center rounded-[var(--radius-xs)] text-[var(--fg-3)] outline-none [transition:var(--transition-control)] hover:bg-[var(--surface-raised)] hover:text-[var(--fg-1)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--focus-ring)]"
                    @click="remove(index)"
                >
                    <Icon name="x" :size="11" />
                </button>
            </span>
        </div>

        <input
            v-model="draft"
            type="text"
            :placeholder="full() ? 'Full' : props.placeholder"
            :disabled="full()"
            :class="
                cn(
                    CONTROL_SURFACE,
                    'h-[var(--control-h-sm)] px-2.5 text-[13px]',
                    props.error &&
                        'border-[var(--red-400)] focus:border-[var(--red-400)] focus:outline-[var(--red-400)]',
                )
            "
            @keydown="onKeydown"
            @blur="commit"
        />

        <p
            v-if="props.error"
            class="font-mono text-[12px] text-[var(--red-400)]"
        >
            {{ props.error }}
        </p>
    </div>
</template>
