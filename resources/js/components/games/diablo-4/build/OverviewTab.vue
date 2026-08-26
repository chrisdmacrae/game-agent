<script setup lang="ts">
import { computed } from 'vue';
import Card from '@/components/byb/Card.vue';
import { LABEL_CLASS } from '@/components/byb/controls';
import Icon from '@/components/byb/Icon.vue';
import {
    D4_ACCENT,
    plainNumber,
    RESISTANCE_CAP,
} from '@/components/games/diablo-4/build';
import EmptyBlock from '@/components/games/diablo-4/build/EmptyBlock.vue';
import { D4_RESISTANCES } from '@/components/games/diablo-4/types';
import type { D4BuildDefinition } from '@/components/games/diablo-4/types';

const props = defineProps<{
    definition: D4BuildDefinition;
}>();

/** Six rows is what the panel is sized for; the rest lives in the notes. */
const ROW_LIMIT = 6;

const offence = computed(() =>
    (props.definition.stats?.offence ?? []).slice(0, ROW_LIMIT),
);

const defence = computed(() =>
    (props.definition.stats?.defence ?? []).slice(0, ROW_LIMIT),
);

/** Diablo IV has five elements, and armour sits beside them on the sheet. */
const resistances = computed(() => {
    const values = props.definition.resistances;

    if (!values) {
        return [];
    }

    return D4_RESISTANCES.filter(
        (element) => typeof values[element] === 'number',
    ).map((element) => {
        const value = values[element] as number;
        const underCap = value < RESISTANCE_CAP;

        return {
            element,
            value,
            underCap,
            width: `${Math.max(0, Math.min(100, Math.round((value / RESISTANCE_CAP) * 100)))}%`,
        };
    });
});

const armor = computed(() => props.definition.armor ?? null);

/** The stat calculator's breakdown, when the build has been saved through it. */
const engine = computed(() => props.definition.computed ?? null);

const engineSkills = computed(() => engine.value?.skills ?? []);

const engineAssumptions = computed(() => engine.value?.assumptions ?? []);

const seasonalPower = computed(() => props.definition.seasonal_power ?? null);

