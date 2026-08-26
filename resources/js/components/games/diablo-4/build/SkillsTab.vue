<script setup lang="ts">
import { computed } from 'vue';
import Card from '@/components/byb/Card.vue';
import { LABEL_CLASS } from '@/components/byb/controls';
import { D4_ACCENT, skillMeta } from '@/components/games/diablo-4/build';
import EmptyBlock from '@/components/games/diablo-4/build/EmptyBlock.vue';
import { D4_MAX_EQUIPPED_SKILLS } from '@/components/games/diablo-4/types';
import type { D4BuildDefinition } from '@/components/games/diablo-4/types';

const props = defineProps<{
    definition: D4BuildDefinition;
}>();

/**
 * The action bar holds six. Empty slots are drawn so a five-skill bar reads as
 * a deliberate choice rather than a missing row.
 */
const slots = computed(() => {
    const equipped = props.definition.equipped_skills ?? [];

    return Array.from({ length: D4_MAX_EQUIPPED_SKILLS }, (_, index) => ({
        index,
        skill: equipped[index] ?? null,
    }));
});

const hasSkills = computed(
    () => (props.definition.equipped_skills ?? []).length > 0,
);

/** Points spent in the skill tree, outside the six equipped. */
const skillPoints = computed(() =>
    (props.definition.skill_points ?? []).filter((entry) => entry.skill),
);
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex flex-wrap items-center gap-3">
            <p :class="LABEL_CLASS">Action bar</p>
            <span class="font-mono text-[12px] text-[var(--fg-3)]">
                {{ (definition.equipped_skills ?? []).length }} of
                {{ D4_MAX_EQUIPPED_SKILLS }} slots
            </span>
        </div>

        <EmptyBlock
            v-if="!hasSkills"
            message="No skills on the action bar yet."
        />

        <div v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <Card
                v-for="slot in slots"
                :key="`slot-${slot.index}`"
                padding="0"
                :class="
                    slot.skill
                        ? undefined
                        : 'border-dashed bg-transparent shadow-none'
                "
            >
                <template v-if="slot.skill">
                    <div
                        class="flex items-start gap-3 border-b border-[var(--border-hairline)] p-4"
                    >
                        <span
                            class="flex size-[34px] shrink-0 items-center justify-center rounded-[var(--radius-xs)] border border-[var(--border-subtle)] bg-[var(--surface-card-hover)] font-mono text-[13px] font-bold"
                            :style="{ color: D4_ACCENT }"
                        >
                            {{ slot.index + 1 }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p
                                class="text-[18px] leading-[1.28] font-semibold text-[var(--fg-1)]"
                            >
                                {{ slot.skill.skill }}
                            </p>
                            <p
                                v-if="slot.skill.role"
                                class="mt-0.5 font-mono text-[12px] text-[var(--fg-3)]"
                            >
                                {{ slot.skill.role }}
                            </p>
                        </div>
                        <span
                            v-if="skillMeta(slot.skill)"
                            class="shrink-0 font-mono text-[12px] text-[var(--fg-2)]"
                        >
                            {{ skillMeta(slot.skill) }}
                        </span>
                    </div>

                    <div class="p-4">
                        <p :class="LABEL_CLASS">Modifiers</p>
                        <div
                            v-if="slot.skill.modifiers?.length"
                            class="mt-3 flex flex-wrap gap-1.5"
                        >
                            <span
                                v-for="modifier in slot.skill.modifiers"
                                :key="modifier"
                                class="inline-flex items-center rounded-[var(--radius-pill)] border border-[var(--border-subtle)] bg-[var(--surface-card-hover)] px-2.5 py-1 font-mono text-[12px] leading-none text-[var(--fg-2)]"
                            >
                                {{ modifier }}
                            </span>
                        </div>
                        <EmptyBlock
                            v-else
                            class="mt-3"
                            message="No modifier pairs or variants picked."
                        />
                        <p
                            v-if="slot.skill.reported"
                            class="mt-3 font-mono text-[12px] text-[var(--fg-3)]"
                        >
                            {{ slot.skill.reported }}
                        </p>
                    </div>
                </template>

                <div
                    v-else
                    class="flex min-h-[108px] items-center justify-center p-4"
                >
                    <p :class="LABEL_CLASS">Slot {{ slot.index + 1 }} empty</p>
                </div>
            </Card>
        </div>

        <Card v-if="skillPoints.length">
            <p :class="LABEL_CLASS">Skill tree points</p>
            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                <div
                    v-for="entry in skillPoints"
                    :key="entry.skill"
                    class="flex items-center gap-3 rounded-[var(--radius-sm)] border border-[var(--border-hairline)] bg-[var(--surface-card-hover)] px-3 py-2"
                >
                    <span class="flex-1 text-[13px] text-[var(--fg-2)]">
                        {{ entry.skill }}
                    </span>
                    <span class="font-mono text-[14px] text-[var(--fg-1)]">
                        {{ entry.points ?? 0 }}
                    </span>
                </div>
            </div>
        </Card>
    </div>
</template>
