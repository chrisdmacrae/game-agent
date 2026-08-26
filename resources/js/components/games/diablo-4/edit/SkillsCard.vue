<script setup lang="ts">
import { computed } from 'vue';
import Button from '@/components/byb/Button.vue';
import Card from '@/components/byb/Card.vue';
import { LABEL_CLASS } from '@/components/byb/controls';
import IconButton from '@/components/byb/IconButton.vue';
import Input from '@/components/byb/Input.vue';
import TagInput from '@/components/games/diablo-4/edit/TagInput.vue';
import { D4_MAX_EQUIPPED_SKILLS } from '@/components/games/diablo-4/types';
import type { D4EquippedSkill } from '@/components/games/diablo-4/types';

/**
 * The action bar: up to six skills, each with its rank and the modifier pairs
 * or variant nodes picked under it. Add is disabled at six rather than letting
 * the server reject the seventh.
 */
const skills = defineModel<D4EquippedSkill[]>({ required: true });

const props = defineProps<{
    errors: Record<string, string>;
}>();

const full = computed(() => skills.value.length >= D4_MAX_EQUIPPED_SKILLS);

function addSkill(): void {
    if (full.value) {
        return;
    }

    skills.value.push({
        skill: '',
        role: '',
        rank: null,
        modifiers: [],
        reported: '',
    });
}

function removeSkill(index: number): void {
    skills.value.splice(index, 1);
}

function modifiersFor(index: number): string[] {
    return skills.value[index].modifiers ?? [];
}

function setModifiers(index: number, value: string[]): void {
    skills.value[index].modifiers = value;
}

function errorFor(index: number, field: string): string | undefined {
    return props.errors[`build.equipped_skills.${index}.${field}`];
}
</script>

<template>
    <Card padding="var(--sp-7)">
        <div class="flex items-center gap-3">
            <p :class="LABEL_CLASS">Action bar</p>
            <span class="font-mono text-[12px] text-[var(--fg-3)]">
                {{ skills.length }} / {{ D4_MAX_EQUIPPED_SKILLS }}
            </span>
            <Button
                type="button"
                size="sm"
                variant="ghost"
                icon="plus"
                class="ml-auto"
                :disabled="full"
                @click="addSkill"
            >
                Add skill
            </Button>
        </div>

        <p
            v-if="errors['build.equipped_skills']"
            class="mt-3 font-mono text-[12px] text-[var(--red-400)]"
        >
            {{ errors['build.equipped_skills'] }}
        </p>

        <div class="mt-4 flex flex-col gap-3">
            <div
                v-for="(skill, index) in skills"
                :key="`skill-${index}`"
                class="flex flex-col gap-2 rounded-[var(--radius-sm)] border border-[var(--border-subtle)] bg-[var(--surface-card-hover)] p-4"
            >
                <div class="flex items-center gap-2">
                    <span :class="LABEL_CLASS" class="flex-1">
                        Slot {{ index + 1 }} —
                        {{ skill.skill || 'new skill' }}
                    </span>
                    <IconButton
                        type="button"
                        size="sm"
                        icon="x"
                        label="Remove skill"
                        @click="removeSkill(index)"
                    />
                </div>

                <div class="grid gap-2 md:grid-cols-[1fr_1fr_80px]">
                    <Input
                        v-model="skill.skill"
                        size="sm"
                        placeholder="Skill"
                        :error="errorFor(index, 'skill')"
                    />
                    <Input
                        v-model="skill.role"
                        size="sm"
                        placeholder="Role"
                        :error="errorFor(index, 'role')"
                    />
                    <Input
                        v-model="skill.rank"
                        size="sm"
                        type="number"
                        mono
                        placeholder="Rank"
                        :error="errorFor(index, 'rank')"
                    />
                </div>

                <TagInput
                    label="Modifiers"
                    placeholder="Modifier pair or variant node — Enter to add"
                    :max="4"
                    :model-value="modifiersFor(index)"
                    :error="errorFor(index, 'modifiers')"
                    @update:model-value="setModifiers(index, $event)"
                />

                <Input
                    v-model="skill.reported"
                    size="sm"
                    placeholder="Reported numbers"
                    :error="errorFor(index, 'reported')"
                />
            </div>
        </div>
    </Card>
</template>
