<script setup lang="ts">
import {
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
    DialogTrigger,
} from 'reka-ui';
import { computed } from 'vue';
import type { CSSProperties, HTMLAttributes } from 'vue';
import { LABEL_CLASS } from '@/components/byb/controls';
import Icon from '@/components/byb/Icon.vue';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    /** Mono uppercase kicker above the title, e.g. "MCP server". */
    eyebrow?: string;
    description?: string;
    /** Dialog width in pixels; the content never exceeds the viewport. */
    width?: number;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    eyebrow: undefined,
    description: undefined,
    width: 560,
    class: undefined,
});

const open = defineModel<boolean>('open', { default: false });

const style = computed<CSSProperties>(() => ({
    width: `min(${props.width}px, calc(100vw - 2 * var(--layout-gutter)))`,
}));
</script>

<template>
    <DialogRoot v-model:open="open">
        <DialogTrigger v-if="$slots.trigger" as-child>
            <slot name="trigger" />
        </DialogTrigger>
        <DialogPortal>
            <DialogOverlay
                class="fixed inset-0 z-50 bg-[var(--surface-overlay)] [backdrop-filter:var(--blur-glass)] data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0"
            />
            <DialogContent
                :style="style"
                :class="
                    cn(
                        'fixed top-1/2 left-1/2 z-50 max-h-[calc(100vh-2*var(--layout-gutter))] -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-[var(--radius-lg)] border border-[var(--border-subtle)] bg-[var(--surface-card)] p-6 [box-shadow:var(--shadow-3)] outline-none data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0',
                        props.class,
                    )
                "
            >
                <div class="flex items-start gap-4">
                    <div class="flex-1">
                        <p
                            v-if="props.eyebrow"
                            :class="cn(LABEL_CLASS, 'mb-2.5')"
                        >
                            {{ props.eyebrow }}
                        </p>
                        <DialogTitle
                            class="font-display text-[22px] leading-[1.2] font-bold tracking-[-0.02em] text-[var(--fg-1)]"
                        >
                            {{ props.title }}
                        </DialogTitle>
                        <DialogDescription
                            v-if="props.description"
                            class="mt-2 text-[15px] text-[var(--fg-2)]"
                        >
                            {{ props.description }}
                        </DialogDescription>
                    </div>
                    <DialogClose
                        aria-label="Close"
                        class="inline-flex size-[var(--control-h-sm)] shrink-0 items-center justify-center rounded-[var(--radius-sm)] border border-[var(--border-subtle)] text-[var(--fg-3)] outline-none [transition:var(--transition-control)] hover:border-[var(--border-strong)] hover:bg-[var(--surface-card-hover)] hover:text-[var(--fg-1)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--focus-ring)]"
                    >
                        <Icon name="x" :size="13" />
                    </DialogClose>
                </div>

                <div class="mt-5">
                    <slot />
                </div>

                <div
                    v-if="$slots.footer"
                    class="mt-6 flex items-center justify-end gap-3 border-t border-[var(--border-hairline)] pt-5"
                >
                    <slot name="footer" />
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
