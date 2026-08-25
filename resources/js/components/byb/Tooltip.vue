<script setup lang="ts">
import {
    TooltipArrow,
    TooltipContent,
    TooltipPortal,
    TooltipProvider,
    TooltipRoot,
    TooltipTrigger,
} from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

type Props = {
    text: string;
    side?: 'top' | 'right' | 'bottom' | 'left';
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    side: 'top',
    class: undefined,
});
</script>

<template>
    <TooltipProvider :delay-duration="140">
        <TooltipRoot>
            <TooltipTrigger as-child>
                <slot />
            </TooltipTrigger>
            <TooltipPortal>
                <TooltipContent
                    :side="props.side"
                    :side-offset="6"
                    :class="
                        cn(
                            'z-50 rounded-[var(--radius-sm)] border border-[var(--border-subtle)] bg-[var(--surface-raised)] px-2.5 py-1.5 font-mono text-[12px] text-[var(--fg-1)] [box-shadow:var(--shadow-2)] data-[state=delayed-open]:animate-in data-[state=delayed-open]:fade-in-0',
                            props.class,
                        )
                    "
                >
                    {{ props.text }}
                    <TooltipArrow
                        class="fill-[var(--surface-raised)]"
                        :width="8"
                    />
                </TooltipContent>
            </TooltipPortal>
        </TooltipRoot>
    </TooltipProvider>
</template>
