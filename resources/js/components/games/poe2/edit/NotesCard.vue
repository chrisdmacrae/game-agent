<script setup lang="ts">
import Button from '@/components/byb/Button.vue';
import Card from '@/components/byb/Card.vue';
import { LABEL_CLASS } from '@/components/byb/controls';
import IconButton from '@/components/byb/IconButton.vue';
import Input from '@/components/byb/Input.vue';
import Textarea from '@/components/byb/Textarea.vue';
import type { Poe2Milestone } from '@/components/games/poe2/types';

const notes = defineModel<string>('notes', { required: true });
const milestones = defineModel<Poe2Milestone[]>('milestones', {
    required: true,
});

const props = defineProps<{
    errors: Record<string, string>;
}>();

function addMilestone(): void {
    milestones.value.push({ level: 1, text: '' });
}

function removeMilestone(index: number): void {
    milestones.value.splice(index, 1);
}

function errorFor(index: number, field: string): string | undefined {
    return props.errors[`build.milestones.${index}.${field}`];
}
</script>

<template>
    <Card padding="var(--sp-7)">
        <p :class="LABEL_CLASS">Notes and milestones</p>

        <Textarea
            v-model="notes"
            class="mt-4"
            :rows="8"
            label="Guide"
            hint="Markdown. This is the main thing readers scroll through."
            :error="errors.guide_markdown"
        />

        <div class="mt-6 flex items-center gap-3">
            <p :class="LABEL_CLASS">Leveling milestones</p>
            <Button
                type="button"
                size="sm"
                variant="ghost"
                icon="plus"
                class="ml-auto"
                @click="addMilestone"
            >
                Add milestone
            </Button>
        </div>

        <div class="mt-4 flex flex-col gap-2">
            <div
                v-for="(milestone, index) in milestones"
                :key="`milestone-${index}`"
                class="grid items-start gap-2 md:grid-cols-[90px_1fr_30px]"
            >
                <Input
                    v-model="milestone.level"
                    size="sm"
                    type="number"
                    mono
                    placeholder="Lvl"
                    :error="errorFor(index, 'level')"
                />
                <Input
                    v-model="milestone.text"
                    size="sm"
                    placeholder="What changes"
                    :error="errorFor(index, 'text')"
                />
                <IconButton
                    type="button"
                    size="sm"
                    icon="x"
                    label="Remove milestone"
                    @click="removeMilestone(index)"
                />
            </div>
        </div>
    </Card>
</template>
