<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Card from '@/components/byb/Card.vue';
import CodeBlock from '@/components/byb/CodeBlock.vue';
import { LABEL_CLASS } from '@/components/byb/controls';
import Icon from '@/components/byb/Icon.vue';
import type { SimilarBuild } from '@/components/games/poe2/types';

defineProps<{
    buildId: string;
    gameShortName: string;
    similarBuilds: SimilarBuild[];
}>();
</script>

<template>
    <div class="flex w-full flex-col gap-4 lg:w-[280px] lg:shrink-0">
        <Card>
            <p :class="LABEL_CLASS">Generated with</p>
            <div
                class="mt-3 flex items-center gap-2 text-[13px] text-[var(--fg-2)]"
            >
                <Icon
                    name="plug-zap"
                    :size="16"
                    class="shrink-0 text-[var(--teal-400)]"
                />
                Build Your Build MCP · {{ gameShortName }}
            </div>
            <CodeBlock
                class="mt-3"
                :code="`byb://poe2/build/${buildId}`"
                filename="mcp resource"
            />
            <p class="mt-3 text-[13px] [text-wrap:pretty] text-[var(--fg-3)]">
                Numbers are simulated by the publisher, not by us.
            </p>
        </Card>

        <Card v-if="similarBuilds.length">
            <p :class="LABEL_CLASS">Similar builds</p>
            <div class="mt-3 flex flex-col gap-3">
                <Link
                    v-for="similar in similarBuilds"
                    :key="similar.id"
                    :href="similar.url"
                    class="flex flex-col gap-0.5 no-underline hover:no-underline"
                >
                    <span
                        class="text-[15px] leading-[1.5] font-semibold text-[var(--fg-1)]"
                    >
                        {{ similar.title }}
                    </span>
                    <span class="font-mono text-[12px] text-[var(--fg-3)]">
                        {{ similar.meta }}
                    </span>
                </Link>
            </div>
        </Card>
    </div>
</template>
