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
import Textarea from '@/components/byb/Textarea.vue';
import { stageValue } from '@/components/games/poe2/build';
import GearCard from '@/components/games/poe2/edit/GearCard.vue';
import NotesCard from '@/components/games/poe2/edit/NotesCard.vue';
import PassivesCard from '@/components/games/poe2/edit/PassivesCard.vue';
import SkillsCard from '@/components/games/poe2/edit/SkillsCard.vue';
import type {
    Poe2BuildEditProps,
    Poe2GearItem,
    Poe2Milestone,
    Poe2SkillSetup,
} from '@/components/games/poe2/types';
import { cn } from '@/lib/utils';
import { update } from '@/routes/games/builds';

/**
 * Full edit mode for a Path of Exile 2 build (scope §3.7 / §3.8). The
 * assistant writes a partial payload; this is where a human checks the numbers
 * before it goes live.
 */
const props = defineProps<
    Poe2BuildEditProps & {
        spriteUrl: string;
        treeUrl: string | null;
        ascendancyKey: string | null;
        ascendancyPathIds: number[];
    }
>();

const definition = props.build.definition;

const form = useForm({
    name: props.build.name,
    summary: props.build.summary ?? '',
    guide_markdown: props.build.guide_markdown ?? '',
    visibility: props.build.visibility,
    build: {
        ...definition,
        class: definition.class ?? null,
        ascendancy: definition.ascendancy ?? null,
        // Older payloads carry the stage as `content_tier`; show what the
        // build page shows rather than an empty select.
        stage: stageValue(definition),
        tier: definition.tier ?? null,
        dps: definition.dps ?? null,
        ehp: definition.ehp ?? null,
        cost_divine: definition.cost_divine ?? null,
        skills: (definition.skills ?? []).map((skill) => ({ ...skill })),
        gear: (definition.gear ?? []).map((item) => ({ ...item })),
        milestones: (definition.milestones ?? []).map((milestone) => ({
            ...milestone,
        })),
        passives: {
            ...(definition.passives ?? {}),
            import_string: definition.passives?.import_string ?? '',
        },
    },
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

/** Number inputs hand back empty strings; the API wants nulls. */
form.transform((data) => ({
    ...data,
    summary: textOrNull(data.summary),
    guide_markdown: textOrNull(data.guide_markdown),
    build: {
        ...data.build,
        dps: numberOrNull(data.build.dps),
        ehp: numberOrNull(data.build.ehp),
        cost_divine: numberOrNull(data.build.cost_divine),
        level: numberOrNull(data.build.level),
        skills: (data.build.skills as Poe2SkillSetup[]).map((skill) => ({
            ...skill,
            level: numberOrNull(skill.level),
            quality: numberOrNull(skill.quality),
            role: textOrNull(skill.role),
            cost: textOrNull(skill.cost),
            reported: textOrNull(skill.reported),
        })),
        gear: (data.build.gear as Poe2GearItem[]).map((item) => ({
            ...item,
            name: textOrNull(item.name),
            base: textOrNull(item.base),
        })),
        milestones: (data.build.milestones as Poe2Milestone[]).map(
            (milestone) => ({
                ...milestone,
                level: numberOrNull(milestone.level) ?? 1,
            }),
        ),
        passives: {
            ...data.build.passives,
            import_string: textOrNull(data.build.passives?.import_string),
        },
    },
}));

/** Errors come back keyed by the dotted payload path, e.g. `build.gear.0.slot`. */
const errors = computed(() => form.errors as unknown as Record<string, string>);

/** Ascendancies belong to a class; picking a class narrows the list. */
const ascendancyOptions = computed(() =>
    props.options.ascendancies
        .filter(
            (ascendancy) =>
                !form.build.class ||
                ascendancy.class_name === null ||
                ascendancy.class_name === form.build.class,
        )
        .map((ascendancy) => ascendancy.name),
);

const stageOptions = computed(() =>
    props.options.stages.map((stage) => ({
        value: stage,
        label: stage.charAt(0).toUpperCase() + stage.slice(1),
    })),
);

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

    if (key === 'gear') {
        const slots = (form.build.gear as Poe2GearItem[]).map(
            (item) => item.slot,
        );

        return (
            slots.includes('body') &&
            (slots.includes('weapon1') || slots.includes('weapon2'))
        );
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
                    <p :class="cn(LABEL_CLASS, 'text-[var(--teal-400)]')">
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
                class="flex items-start gap-3 rounded-[var(--radius-sm)] border border-l-2 border-[var(--border-subtle)] border-l-[var(--teal-400)] bg-[var(--surface-accent-soft)] p-4"
            >
                <Icon
                    name="zap"
                    :size="16"
                    class="mt-0.5 shrink-0 text-[var(--teal-400)]"
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
                    <Select
                        v-model="form.build.ascendancy"
                        label="Ascendancy"
                        :options="ascendancyOptions"
                        placeholder="Pick an ascendancy"
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
                </div>
            </Card>

            <Card padding="var(--sp-7)">
                <p :class="LABEL_CLASS">Stats</p>
                <div class="mt-4 grid gap-4 md:grid-cols-3">
                    <Input
                        v-model="form.build.dps"
                        label="DPS"
                        type="number"
                        mono
                        hint="As your sim reports it."
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
                        v-model="form.build.cost_divine"
                        label="Cost in divine"
                        type="number"
                        step="0.1"
                        mono
                        hint="Excluding leveling gear."
                        :error="errors['build.cost_divine']"
                    />
                </div>
            </Card>

            <SkillsCard v-model="form.build.skills" :errors="errors" />

            <GearCard v-model="form.build.gear" :errors="errors" />

            <PassivesCard
                v-model="form.build.passives.import_string"
                :definition="form.build"
                :sprite-url="spriteUrl"
                :tree-url="treeUrl"
                :ascendancy-key="ascendancyKey"
                :ascendancy-path-ids="ascendancyPathIds"
                :error="errors['build.passives.import_string']"
            />

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
                <span class="ml-auto">byb://poe2/build/{{ build.id }}</span>
            </div>
        </div>
    </form>
</template>