const mercenary = computed(() => {
    const merc = props.definition.mercenary;

    if (!merc || (!merc.hired && !merc.reinforcement)) {
        return null;
    }

    return merc;
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
        resistances.value.length > 0 ||
        armor.value !== null ||
        seasonalPower.value !== null ||
        mercenary.value !== null,
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

        <Card v-if="engine">
            <div class="flex items-baseline gap-3">
                <p :class="LABEL_CLASS">Engine-computed baseline</p>
                <span class="ml-auto font-mono text-[12px] text-[var(--fg-3)]">
                    {{ engineAssumptions.length }} assumption{{
                        engineAssumptions.length === 1 ? '' : 's'
                    }}
                </span>
            </div>

            <div
                v-if="engine.weapon"
                class="mt-4 flex flex-wrap items-baseline gap-x-6 gap-y-1 border-b border-[var(--border-hairline)] pb-3 font-mono text-[13px] text-[var(--fg-2)]"
            >
                <span>
                    {{ engine.weapon.item_type }} · item power
                    {{ engine.weapon.item_power }}
                </span>
                <span>
                    {{ Math.round(engine.weapon.average_hit ?? 0) }} per hit ·
                    {{ engine.weapon.attacks_per_second }} APS
                </span>
            </div>

            <div v-if="engineSkills.length" class="mt-3 flex flex-col">
                <div
                    v-for="skill in engineSkills"
                    :key="skill.skill"
                    class="flex items-baseline gap-4 border-b border-[var(--border-hairline)] py-[7px]"
                >
                    <span
                        class="flex-1 text-[13px] text-[var(--fg-2)]"
                        :data-entity="skill.skill"
                    >
                        {{ skill.skill }}
                        <span class="font-mono text-[11px] text-[var(--fg-3)]">
                            r{{ skill.rank }} ·
                            {{ skill.weapon_damage_percent }}% weapon
                        </span>
                    </span>
                    <span class="font-mono text-[14px] text-[var(--fg-1)]">
                        {{ plainNumber(skill.dps) }} dps
                    </span>
                </div>
            </div>

            <details
                v-if="engineAssumptions.length"
                class="mt-3 font-mono text-[12px] text-[var(--fg-3)]"
            >
                <summary class="cursor-pointer select-none">
                    What these numbers rest on
                </summary>
                <ul class="mt-2 flex flex-col gap-1">
                    <li v-for="line in engineAssumptions" :key="line">
                        · {{ line }}
                    </li>
                </ul>
            </details>
        </Card>

        <Card v-if="resistances.length || armor !== null">
            <div class="flex items-center">
                <p :class="LABEL_CLASS">Armour and resistances</p>
                <span class="ml-auto font-mono text-[12px] text-[var(--fg-3)]">
                    cap {{ RESISTANCE_CAP }}%
                </span>
            </div>

            <div
                v-if="armor !== null"
                class="mt-4 flex items-baseline gap-3 border-b border-[var(--border-hairline)] pb-4"
            >
                <span :class="LABEL_CLASS">Armour</span>
                <span
                    class="ml-auto font-mono text-[20px] leading-[1.1] font-bold text-[var(--fg-1)]"
                >
                    {{ plainNumber(armor) }}
                </span>
            </div>

            <div
                v-if="resistances.length"
                class="mt-4 grid grid-cols-2 gap-5 sm:grid-cols-5"
            >
                <div v-for="bar in resistances" :key="bar.element">
                    <div class="mb-1.5 flex items-baseline gap-1.5">
                        <span :class="LABEL_CLASS">{{ bar.element }}</span>
                        <span
                            class="ml-auto font-mono text-[12px]"
                            :style="{
                                color: bar.underCap
                                    ? 'var(--gold-400)'
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
                                    ? 'var(--gold-400)'
                                    : 'var(--teal-400)',
                            }"
                        />
                    </div>
                </div>
            </div>
        </Card>

        <div
            v-if="seasonalPower || mercenary"
            class="grid gap-4 md:grid-cols-2"
        >
            <Card v-if="seasonalPower">
                <p :class="LABEL_CLASS">Seasonal power</p>
                <p
                    class="mt-3 text-[15px] leading-[1.5] [text-wrap:pretty] text-[var(--fg-1)]"
                >
                    {{ seasonalPower }}
                </p>
            </Card>

            <Card v-if="mercenary">
                <p :class="LABEL_CLASS">Mercenary</p>
                <div class="mt-3 flex flex-col gap-2">
                    <div
                        v-if="mercenary.hired"
                        class="flex items-center gap-3 rounded-[var(--radius-sm)] border border-[var(--border-hairline)] bg-[var(--surface-card-hover)] px-3 py-2"
                    >
                        <span :class="LABEL_CLASS">Hired</span>
                        <span
                            class="ml-auto text-[15px] font-semibold"
                            :style="{ color: D4_ACCENT }"
                        >
                            {{ mercenary.hired }}
                        </span>
                    </div>
                    <div
                        v-if="mercenary.reinforcement"
                        class="flex items-center gap-3 rounded-[var(--radius-sm)] border border-[var(--border-hairline)] bg-[var(--surface-card-hover)] px-3 py-2"
                    >
                        <span :class="LABEL_CLASS">Reinforcement</span>
                        <span
                            class="ml-auto text-[15px] font-semibold text-[var(--fg-1)]"
                        >
                            {{ mercenary.reinforcement }}
                        </span>
                    </div>
                </div>
            </Card>
        </div>

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
                        class="w-[34px] shrink-0 font-mono text-[14px]"
                        :style="{ color: D4_ACCENT }"
                    >
                        {{ milestone.level }}
                    </span>
                    {{ milestone.text }}
                </div>
            </div>
        </Card>

        <EmptyBlock
            v-if="!hasNarrative"
            message="Armour, resistances, how it plays and the leveling milestones are not filled in yet."
        />
    </div>
</template>
