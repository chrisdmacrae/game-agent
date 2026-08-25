<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { bybIcons } from '@/components/byb/icons';
import type { IconName, IconSize } from '@/components/byb/icons';
import { cn } from '@/lib/utils';

type Props = {
    name: IconName;
    size?: IconSize;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    size: 16,
    class: undefined,
});
</script>

<template>
    <component
        :is="bybIcons[props.name]"
        :size="props.size"
        :stroke-width="1.5"
        color="currentColor"
        :class="cn('shrink-0', props.class)"
        aria-hidden="true"
    />
</template>
