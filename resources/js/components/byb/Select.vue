<script setup lang="ts">
import { computed, useId } from 'vue';
import type { HTMLAttributes } from 'vue';
import {
    CONTROL_SIZES,
    CONTROL_SURFACE,
    LABEL_CLASS,
} from '@/components/byb/controls';
import type { ControlSize } from '@/components/byb/controls';
import Icon from '@/components/byb/Icon.vue';
import { cn } from '@/lib/utils';

type SelectOption = {
    label: string;
    value: string | number;
};

type Props = {
    label?: string;
    options: (SelectOption | string)[];
    size?: ControlSize;
    placeholder?: string;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    label: undefined,
    size: 'md',
    placeholder: undefined,
    class: undefined,
});

const model = defineModel<string | number | null>();

defineOptions({ inheritAttrs: false });

const id = useId();

const normalized = computed<SelectOption[]>(() =>
    props.options.map((option) =>
        typeof option === 'string' ? { label: option, value: option } : option,
    ),
);
</script>

<template>
    <div class="flex flex-col gap-2">
        <label v-if="props.label" :for="id" :class="LABEL_CLASS">
            {{ props.label }}
        </label>
        <div class="relative">
            <select
                :id="id"
                v-model="model"
                v-bind="$attrs"
                :class="
                    cn(
                        CONTROL_SURFACE,
                        CONTROL_SIZES[props.size],
                        'appearance-none pr-8',
                        props.class,
                    )
                "
            >
                <option v-if="props.placeholder" :value="null" disabled>
                    {{ props.placeholder }}
                </option>
                <option
                    v-for="option in normalized"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>
            <span
                class="pointer-events-none absolute inset-y-0 right-2.5 flex items-center text-[var(--fg-3)]"
            >
                <Icon name="chevron-down" :size="13" />
            </span>
        </div>
    </div>
</template>
