<script setup lang="ts">
import Button from '@/components/byb/Button.vue';
import Card from '@/components/byb/Card.vue';
import { LABEL_CLASS } from '@/components/byb/controls';
import IconButton from '@/components/byb/IconButton.vue';
import Input from '@/components/byb/Input.vue';
import { skillSupports } from '@/components/games/poe2/build';
import type { Poe2SkillSetup } from '@/components/games/poe2/types';

/**
 * One block per skill gem. Tags and support gems are typed as comma-separated
 * lists — a support entry reads `name` or `name: effect`.
 */
const skills = defineModel<Poe2SkillSetup[]>({ required: true });

const props = defineProps<{
    errors: Record<string, string>;
}>();

function splitList(value: string): string[] {
    return value
        .split(',')
        .map((part) => part.trim())
        .filter((part) => part !== '');
}

function tagsValue(skill: Poe2SkillSetup): string {
    return (skill.tags ?? []).join(', ');
}

function setTags(
    index: number,
    value: string | number | null | undefined,
): void {
    skills.value[index].tags = splitList(String(value ?? ''));
}

function supportsValue(skill: Poe2SkillSetup): string {
    return skillSupports(skill)
        .map((support) =>
            support.effect
                ? `${support.name}: ${support.effect}`
                : support.name,
        )
        .join(', ');
}

function setSupports(
    index: number,
    value: string | number | null | undefined,
): void {
    skills.value[index].supports = splitList(String(value ?? '')).map(
        (entry) => {
            const [name, ...effect] = entry.split(':');

            return {
                name: name.trim(),
                effect: effect.length ? effect.join(':').trim() : null,
            };
        },
    );
}

function addSkill(): void {
    skills.value.push({
        gem: '',
        role: '',
        level: null,
        cost: '',
        tags: [],
        reported: '',
        supports: [],
    });
}

function removeSkill(index: number): void {
    skills.value.splice(index, 1);
}

function errorFor(index: number, field: string): string | undefined {
    return props.errors[`build.skills.${index}.${field}`];
}
</script>

<template>
    <Card padding="var(--sp-7)">
        <div class="flex items-center gap-3">
            <p :class="LABEL_CLASS">Skills and support gems</p>
            <Button
                type="button"
                size="sm"
                variant="ghost"
                icon="plus"
                class="ml-auto"
                @click="addSkill"
            >
                Add skill
            </Button>
        </div>

        <p
            v-if="errors['build.skills']"
            class="mt-3 font-mono text-[12px] text-[var(--red-400)]"
        >
            {{ errors['build.skills'] }}
        </p>

        <div class="mt-4 flex flex-col gap-3">
            <div
                v-for="(skill, index) in skills"
                :key="`skill-${index}`"
                class="flex flex-col gap-2 rounded-[var(--radius-sm)] border border-[var(--border-subtle)] bg-[var(--surface-card-hover)] p-4"
            >
                <div class="flex items-center gap-2">
                    <span :class="LABEL_CLASS" class="flex-1">
                        {{ skill.gem || 'New skill' }}
                    </span>
                    <IconButton
                        type="button"
                        size="sm"
                        icon="x"
                        label="Remove skill"
                        @click="removeSkill(index)"
                    />
                </div>

                <div class="grid gap-2 md:grid-cols-[1fr_1fr_70px_110px]">
                    <Input
                        v-model="skill.gem"
                        size="sm"
                        placeholder="Skill gem"
                        :error="errorFor(index, 'gem')"
                    />
                    <Input
                        v-model="skill.role"
                        size="sm"
                        placeholder="Role"
                        :error="errorFor(index, 'role')"
                    />
                    <Input
                        v-model="skill.level"
                        size="sm"
                        type="number"
                        mono
                        placeholder="Lvl"
                        :error="errorFor(index, 'level')"
                    />
                    <Input
                        v-model="skill.cost"
                        size="sm"
                        mono
                        placeholder="Cost"
                        :error="errorFor(index, 'cost')"
                    />
                </div>

                <div class="grid gap-2 md:grid-cols-2">
                    <Input
                        size="sm"
                        placeholder="Tags — comma separated"
                        :model-value="tagsValue(skill)"
                        @update:model-value="setTags(index, $event)"
                    />
                    <Input
                        v-model="skill.reported"
                        size="sm"
                        placeholder="Reported numbers"
                        :error="errorFor(index, 'reported')"
                    />
                </div>

                <Input
                    size="sm"
                    placeholder="Support gems — comma separated, use “name: effect”"
                    :model-value="supportsValue(skill)"
                    @update:model-value="setSupports(index, $event)"
                />
            </div>
        </div>
    </Card>
</template>
