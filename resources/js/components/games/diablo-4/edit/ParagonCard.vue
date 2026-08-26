<script setup lang="ts">
import { computed } from 'vue';
import Button from '@/components/byb/Button.vue';
import Card from '@/components/byb/Card.vue';
import { LABEL_CLASS } from '@/components/byb/controls';
import IconButton from '@/components/byb/IconButton.vue';
import Input from '@/components/byb/Input.vue';
import Select from '@/components/byb/Select.vue';
import TagInput from '@/components/games/diablo-4/edit/TagInput.vue';
import ParagonView from '@/components/games/diablo-4/ParagonView.vue';
import {
    D4_MAX_PARAGON_BOARDS,
    D4_ROTATIONS,
} from '@/components/games/diablo-4/types';
import type {
    D4ParagonBoardGrid,
    D4ParagonEntry,
} from '@/components/games/diablo-4/types';

/**
 * The paragon plan, as an ordered list: boards attach in the order they are
 * listed, so moving an entry up or down is the edit that matters most.
 */
const paragon = defineModel<D4ParagonEntry[]>({ required: true });

const props = defineProps<{
    errors: Record<string, string>;
    /** Grid data, when the page has it: drives the read-only preview. */
    boards?: D4ParagonBoardGrid[];
}>();

/** Only worth a preview once the server actually sends board layouts. */
const showPreview = computed(
    () => (props.boards ?? []).length > 0 && paragon.value.length > 0,
);

const full = computed(() => paragon.value.length >= D4_MAX_PARAGON_BOARDS);

const rotationOptions = D4_ROTATIONS.map((rotation) => ({
    value: rotation,
    label: `${rotation}°`,
}));

function addBoard(): void {
    if (full.value) {
        return;
    }

    paragon.value.push({
        board: '',
        rotation: 0,
        glyph: '',
        glyph_level: null,
        notables: [],
    });
}

function removeBoard(index: number): void {
    paragon.value.splice(index, 1);
}

/** Attachment order is the plan, so it has to be editable in place. */
function move(index: number, delta: number): void {
    const target = index + delta;

    if (target < 0 || target >= paragon.value.length) {
        return;
    }

    const [entry] = paragon.value.splice(index, 1);
    paragon.value.splice(target, 0, entry);
}

function notablesFor(index: number): string[] {
    return paragon.value[index].notables ?? [];
}

function setNotables(index: number, value: string[]): void {
    paragon.value[index].notables = value;
}

function errorFor(index: number, field: string): string | undefined {
    return props.errors[`build.paragon.${index}.${field}`];
}
</script>

<template>
    <Card padding="var(--sp-7)">
        <div class="flex items-center gap-3">
            <p :class="LABEL_CLASS">Paragon boards</p>
            <span class="font-mono text-[12px] text-[var(--fg-3)]">
                {{ paragon.length }} / {{ D4_MAX_PARAGON_BOARDS }}
            </span>
            <Button
                type="button"
                size="sm"
                variant="ghost"
                icon="plus"
                class="ml-auto"
                :disabled="full"
                @click="addBoard"
            >
                Add board
            </Button>
        </div>

        <p
            v-if="errors['build.paragon']"
            class="mt-3 font-mono text-[12px] text-[var(--red-400)]"
        >
            {{ errors['build.paragon'] }}
        </p>

        <div class="mt-4 flex flex-col gap-3">
            <div
                v-for="(entry, index) in paragon"
                :key="`board-${index}`"
                class="flex flex-col gap-2 rounded-[var(--radius-sm)] border border-[var(--border-subtle)] bg-[var(--surface-card-hover)] p-4"
            >
                <div class="flex items-center gap-2">
                    <span :class="LABEL_CLASS" class="flex-1">
                        {{ index + 1 }}. {{ entry.board || 'New board' }}
                    </span>
                    <IconButton
                        type="button"
                        size="sm"
                        icon="chevron-left"
                        label="Attach earlier"
                        class="rotate-90"
                        :disabled="index === 0"
                        @click="move(index, -1)"
                    />
                    <IconButton
                        type="button"
                        size="sm"
                        icon="chevron-right"
                        label="Attach later"
                        class="rotate-90"
                        :disabled="index === paragon.length - 1"
                        @click="move(index, 1)"
                    />
                    <IconButton
                        type="button"
                        size="sm"
                        icon="x"
                        label="Remove board"
                        @click="removeBoard(index)"
                    />
                </div>

                <div class="grid gap-2 md:grid-cols-[1fr_100px]">
                    <Input
                        v-model="entry.board"
                        size="sm"
                        placeholder="Board name"
                        :error="errorFor(index, 'board')"
                    />
                    <Select
                        v-model="entry.rotation"
                        size="sm"
                        :options="rotationOptions"
                    />
                </div>

                <div class="grid gap-2 md:grid-cols-[1fr_100px]">
                    <Input
                        v-model="entry.glyph"
                        size="sm"
                        placeholder="Socketed glyph"
                        :error="errorFor(index, 'glyph')"
                    />
                    <Input
                        v-model="entry.glyph_level"
                        size="sm"
                        type="number"
                        mono
                        placeholder="Lvl"
                        :error="errorFor(index, 'glyph_level')"
                    />
                </div>

                <TagInput
                    label="Notables"
                    placeholder="Notable this board reaches — Enter to add"
                    :max="20"
                    :model-value="notablesFor(index)"
                    :error="errorFor(index, 'notables')"
                    @update:model-value="setNotables(index, $event)"
                />
            </div>
        </div>

        <!-- Read-only preview, the way the editor previews the PoE 2 tree. -->
        <div v-if="showPreview" class="mt-6">
            <p :class="LABEL_CLASS">Preview</p>
            <ParagonView
                class="mt-3"
                :entries="paragon"
                :boards="props.boards"
            />
        </div>
    </Card>
</template>
