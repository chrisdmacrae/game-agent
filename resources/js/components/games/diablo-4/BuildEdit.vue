<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Button from '@/components/byb/Button.vue';
import Card from '@/components/byb/Card.vue';
import { LABEL_CLASS } from '@/components/byb/controls';
import Icon from '@/components/byb/Icon.vue';
import Input from '@/components/byb/Input.vue';
import Radio from '@/components/byb/Radio.vue';
import RadioGroup from '@/components/byb/RadioGroup.vue';
import Select from '@/components/byb/Select.vue';
import Switch from '@/components/byb/Switch.vue';
import Textarea from '@/components/byb/Textarea.vue';
import { stageValue } from '@/components/games/diablo-4/build';
import GearCard from '@/components/games/diablo-4/edit/GearCard.vue';
import NotesCard from '@/components/games/diablo-4/edit/NotesCard.vue';
import ParagonCard from '@/components/games/diablo-4/edit/ParagonCard.vue';
import SkillsCard from '@/components/games/diablo-4/edit/SkillsCard.vue';
import {
    D4_CONTENT_TIERS,
    D4_GEAR_SLOTS,
    D4_RESISTANCES,
} from '@/components/games/diablo-4/types';
import type {
    D4BuildDefinition,
    D4BuildEditProps,
    D4EquippedSkill,
    D4Gear,
    D4GearItem,
    D4Milestone,
    D4ParagonEntry,
    D4Resistance,
    D4SkillPoint,
} from '@/components/games/diablo-4/types';
import { cn } from '@/lib/utils';
import { update } from '@/routes/games/builds';

/**
 * Full edit mode for a Diablo IV build. The assistant writes a partial payload;
 * this is where a human checks the numbers before it goes live.
 */
const props = defineProps<D4BuildEditProps>();

const definition = props.build.definition;

/**
 * The form's own shape. Everything a control binds to is present and non
 * optional here — a `null` is a field nobody filled in, and `transform` turns
 * the empty ones back into nothing before the payload is sent.
 */
type EditableBuild = {
    class: string | null;
    level: number | null;
    armor: number | null;
    resistances: Record<D4Resistance, number | null>;
    content_tier: string | null;
    stage: string | null;
    tier: string | null;
    dps: number | null;
    ehp: number | null;
    hardcore_viable: boolean;
    equipped_skills: D4EquippedSkill[];
    skill_points: D4SkillPoint[];
    paragon: D4ParagonEntry[];
    gear: D4Gear;
    seasonal_power: string;
    mercenary: { hired: string; reinforcement: string };
    milestones: D4Milestone[];
    stats: D4BuildDefinition['stats'];
    how_it_plays: string[];
    works_because: string[];
    watch_out_for: string[];
};

/**
 * Gear is a map, so every slot starts as an object even when it is empty —
 * the slot form binds straight to it.
 */
function initialGear(): D4Gear {
    const gear: D4Gear = {
        weapons: (definition.gear?.weapons ?? []).map((weapon) => ({
            ...weapon,
        })),
    };

    for (const slot of D4_GEAR_SLOTS) {
        gear[slot] = { ...(definition.gear?.[slot] ?? {}) };
    }

    return gear;
}

function initialResistances(): Record<D4Resistance, number | null> {
    const values = {} as Record<D4Resistance, number | null>;

    for (const element of D4_RESISTANCES) {
        values[element] = definition.resistances?.[element] ?? null;
    }

    return values;
}

