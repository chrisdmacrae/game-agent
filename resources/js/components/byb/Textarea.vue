<script setup lang="ts">
import { computed, useId } from 'vue';
import type { HTMLAttributes } from 'vue';
import { CONTROL_SURFACE, LABEL_CLASS } from '@/components/byb/controls';
import { cn } from '@/lib/utils';

type Props = {
    label?: string;
    rows?: number;
    /** Shows a mono counter under the field; turns red past the limit. */
    maxlength?: number;
    hint?: string;
    error?: string;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    label: undefined,
    rows: 4,
    maxlength: undefined,
    hint: undefined,
    error: undefined,
    class: undefined,
});

const model = defineModel<string>({ default: '' });

defineOptions({ inheritAttrs: false });

const id = useId();
const count = computed(() => (model.value ?? '').length);
const overLimit = computed(
    () => props.maxlength !== undefined && count.value > props.maxlength,
);
</script>

<template>
    <div class="flex flex-col gap-2">
        <label v-if="props.label" :for="id" :class="LABEL_CLASS">
            {{ props.label }}
        </label>
        <textarea
            :id="id"
            v-model="model"
            v-bind="$attrs"
            :rows="props.rows"
            :aria-invalid="props.error || overLimit ? 'true' : undefined"
            :class="
                cn(
                    CONTROL_SURFACE,
                    'resize-y px-3 py-2.5 text-[15px] leading-[1.6]',
                    (props.error || overLimit) &&
                        'border-[var(--red-400)] focus:border-[var(--red-400)] focus:outline-[var(--red-400)]',
                    props.class,
                )
            "
        />
        <div class="flex items-baseline gap-4">
            <p
                v-if="props.error"
                class="flex-1 font-mono text-[12px] text-[var(--red-400)]"
            >
                {{ props.error }}
            </p>
            <p
                v-else-if="props.hint"
                class="flex-1 text-[13px] text-[var(--fg-3)]"
            >
                {{ props.hint }}
            </p>
            <span
                v-if="props.maxlength !== undefined"
                :class="
                    cn(
                        'ml-auto font-mono text-[12px]',
                        overLimit
                            ? 'text-[var(--red-400)]'
                            : 'text-[var(--fg-3)]',
                    )
                "
            >
                {{ count }} / {{ props.maxlength }}
            </span>
        </div>
    </div>
</template>
