<script setup lang="ts">
import { computed } from 'vue';
import Card from '@/components/byb/Card.vue';
import { LABEL_CLASS } from '@/components/byb/controls';
import {
    gemColor,
    skillMeta,
    skillSupports,
} from '@/components/games/poe2/build';
import EmptyBlock from '@/components/games/poe2/build/EmptyBlock.vue';
import type {
    Poe2BuildDefinition,
    Poe2Entity,
} from '@/components/games/poe2/types';

const props = defineProps<{
    definition: Poe2BuildDefinition;
    entityFor: (name: string) => Poe2Entity | null;
}>();

const panels = computed(() =>
    (props.definition.skills ?? []).map((skill) => ({
        skill,
        supports: skillSupports(skill),
        meta: skillMeta(skill),
        swatch: gemColor(props.entityFor(skill.gem)),
        icon: props.entityFor(skill.gem)?.icon ?? null,
    })),
);
</script>

<template>
    <div class="flex flex-col gap-4">
        <div v-if="definition.spirit_available" class="flex items-center gap-3">
            <span :class="LABEL_CLASS">Spirit available</span>
            <span class="font-mono text-[14px] text-[var(--fg-1)]">
                {{ definition.spirit_available }}
            </span>
        </div>

        <EmptyBlock
            v-if="!panels.length"
            message="No skill setups on this build yet."
        />

        <Card v-for="panel in panels" :key="panel.skill.gem" padding="0">
            <div
                class="flex flex-wrap items-start gap-3 border-b border-[var(--border-hairline)] p-4"
            >
                <span
                    class="flex size-[34px] shrink-0 items-center justify-center overflow-hidden rounded-[var(--radius-xs)] border border-[var(--border-subtle)] bg-[var(--surface-card-hover)]"
                >
                    <img
                        v-if="panel.icon"
                        :src="panel.icon"
                        :alt="panel.skill.gem"
                        class="max-h-[30px] object-contain"
                    />
                    <span
                        v-else
                        class="size-2.5 rounded-[2px]"
                        :style="{ background: panel.swatch }"
                    />
                </span>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="entity-ref text-[18px] leading-[1.28] font-semibold text-[var(--fg-1)]"
                            :data-entity="panel.skill.gem"
                        >
                            {{ panel.skill.gem }}
                        </span>
                        <span
                            v-for="tag in panel.skill.tags ?? []"
                            :key="tag"
                            class="rounded-[var(--radius-xs)] border border-[var(--border-subtle)] px-1.5 py-0.5 font-mono text-[11px] leading-none font-bold tracking-[0.14em] text-[var(--fg-3)] uppercase"
                        >
                            {{ tag }}
                        </span>
                    </div>
                    <p
                        v-if="panel.skill.reported"
                        class="mt-1 font-mono text-[12px] text-[var(--fg-3)]"
                    >
                        {{ panel.skill.reported }}
                    </p>
                </div>

                <div class="text-right">
                    <p
                        v-if="panel.meta"
                        class="font-mono text-[12px] text-[var(--fg-2)]"
                    >
                        {{ panel.meta }}
                    </p>
                    <p
                        v-if="panel.skill.role"
                        class="mt-0.5 font-mono text-[12px] text-[var(--fg-3)]"
                    >
                        {{ panel.skill.role }}
                    </p>
                </div>
            </div>

            <div class="p-4">
                <div class="flex items-center">
                    <p :class="LABEL_CLASS">Support gems</p>
                    <span
                        class="ml-auto font-mono text-[12px] text-[var(--fg-3)]"
                    >
                        {{
                            panel.supports.length === 1
                                ? '1 support'
                                : `${panel.supports.length} supports`
                        }}
                    </span>
                </div>
                <div
                    v-if="panel.supports.length"
                    class="mt-3 grid gap-2 sm:grid-cols-2"
                >
                    <div
                        v-for="support in panel.supports"
                        :key="support.name"
                        class="flex items-center gap-3 rounded-[var(--radius-sm)] border border-[var(--border-hairline)] bg-[var(--surface-card-hover)] px-3 py-2"
                    >
                        <span
                            class="size-1.5 shrink-0 rounded-full"
                            :style="{ background: panel.swatch }"
                        />
                        <div class="min-w-0">
                            <p
                                class="entity-ref text-[15px] leading-[1.5] font-semibold text-[var(--fg-1)]"
                                :data-entity="support.name"
                            >
                                {{ support.name }}
                            </p>
                            <p
                                v-if="support.effect"
                                class="font-mono text-[12px] text-[var(--fg-3)]"
                            >
                                {{ support.effect }}
                            </p>
                        </div>
                    </div>
                </div>
                <EmptyBlock
                    v-else
                    class="mt-3"
                    message="No support gems linked yet."
                />
            </div>
        </Card>
    </div>
</template>
