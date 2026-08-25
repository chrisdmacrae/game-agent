<script setup lang="ts">
import { TabsList, TabsRoot, TabsTrigger } from 'reka-ui';
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import Icon from '@/components/byb/Icon.vue';
import type { IconName } from '@/components/byb/icons';
import { cn } from '@/lib/utils';

type TabItem = {
    value: string;
    label: string;
    icon?: IconName;
};

type Props = {
    tabs: TabItem[];
    variant?: 'underline' | 'segmented';
    size?: 'sm' | 'md';
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    variant: 'underline',
    size: 'md',
    class: undefined,
});

const model = defineModel<string>({ required: true });

const iconSize = computed(() => (props.size === 'sm' ? 13 : 16));

const listClass = computed(() =>
    props.variant === 'segmented'
        ? 'inline-flex w-full gap-1 rounded-[var(--radius-sm)] border border-[var(--border-subtle)] bg-[var(--surface-input)] p-1'
        : 'flex gap-6 border-b border-[var(--border-subtle)]',
);

const triggerClass = computed(() =>
    props.variant === 'segmented'
        ? 'flex flex-1 items-center justify-center gap-2 rounded-[var(--radius-xs)] px-3 text-[var(--fg-3)] outline-none [transition:var(--transition-control)] hover:text-[var(--fg-1)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--focus-ring)] data-[state=active]:bg-[var(--surface-accent-soft)] data-[state=active]:text-[var(--teal-400)]'
        : '-mb-px flex items-center gap-2 border-b-2 border-transparent px-0.5 text-[var(--fg-3)] outline-none [transition:var(--transition-control)] hover:text-[var(--fg-1)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--focus-ring)] data-[state=active]:border-[var(--teal-400)] data-[state=active]:text-[var(--fg-1)]',
);

const triggerSizeClass = computed(() =>
    props.size === 'sm'
        ? 'h-[26px] text-[11px] tracking-[0.14em]'
        : 'h-[38px] text-[12px] tracking-[0.12em]',
);
</script>

<template>
    <TabsRoot v-model="model" :class="cn('w-full', props.class)">
        <TabsList :class="listClass">
            <TabsTrigger
                v-for="tab in props.tabs"
                :key="tab.value"
                :value="tab.value"
                :class="
                    cn(
                        'font-mono font-bold whitespace-nowrap uppercase',
                        triggerClass,
                        triggerSizeClass,
                    )
                "
            >
                <Icon v-if="tab.icon" :name="tab.icon" :size="iconSize" />
                {{ tab.label }}
            </TabsTrigger>
        </TabsList>
        <slot />
    </TabsRoot>
</template>
