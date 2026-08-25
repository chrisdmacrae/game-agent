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
import type { IconName } from '@/components/byb/icons';
import { cn } from '@/lib/utils';

type Props = {
    label?: string;
    size?: ControlSize;
    icon?: IconName;
    /** Numbers, patch strings and currency always render in Azeret Mono. */
    mono?: boolean;
    hint?: string;
    error?: string;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    label: undefined,
    size: 'md',
    icon: undefined,
    mono: false,
    hint: undefined,
    error: undefined,
    class: undefined,
});

const model = defineModel<string | number | null>();

defineOptions({ inheritAttrs: false });

const id = useId();
const iconSize = computed(() => (props.size === 'sm' ? 13 : 16));
</script>

<template>
    <div class="flex flex-col gap-2">
        <label v-if="props.label" :for="id" :class="LABEL_CLASS">
            {{ props.label }}
        </label>
        <div class="relative">
            <span
                v-if="props.icon"
                class="pointer-events-none absolute inset-y-0 left-2.5 flex items-center text-[var(--fg-3)]"
            >
                <Icon :name="props.icon" :size="iconSize" />
            </span>
            <input
                :id="id"
                v-model="model"
                v-bind="$attrs"
                :aria-invalid="props.error ? 'true' : undefined"
                :class="
                    cn(
                        CONTROL_SURFACE,
                        CONTROL_SIZES[props.size],
                        props.mono && 'font-mono',
                        props.icon && (props.size === 'sm' ? 'pl-8' : 'pl-9'),
                        props.error &&
                            'border-[var(--red-400)] focus:border-[var(--red-400)] focus:outline-[var(--red-400)]',
                        props.class,
                    )
                "
            />
        </div>
        <p
            v-if="props.error"
            class="font-mono text-[12px] text-[var(--red-400)]"
        >
            {{ props.error }}
        </p>
        <p v-else-if="props.hint" class="text-[13px] text-[var(--fg-3)]">
            {{ props.hint }}
        </p>
    </div>
</template>
