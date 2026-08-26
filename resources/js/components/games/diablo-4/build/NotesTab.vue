<script setup lang="ts">
import Card from '@/components/byb/Card.vue';
import { LABEL_CLASS } from '@/components/byb/controls';
import EmptyBlock from '@/components/games/diablo-4/build/EmptyBlock.vue';

defineProps<{
    guideHtml: string | null;
}>();
</script>

<template>
    <Card>
        <p :class="LABEL_CLASS">Notes</p>
        <!-- eslint-disable-next-line vue/no-v-html — server-rendered markdown, HTML escaped -->
        <div
            v-if="guideHtml"
            class="guide-content mt-4 text-[15px] leading-[1.6] [text-wrap:pretty] text-[var(--fg-2)]"
            v-html="guideHtml"
        />
        <EmptyBlock
            v-else
            class="mt-4"
            message="The publisher has not written notes for this build yet."
        />
    </Card>
</template>

<style scoped>
.guide-content :deep(h1),
.guide-content :deep(h2),
.guide-content :deep(h3) {
    color: var(--fg-1);
    font-family: var(--font-display);
    font-weight: 700;
    letter-spacing: var(--track-display);
    margin: var(--sp-7) 0 var(--sp-3);
}

.guide-content :deep(h1) {
    font-size: 22px;
    line-height: 1.2;
}

.guide-content :deep(h2) {
    font-size: 18px;
    line-height: 1.28;
}

.guide-content :deep(h3) {
    font-size: 15px;
    line-height: 1.4;
}

.guide-content :deep(p) {
    margin: var(--sp-4) 0;
}

.guide-content :deep(ul),
.guide-content :deep(ol) {
    margin: var(--sp-4) 0;
    padding-left: var(--sp-6);
    list-style: disc;
}

.guide-content :deep(ol) {
    list-style: decimal;
}

.guide-content :deep(li) {
    margin: var(--sp-2) 0;
}

.guide-content :deep(strong) {
    color: var(--fg-1);
}

.guide-content :deep(code) {
    background: var(--ink-950);
    border: 1px solid var(--border-hairline);
    border-radius: var(--radius-xs);
    padding: 1px 5px;
    font: var(--type-mono-s);
    color: var(--teal-300);
}

.guide-content :deep(pre) {
    background: var(--ink-950);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-sm);
    padding: var(--sp-4);
    overflow-x: auto;
    margin: var(--sp-5) 0;
}

.guide-content :deep(table) {
    width: 100%;
    margin: var(--sp-5) 0;
    border-collapse: collapse;
    font: var(--type-mono-s);
}

.guide-content :deep(th),
.guide-content :deep(td) {
    border: 1px solid var(--border-subtle);
    padding: var(--sp-3) var(--sp-4);
    text-align: left;
}

.guide-content :deep(th) {
    color: var(--fg-1);
    font: var(--type-label);
    letter-spacing: var(--track-label);
    text-transform: uppercase;
}

.guide-content :deep(a) {
    color: var(--text-link);
    text-decoration: underline;
}

.guide-content :deep(a:hover) {
    color: var(--text-link-hover);
}

.guide-content :deep(blockquote) {
    border-left: 2px solid var(--border-accent);
    padding-left: var(--sp-5);
    color: var(--fg-3);
    margin: var(--sp-5) 0;
}
</style>