const initialBuild: EditableBuild = {
    class: definition.class ?? null,
    level: definition.level ?? null,
    armor: definition.armor ?? null,
    resistances: initialResistances(),
    content_tier: definition.content_tier ?? null,
    // Older payloads carry the stage as `content_tier`; show what the build
    // page shows rather than an empty select.
    stage: stageValue(definition),
    tier: definition.tier ?? null,
    dps: definition.dps ?? null,
    ehp: definition.ehp ?? null,
    hardcore_viable: definition.hardcore_viable ?? false,
    equipped_skills: (definition.equipped_skills ?? []).map((skill) => ({
        ...skill,
        modifiers: [...(skill.modifiers ?? [])],
    })),
    skill_points: (definition.skill_points ?? []).map((entry) => ({
        ...entry,
    })),
    paragon: (definition.paragon ?? []).map((entry) => ({
        ...entry,
        rotation: entry.rotation ?? 0,
        notables: [...(entry.notables ?? [])],
    })),
    gear: initialGear(),
    seasonal_power: definition.seasonal_power ?? '',
    mercenary: {
        hired: definition.mercenary?.hired ?? '',
        reinforcement: definition.mercenary?.reinforcement ?? '',
    },
    milestones: (definition.milestones ?? []).map((milestone) => ({
        ...milestone,
    })),
    stats: definition.stats ?? null,
    how_it_plays: definition.how_it_plays ?? [],
    works_because: definition.works_because ?? [],
    watch_out_for: definition.watch_out_for ?? [],
};

const form = useForm({
    name: props.build.name,
    summary: props.build.summary ?? '',
    guide_markdown: props.build.guide_markdown ?? '',
    visibility: props.build.visibility,
    build: initialBuild,
});

function numberOrNull(value: unknown): number | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const parsed = Number(value);

    return Number.isFinite(parsed) ? parsed : null;
}

function textOrNull(value: unknown): string | null {
    const text = typeof value === 'string' ? value.trim() : '';

    return text === '' ? null : text;
}

/** An item nobody filled in is sent as nothing, not as a row of empty strings. */
function cleanItem(item: D4GearItem): D4GearItem | null {
    const cleaned: D4GearItem = {
        name: textOrNull(item.name),
        item_type: textOrNull(item.item_type),
        rarity: textOrNull(item.rarity),
        aspect: textOrNull(item.aspect),
        affixes: (item.affixes ?? []).filter((affix) => affix.trim() !== ''),
        greater_affixes: numberOrNull(item.greater_affixes),
        masterwork_level: numberOrNull(item.masterwork_level),
        runes: (item.runes ?? []).filter((rune) => rune.trim() !== ''),
        tempered: (item.tempered ?? [])
            .filter((temper) => textOrNull(temper.affix) !== null)
            .map((temper) => ({
                affix: temper.affix.trim(),
                tier: numberOrNull(temper.tier),
            })),
    };

    const filled =
        cleaned.name !== null ||
        cleaned.item_type !== null ||
        cleaned.aspect !== null ||
        (cleaned.affixes?.length ?? 0) > 0 ||
        (cleaned.runes?.length ?? 0) > 0 ||
        (cleaned.tempered?.length ?? 0) > 0 ||
        Boolean(cleaned.masterwork_level);

    return filled ? cleaned : null;
}

