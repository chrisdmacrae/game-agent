<script setup lang="ts">
import { computed } from 'vue';
import Card from '@/components/byb/Card.vue';
import { LABEL_CLASS } from '@/components/byb/controls';
import {
    atlasStyle,
    D4_ACCENT,
    skillMeta,
} from '@/components/games/diablo-4/build';
import EmptyBlock from '@/components/games/diablo-4/build/EmptyBlock.vue';
import SkillTreeCanvas from '@/components/games/diablo-4/SkillTreeCanvas.vue';
import { D4_MAX_EQUIPPED_SKILLS } from '@/components/games/diablo-4/types';
import type {
    D4BuildDefinition,
    D4Entity,
    D4SkillTree,
} from '@/components/games/diablo-4/types';

const props = defineProps<{
    definition: D4BuildDefinition;
    /** Hover-card lookup from BuildShow; icons render when the atlas exists. */
    entityFor?: (name: string) => D4Entity | null;
    /** The class skill tree, when the server sent it. Optional by design. */
    skillTree?: D4SkillTree | null;
}>();

function iconFor(name: string): D4Entity['icon'] {
    return props.entityFor?.(name)?.icon ?? null;
}

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

        <!-- The bar itself, the way the game (and every D4 build site) draws
             it: six slots in a row, icon art in a socket, rank badged. -->
        <div
            v-if="hasSkills"
            class="flex flex-wrap items-start justify-center gap-3 rounded-[var(--radius-md)] border border-[var(--border-subtle)] px-4 py-5"
            style="background: #07090d"
        >
            <div
                v-for="slot in slots"
                :key="`bar-${slot.index}`"
                class="flex w-[86px] flex-col items-center gap-2"
            >
                <div
                    class="relative flex size-[64px] items-center justify-center rounded-[10px] border-2"
                    :style="
                        slot.skill
                            ? {
                                  borderColor: '#c79b5a',
                                  background: '#15120d',
                                  boxShadow:
                                      '0 0 16px rgba(255,215,122,0.22), inset 0 0 0 1px rgba(240,217,160,0.35)',
                              }
                            : {
                                  borderColor: '#4a4238',
                                  borderStyle: 'dashed',
                                  background: '#0a0908',
                              }
                    "
                    :data-entity="slot.skill?.skill"
                >
                    <span
                        v-if="slot.skill && iconFor(slot.skill.skill)"
                        class="inline-block rounded-[6px]"
                        :style="atlasStyle(iconFor(slot.skill.skill)!, 54)"
                    />
                    <span
                        v-else-if="slot.skill"
                        class="font-mono text-[22px] font-bold"
                        style="color: #c79b5a"
                    >
                        {{ slot.skill.skill.charAt(0) }}
                    </span>
                    <span
                        v-if="slot.skill?.rank"
                        class="absolute -right-1.5 -bottom-1.5 flex min-w-[20px] items-center justify-center rounded-[5px] border px-1 font-mono text-[11px] font-bold"
                        style="
                            background: #15120d;
                            border-color: #c79b5a;
                            color: #f0d9a0;
                        "
                    >
                        {{ slot.skill.rank }}
                    </span>
                </div>
                <p
                    class="w-full truncate text-center font-mono text-[11px] leading-tight"
                    :style="{ color: slot.skill ? 'var(--fg-2)' : 'var(--fg-3)' }"
                    :data-entity="slot.skill?.skill"
                >
                    {{ slot.skill?.skill ?? '—' }}
                </p>
            </div>
        </div>

        <!-- The class skill tree, laid out the way the game lays it out,
             with this build's picks lit. -->
        <template v-if="skillTree && skillTree.nodes.length">
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <p :class="LABEL_CLASS">Skill tree</p>
                <span class="font-mono text-[11px] text-[var(--fg-3)]">
                    <span style="color: #f0d9a0">◆</span> gold frames are this
                    build's picks · diamonds are skills, circles passives
                </span>
            </div>
            <SkillTreeCanvas
                :tree="skillTree"
                :definition="definition"
                :entity-for="entityFor"
            />
        </template>

        <div v-if="hasSkills" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
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
                            v-if="iconFor(slot.skill.skill)"
                            class="inline-block shrink-0 rounded-[var(--radius-xs)] border border-[var(--border-subtle)] bg-[var(--ink-950)]"
                            :style="atlasStyle(iconFor(slot.skill.skill)!, 34)"
                            :data-entity="slot.skill.skill"
                        />
                        <span
                            v-else
                            class="flex size-[34px] shrink-0 items-center justify-center rounded-[var(--radius-xs)] border border-[var(--border-subtle)] bg-[var(--surface-card-hover)] font-mono text-[13px] font-bold"
                            :style="{ color: D4_ACCENT }"
                        >
                            {{ slot.index + 1 }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p
                                class="text-[18px] leading-[1.28] font-semibold text-[var(--fg-1)]"
                                :data-entity="slot.skill.skill"
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
                    <span
                        class="flex-1 text-[13px] text-[var(--fg-2)]"
                        :data-entity="entry.skill"
                    >
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
