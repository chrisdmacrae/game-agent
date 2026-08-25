<script setup lang="ts">
import { Toaster as Sonner } from 'vue-sonner';
import type { ToasterProps } from 'vue-sonner';
import Icon from '@/components/byb/Icon.vue';
import { cn } from '@/lib/utils';

import 'vue-sonner/style.css';

const props = defineProps<ToasterProps>();
</script>

<template>
    <Sonner
        v-bind="props"
        :class="cn('toaster group', props.class)"
        position="bottom-right"
        :offset="24"
        :style="{
            '--normal-bg': 'var(--surface-raised)',
            '--normal-text': 'var(--fg-1)',
            '--normal-border': 'var(--border-subtle)',
            '--border-radius': 'var(--radius-md)',
            '--success-bg': 'var(--surface-raised)',
            '--success-text': 'var(--teal-400)',
            '--success-border': 'var(--border-subtle)',
            '--info-bg': 'var(--surface-raised)',
            '--info-text': 'var(--blue-400)',
            '--info-border': 'var(--border-subtle)',
            '--warning-bg': 'var(--surface-raised)',
            '--warning-text': 'var(--gold-400)',
            '--warning-border': 'var(--border-subtle)',
            '--error-bg': 'var(--surface-raised)',
            '--error-text': 'var(--red-400)',
            '--error-border': 'var(--border-subtle)',
            '--width': '340px',
        }"
        :toast-options="{
            class: 'byb-toast',
            classes: {
                toast: '[box-shadow:var(--shadow-2)]',
                title: 'text-[15px] font-semibold text-[var(--fg-1)]',
                description:
                    'font-mono text-[12px] text-[var(--fg-3)] leading-[1.45]',
                actionButton:
                    'font-mono text-[11px] font-bold uppercase tracking-[0.14em]',
            },
        }"
    >
        <template #success-icon>
            <Icon name="circle-check" :size="16" />
        </template>
        <template #info-icon>
            <Icon name="info" :size="16" />
        </template>
        <template #warning-icon>
            <Icon name="triangle-alert" :size="16" />
        </template>
        <template #error-icon>
            <Icon name="octagon-x" :size="16" />
        </template>
        <template #loading-icon>
            <Icon name="loader-circle" :size="16" class="animate-spin" />
        </template>
        <template #close-icon>
            <Icon name="x" :size="13" />
        </template>
    </Sonner>
</template>
