<script setup lang="ts">
import { RadioGroupRoot } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { LABEL_CLASS } from '@/components/byb/controls';
import { cn } from '@/lib/utils';

type Props = {
    label?: string;
    orientation?: 'vertical' | 'horizontal';
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    label: undefined,
    orientation: 'vertical',
    class: undefined,
});

const model = defineModel<string | number | null>();
</script>

<template>
    <div class="flex flex-col gap-3">
        <span v-if="props.label" :class="LABEL_CLASS">{{ props.label }}</span>
        <RadioGroupRoot
            v-model="model"
            :orientation="props.orientation"
            :class="
                cn(
                    'flex gap-2.5',
                    props.orientation === 'vertical'
                        ? 'flex-col'
                        : 'flex-row flex-wrap items-center gap-5',
                    props.class,
                )
            "
        >
            <slot />
        </RadioGroupRoot>
    </div>
</template>