/** Number inputs hand back empty strings; the API wants nulls. */
form.transform((data) => {
    const gear: Record<string, unknown> = {};

    for (const slot of D4_GEAR_SLOTS) {
        const cleaned = cleanItem(data.build.gear[slot] ?? {});

        if (cleaned !== null) {
            gear[slot] = cleaned;
        }
    }

    gear.weapons = (data.build.gear.weapons ?? [])
        .map((weapon) => cleanItem(weapon))
        .filter((weapon): weapon is D4GearItem => weapon !== null);

    const resistanceValues: Record<string, number> = {};

    for (const element of D4_RESISTANCES) {
        const value = numberOrNull(data.build.resistances[element]);

        if (value !== null) {
            resistanceValues[element] = value;
        }
    }

    return {
        ...data,
        summary: textOrNull(data.summary),
        guide_markdown: textOrNull(data.guide_markdown),
        build: {
            ...data.build,
            level: numberOrNull(data.build.level),
            armor: numberOrNull(data.build.armor),
            dps: numberOrNull(data.build.dps),
            ehp: numberOrNull(data.build.ehp),
            resistances: resistanceValues,
            seasonal_power: textOrNull(data.build.seasonal_power),
            mercenary: {
                hired: textOrNull(data.build.mercenary.hired),
                reinforcement: textOrNull(data.build.mercenary.reinforcement),
            },
            equipped_skills: data.build.equipped_skills
                .filter((skill) => textOrNull(skill.skill) !== null)
                .map((skill) => ({
                    ...skill,
                    skill: skill.skill.trim(),
                    rank: numberOrNull(skill.rank),
                    role: textOrNull(skill.role),
                    reported: textOrNull(skill.reported),
                    modifiers: (skill.modifiers ?? []).filter(
                        (modifier) => modifier.trim() !== '',
                    ),
                })),
            paragon: data.build.paragon
                .filter((entry) => textOrNull(entry.board) !== null)
                .map((entry) => ({
                    ...entry,
                    board: entry.board.trim(),
                    rotation: numberOrNull(entry.rotation) ?? 0,
                    glyph: textOrNull(entry.glyph),
                    glyph_level: numberOrNull(entry.glyph_level),
                    notables: (entry.notables ?? []).filter(
                        (notable) => notable.trim() !== '',
                    ),
                })),
            gear,
            milestones: data.build.milestones.map((milestone) => ({
                ...milestone,
                level: numberOrNull(milestone.level) ?? 1,
            })),
        },
    };
});

/** Errors come back keyed by the dotted payload path, e.g. `build.gear.helm.name`. */
const errors = computed(() => form.errors as unknown as Record<string, string>);

const stageOptions = computed(() =>
    props.options.stages.map((stage) => ({
        value: stage,
        label: stage.charAt(0).toUpperCase() + stage.slice(1),
    })),
);

const contentTierOptions = D4_CONTENT_TIERS.map((tier) => ({
    value: tier,
    label:
        tier === 'pit_push'
            ? 'Pit push'
            : tier.charAt(0).toUpperCase() + tier.slice(1),
}));

const resistanceElements = D4_RESISTANCES as readonly D4Resistance[];

/**
 * Re-run the pre-flight in the browser so the list reacts while you type. The
 * server runs the same checks on save and its verdict is the one that counts.
 */
const checklist = computed(() =>
    props.checklist.map((check) => {
        const optimistic = liveCheck(check.key);

        if (optimistic === null) {
            return check;
        }

        return optimistic
            ? { ...check, passed: true, detail: null }
            : { ...check, passed: false, detail: check.detail };
    }),
);

function liveCheck(key: string): boolean | null {
    if (key === 'stats') {
        const rows = form.build.stats;

        return (
            (form.build.dps !== null && form.build.ehp !== null) ||
            ((rows?.offence?.length ?? 0) > 0 &&
                (rows?.defence?.length ?? 0) > 0)
        );
    }

    if (key === 'skills') {
        const named = (form.build.equipped_skills as D4EquippedSkill[]).filter(
            (skill) => textOrNull(skill.skill) !== null,
        );

        return named.length > 0 && named.length <= 6;
    }

    if (key === 'paragon') {
        const boards = (form.build.paragon as D4ParagonEntry[]).filter(
            (entry) => textOrNull(entry.board) !== null,
        );
        const level = numberOrNull(form.build.level);

        return boards.length > 0 || (level !== null && level < 60);
    }

    return null;
}

const blocked = computed(
    () =>
        form.visibility === 'public' &&
        checklist.value.some((check) => !check.passed),
);

function submit(): void {
    form.patch(update.url([props.game.slug, props.build.id]), {
        preserveScroll: true,
    });
}
</script>

