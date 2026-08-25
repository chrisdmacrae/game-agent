<script setup lang="ts">
import { computed } from 'vue';
import Card from '@/components/byb/Card.vue';
import { LABEL_CLASS } from '@/components/byb/controls';
import Icon from '@/components/byb/Icon.vue';
import EmptyBlock from '@/components/games/poe2/build/EmptyBlock.vue';
import type { Poe2BuildDefinition } from '@/components/games/poe2/types';

const props = defineProps<{
    definition: Poe2BuildDefinition;
}>();

/** Six rows is what the panel is sized for; the rest lives in the notes. */
const ROW_LIMIT = 6;

/** The cap every resistance is read against in PoE 2. */
const RESISTANCE_CAP = 75;

const offence = computed(() =>
    (props.definition.stats?.offence ?? []).slice(0, ROW_LIMIT),
);

const defence = computed(() =>
    (props.definition.stats?.defence ?? []).slice(0, ROW_LIMIT),
);

const resistances = computed(() => {
    const values = props.definition.resistances;

    if (!values) {
        return [];
    }

    return (['fire', 'cold', 'lightning', 'chaos'] as const)
        .filter((element) => typeof values[element] === 'number')
        .map((element) => {
            const value = values[element];
            const underCap = value < RESISTANCE_CAP;

            return {
                element,
                value,
                underCap,
                width: `${Math.max(0, Math.min(100, Math.round((value / RESISTANCE_CAP) * 100)))}%`,
            };
        });
});

const howItPlays = computed(() => props.definition.how_it_plays ?? []);
const worksBecause = computed(() => props.definition.works_because ?? []);
const watchOutFor = computed(() => props.definition.watch_out_for ?? []);
const milestones = computed(() => props.definition.milestones ?? []);

/** Everything below the two stat tables. Nothing there means say nothing. */
const hasNarrative = computed(
    () =>
        howItPlays.value.length > 0 ||
        worksBecause.value.length > 0 ||
        watchOutFor.value.length > 0 ||
        milestones.value.length > 0 ||
        resistances.value.length > 0,
);
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="grid gap-4 md:grid-cols-2">
            <Card>
                <p :class="LABEL_CLASS">Offence</p>
                <div v-if="offence.length" class="mt-4 flex flex-col">
                    <div
                        v-for="row in offence"
                        :key="row.label"
                        class="flex items-baseline gap-4 border-b border-[var(--border-hairline)] py-[7px]"
                    >
                        <span class="flex-1 text-[13px] text-[var(--fg-2)]">
                            {{ row.label }}
                        </span>
                        <span class="font-mono text-[14px] text-[var(--fg-1)]">
                            {{ row.value }}
                        </span>
                    </div>
                </div>
                <EmptyBlock
                    v-else
                    class="mt-4"
                    message="No offence rows yet."
                />
            </Card>

            <Card>
                <p :class="LABEL_CLASS">Defence</p>
                <div v-if="defence.length" class="mt-4 flex flex-col">
                    <div
                        v-for="row in defence"
                        :key="row.label"
                        class="flex items-baseline gap-4 border-b border-[var(--border-hairline)] py-[7px]"
                    >
                        <span class="flex-1 text-[13px] text-[var(--fg-2)]">
                            {{ row.label }}
                        </span>
                        <span class="font-mono text-[14px] text-[var(--fg-1)]">
                            {{ row.value }}
                        </span>
                    </div>
                </div>
                <EmptyBlock
                    v-else
                    class="mt-4"
                    message="No defence rows yet."
                />
            </Card>
        </div>

        <Card v-if="resistances.length">
            <div class="flex items-center">
                <p :class="LABEL_CLASS">Resistances</p>
                <span class="ml-auto font-mono text-[12px] text-[var(--fg-3)]">
                    cap {{ RESISTANCE_CAP }}%
                </span>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-5 sm:grid-cols-4">
                <div v-for="bar in resistances" :key="bar.element">
                    <div class="mb-1.5 flex items-baseline gap-1.5">
                        <span :class="LABEL_CLASS">{{ bar.element }}</span>
                        <span
                            class="ml-auto font-mono text-[12px]"
                            :style="{
                                color: bar.underCap
                                    ? 'var(--red-400)'
                                    : 'var(--fg-1)',
                            }"
                        >
                            {{ bar.value }}%
                        </span>
                    </div>
                    <div
                        class="h-1.5 overflow-hidden rounded-[3px] bg-[var(--ink-700)]"
                    >
                        <div
                            class="h-full"
                            :style="{
                                width: bar.width,
                                background: bar.underCap
                                    ? 'var(--red-400)'
                                    : 'var(--teal-400)',
                            }"
                        />
                    </div>
                </div>
            </div>
        </Card>

        <Card v-if="howItPlays.length">
            <p :class="LABEL_CLASS">How it plays</p>
            <div class="mt-4 flex flex-col gap-3">
                <div
                    v-for="line in howItPlays"
                    :key="line"
                    class="flex gap-3 text-[15px] [text-wrap:pretty] text-[var(--fg-2)]"
                >
                    <span
                        class="w-[3px] shrink-0 rounded-[2px] bg-[var(--ink-600)]"
                    />
                    {{ line }}
                </div>
            </div>
        </Card>

        <div
            v-if="worksBecause.length || watchOutFor.length"
            class="grid gap-4 md:grid-cols-2"
        >
            <Card v-if="worksBecause.length">
                <p :class="LABEL_CLASS">Works because</p>
                <div class="mt-4 flex flex-col gap-3">
                    <div
                        v-for="line in worksBecause"
                        :key="line"
                        class="flex gap-3 text-[13px] [text-wrap:pretty] text-[var(--fg-2)]"
                    >
                        <Icon
                            name="check"
                            :size="13"
                            class="mt-0.5 shrink-0 text-[var(--teal-400)]"
                        />
                        {{ line }}
                    </div>
                </div>
            </Card>
            <Card v-if="watchOutFor.length">
                <p :class="LABEL_CLASS">Watch out for</p>
                <div class="mt-4 flex flex-col gap-3">
                    <div
                        v-for="line in watchOutFor"
                        :key="line"
                        class="flex gap-3 text-[13px] [text-wrap:pretty] text-[var(--fg-2)]"
                    >
                        <Icon
                            name="triangle-alert"
                            :size="13"
                            class="mt-0.5 shrink-0 text-[var(--gold-400)]"
                        />
                        {{ line }}
                    </div>
                </div>
            </Card>
        </div>

        <Card v-if="milestones.length">
            <p :class="LABEL_CLASS">Leveling milestones</p>
            <div class="mt-4 flex flex-col gap-3">
                <div
                    v-for="milestone in milestones"
                    :key="`${milestone.level}-${milestone.text}`"
                    class="flex gap-4 text-[13px] text-[var(--fg-2)]"
                >
                    <span
                        class="w-[34px] shrink-0 font-mono text-[14px] text-[var(--teal-400)]"
                    >
                        {{ milestone.level }}
                    </span>
                    {{ milestone.text }}
                </div>
            </div>
        </Card>

        <EmptyBlock
            v-if="!hasNarrative"
            message="Resistances, how it plays and the leveling milestones are not filled in yet."
        />
    </div>
</template>
