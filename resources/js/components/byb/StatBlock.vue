<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { LABEL_CLASS } from '@/components/byb/controls';
import Icon from '@/components/byb/Icon.vue';
import type { IconName } from '@/components/byb/icons';
import { cn } from '@/lib/utils';

type Props = {
    label: string;
    value: string | number;
    /** Rendered smaller next to the figure, e.g. `M`, `div`, `%`. */
    unit?: string;
    icon?: IconName;
    /** A token reference such as `var(--teal-400)` applied to the figure. */
    tone?: string;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    unit: undefined,
    icon: undefined,
    tone: 'var(--fg-1)',
    class: undefined,
});
</script>

<template>
    <div :class="cn('flex flex-col gap-2', props.class)">
        <span :class="LABEL_CLASS">{{ props.label }}</span>
        <span
            class="inline-flex items-baseline gap-1.5 font-mono text-[20px] leading-[1.1] font-bold"
            :style="{ color: props.tone }"
        >
            <Icon
                v-if="props.icon"
                :name="props.icon"
                :size="16"
                class="self-center"
            />
            {{ props.value }}
            <span
                v-if="props.unit"
                class="font-mono text-[12px] font-normal text-[var(--fg-3)]"
            >
                {{ props.unit }}
            </span>
        </span>
    </div>
</template>