<template>
    <form class="pb-12" @submit.prevent="submit">
        <div
            class="sticky top-[var(--layout-topbar)] z-20 -mx-[var(--layout-gutter)] border-b border-[var(--border-subtle)] bg-[var(--overlay-glass)] px-[var(--layout-gutter)] py-4 [backdrop-filter:var(--blur-glass)]"
        >
            <div
                class="mx-auto flex max-w-[960px] flex-wrap items-center gap-4"
            >
                <div class="min-w-0 flex-1">
                    <p :class="cn(LABEL_CLASS, 'text-[var(--red-400)]')">
                        Editing
                    </p>
                    <p
                        class="mt-1 truncate text-[18px] leading-[1.28] font-semibold text-[var(--fg-1)]"
                    >
                        {{ form.name || 'Untitled build' }}
                    </p>
                </div>
                <span
                    class="font-mono text-[12px]"
                    :style="{
                        color: form.isDirty ? 'var(--gold-400)' : 'var(--fg-3)',
                    }"
                >
                    {{ form.isDirty ? 'unsaved changes' : 'no changes' }}
                </span>
                <Button variant="ghost" as-child>
                    <Link :href="build.url">Cancel</Link>
                </Button>
                <Button
                    type="submit"
                    variant="primary"
                    icon="check"
                    :disabled="form.processing"
                >
                    Save changes
                </Button>
            </div>
        </div>

        <div class="mx-auto flex max-w-[960px] flex-col gap-4 pt-8">
            <div
                class="flex items-start gap-3 rounded-[var(--radius-sm)] border border-l-2 border-[var(--border-subtle)] border-l-[var(--red-400)] bg-[var(--surface-card-hover)] p-4"
            >
                <Icon
                    name="zap"
                    :size="16"
                    class="mt-0.5 shrink-0 text-[var(--red-400)]"
                />
                <p class="text-[13px] [text-wrap:pretty] text-[var(--fg-2)]">
                    Your assistant filled most of this in. Check the numbers
                    before it goes live — anything you change here overrides the
                    generated values.
                </p>
            </div>

            <Card padding="var(--sp-7)">
                <p :class="LABEL_CLASS">Identity</p>
                <div class="mt-4 flex flex-col gap-4">
                    <Input
                        v-model="form.name"
                        label="Build title"
                        :error="form.errors.name"
                    />
                    <Textarea
                        v-model="form.summary"
                        label="Summary"
                        :rows="3"
                        :maxlength="240"
                        hint="Two sentences max."
                        :error="form.errors.summary"
                    />
                </div>
            </Card>

            <Card padding="var(--sp-7)">
                <p :class="LABEL_CLASS">Classification</p>
                <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <Select
                        v-model="form.build.class"
                        label="Class"
                        :options="options.classes"
                        placeholder="Pick a class"
                    />
                    <!-- No ascendancy select: a Diablo IV character has no
                         second class layer, GameReference sends the list empty
                         for this game, and an empty select is a question the
                         game does not ask. -->
                    <Input
                        v-model="form.build.level"
                        label="Level"
                        type="number"
                        mono
                        hint="Capped at 70."
                        :error="errors['build.level']"
                    />
                    <Select
                        v-model="form.build.stage"
                        label="Game stage"
                        :options="stageOptions"
                        placeholder="Pick a stage"
                    />
                    <Select
                        v-model="form.build.tier"
                        label="Tier"
                        :options="options.tiers"
                        placeholder="Pick a tier"
                    />
                    <Select
                        v-model="form.build.content_tier"
                        label="Content"
                        :options="contentTierOptions"
                        placeholder="Pick the content"
                    />
                </div>
                <Switch
                    v-model="form.build.hardcore_viable"
                    class="mt-4"
                    label="Hardcore viable — survives without a town portal out"
                />
            </Card>

            <Card padding="var(--sp-7)">
                <p :class="LABEL_CLASS">Stats</p>
                <div class="mt-4 grid gap-4 md:grid-cols-3">
                    <Input
                        v-model="form.build.dps"
                        label="DPS"
                        type="number"
                        mono
                        hint="As the in-game sheet reports it."
                        :error="errors['build.dps']"
                    />
                    <Input
                        v-model="form.build.ehp"
                        label="EHP"
                        type="number"
                        mono
                        hint="Effective hit pool at your listed level."
                        :error="errors['build.ehp']"
                    />
                    <Input
                        v-model="form.build.armor"
                        label="Armour"
                        type="number"
                        mono
                        hint="The number on the character sheet."
                        :error="errors['build.armor']"
                    />
                </div>

                <p :class="LABEL_CLASS" class="mt-6">Resistances</p>
                <div class="mt-4 grid gap-4 sm:grid-cols-3 xl:grid-cols-5">
                    <Input
                        v-for="element in resistanceElements"
                        :key="element"
                        v-model="form.build.resistances[element]"
                        :label="element"
                        type="number"
                        mono
                        :error="errors[`build.resistances.${element}`]"
                    />
                </div>
            </Card>

            <SkillsCard v-model="form.build.equipped_skills" :errors="errors" />

            <ParagonCard
                v-model="form.build.paragon"
                :errors="errors"
                :boards="paragonBoards"
            />

            <GearCard v-model="form.build.gear" :errors="errors" />

            <Card padding="var(--sp-7)">
                <p :class="LABEL_CLASS">Season and mercenary</p>
                <div class="mt-4 grid gap-4 md:grid-cols-3">
                    <Input
                        v-model="form.build.seasonal_power"
                        label="Seasonal power"
                        hint="The season mechanic this build leans on."
                        :error="errors['build.seasonal_power']"
                    />
                    <Input
                        v-model="form.build.mercenary.hired"
                        label="Hired mercenary"
                        :error="errors['build.mercenary.hired']"
                    />
                    <Input
                        v-model="form.build.mercenary.reinforcement"
                        label="Reinforcement"
                        :error="errors['build.mercenary.reinforcement']"
                    />
                </div>
            </Card>

            <NotesCard
                v-model:notes="form.guide_markdown"
                v-model:milestones="form.build.milestones"
                :errors="errors"
            />

            <Card padding="var(--sp-7)">
                <p :class="LABEL_CLASS">Visibility</p>
                <RadioGroup v-model="form.visibility" class="mt-4">
                    <Radio value="draft" label="Draft — only you can open it" />
                    <Radio
                        value="public"
                        :label="`Public — listed on the ${game.short_name} hub`"
                    />
                </RadioGroup>

                <ul
                    class="mt-6 flex flex-col gap-2 border-t border-[var(--border-hairline)] pt-4"
                >
                    <li
                        v-for="check in checklist"
                        :key="check.key"
                        class="flex items-start gap-2 text-[13px]"
                    >
                        <Icon
                            :name="check.passed ? 'check' : 'triangle-alert'"
                            :size="13"
                            class="mt-0.5 shrink-0"
                            :style="{
                                color: check.passed
                                    ? 'var(--teal-400)'
                                    : 'var(--gold-400)',
                            }"
                        />
                        <span class="text-[var(--fg-2)]">
                            {{ check.label }}
                            <span
                                v-if="check.detail"
                                class="text-[var(--fg-3)]"
                            >
                                — {{ check.detail }}
                            </span>
                        </span>
                    </li>
                </ul>

                <p
                    v-if="blocked"
                    class="mt-4 text-[13px] text-[var(--gold-400)]"
                >
                    Publishing is blocked until every check passes. Save as a
                    draft in the meantime.
                </p>

                <div
                    v-if="form.errors.visibility"
                    class="mt-4 rounded-[var(--radius-sm)] border border-[var(--red-600)] p-3"
                >
                    <p class="text-[13px] font-semibold text-[var(--red-400)]">
                        The server rejected publishing this build.
                    </p>
                    <p class="mt-1 text-[13px] text-[var(--red-400)]">
                        {{ form.errors.visibility }}
                    </p>
                </div>
            </Card>

            <div
                class="flex flex-wrap items-center gap-3 border-t border-[var(--border-subtle)] pt-6 font-mono text-[12px] text-[var(--fg-3)]"
            >
                <span>Saving is in the bar above.</span>
                <span class="ml-auto">
                    byb://{{ game.slug }}/build/{{ build.id }}
                </span>
            </div>
        </div>
    </form>
</template>
