<script setup lang="ts">
import Button from '@/components/byb/Button.vue';
import { LABEL_CLASS } from '@/components/byb/controls';
import IconButton from '@/components/byb/IconButton.vue';
import Input from '@/components/byb/Input.vue';
import Select from '@/components/byb/Select.vue';
import TagInput from '@/components/games/diablo-4/edit/TagInput.vue';
import {
    D4_MAX_GREATER_AFFIXES,
    D4_MAX_MASTERWORK,
    D4_RARITIES,
} from '@/components/games/diablo-4/types';
import type { D4GearItem } from '@/components/games/diablo-4/types';

/**
 * The fields of one equipped item, shared by the keyed slots and the weapons
 * list. Aspect, tempering, masterworking and runes are all per item in Diablo
 * IV, so they live here rather than being repeated per slot.
 */
const item = defineModel<D4GearItem>({ required: true });

const props = defineProps<{
    /** The dotted payload path this item sits at, e.g. `build.gear.helm`. */
    path: string;
    errors: Record<string, string>;
}>();

const rarityOptions = D4_RARITIES.map((rarity) => ({
    value: rarity,
    label: rarity.charAt(0).toUpperCase() + rarity.slice(1),
}));

const greaterAffixOptions = Array.from(
    { length: D4_MAX_GREATER_AFFIXES + 1 },
    (_, count) => ({ value: count, label: String(count) }),
);

const masterworkOptions = Array.from(
    { length: D4_MAX_MASTERWORK + 1 },
    (_, level) => ({ value: level, label: String(level) }),
);

function setAffixes(value: string[]): void {
    item.value.affixes = value;
}

function setRunes(value: string[]): void {
    item.value.runes = value;
}

function addTempered(): void {
    const tempered = item.value.tempered ?? [];

    // Two is the ceiling the rules allow; the validator reports the current
    // in-game limit rather than blocking the second entry here.
    if (tempered.length >= 2) {
        return;
    }

    item.value.tempered = [...tempered, { affix: '', tier: null }];
}

function removeTempered(index: number): void {
    item.value.tempered = (item.value.tempered ?? []).filter(
        (_, position) => position !== index,
    );
}

function errorFor(field: string): string | undefined {
    return props.errors[`${props.path}.${field}`];
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <div class="grid gap-2 md:grid-cols-[1fr_1fr_130px]">
            <Input
                v-model="item.name"
                size="sm"
                placeholder="Item name"
                :error="errorFor('name')"
            />
            <Input
                v-model="item.item_type"
                size="sm"
                placeholder="Item type"
                :error="errorFor('item_type')"
            />
            <Select
                v-model="item.rarity"
                size="sm"
                :options="rarityOptions"
                placeholder="Rarity"
            />
        </div>

        <Input
            v-model="item.aspect"
            size="sm"
            placeholder="Legendary aspect"
            :error="errorFor('aspect')"
        />

        <TagInput
            label="Affixes"
            placeholder="Affix line — Enter to add"
            :max="8"
            :model-value="item.affixes ?? []"
            :error="errorFor('affixes')"
            @update:model-value="setAffixes"
        />

        <div class="grid gap-2 md:grid-cols-2">
            <Select
                v-model="item.greater_affixes"
                size="sm"
                label="Greater affixes"
                :options="greaterAffixOptions"
            />
            <Select
                v-model="item.masterwork_level"
                size="sm"
                label="Masterwork level"
                :options="masterworkOptions"
            />
        </div>

        <TagInput
            label="Runes"
            placeholder="Rune — condition first, then effect"
            :max="2"
            :model-value="item.runes ?? []"
            :error="errorFor('runes')"
            @update:model-value="setRunes"
        />

        <div class="flex items-center gap-2">
            <span :class="LABEL_CLASS">Tempered</span>
            <Button
                type="button"
                size="sm"
                variant="ghost"
                icon="plus"
                class="ml-auto"
                :disabled="(item.tempered ?? []).length >= 2"
                @click="addTempered"
            >
                Add temper
            </Button>
        </div>

        <div
            v-for="(temper, index) in item.tempered ?? []"
            :key="`temper-${index}`"
            class="grid items-start gap-2 md:grid-cols-[1fr_80px_30px]"
        >
            <Input
                v-model="temper.affix"
                size="sm"
                placeholder="Tempered affix"
                :error="errorFor(`tempered.${index}.affix`)"
            />
            <Input
                v-model="temper.tier"
                size="sm"
                type="number"
                mono
                placeholder="Tier"
                :error="errorFor(`tempered.${index}.tier`)"
            />
            <IconButton
                type="button"
                size="sm"
                icon="x"
                label="Remove tempered affix"
                @click="removeTempered(index)"
            />
        </div>
    </div>
</template>
