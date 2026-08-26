<script setup lang="ts">
import { computed, watch } from 'vue';
import Button from '@/components/byb/Button.vue';
import Checkbox from '@/components/byb/Checkbox.vue';
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

/** The editable, always-object form of an affix row. */
type AffixRow = {
    text?: string | null;
    affix?: string | null;
    value?: number | null;
    greater?: boolean | null;
};

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

/**
 * Affix rows edit as objects, so legacy string entries are lifted to `{text}`
 * as soon as the item binds — v-model needs object fields to write into.
 */
watch(
    () => item.value.affixes,
    (affixes) => {
        if (affixes?.some((entry) => typeof entry === 'string')) {
            item.value.affixes = affixes.map((entry) =>
                typeof entry === 'string' ? { text: entry } : entry,
            );
        }
    },
    { immediate: true },
);

const affixRows = computed(() => (item.value.affixes ?? []) as AffixRow[]);

function addAffix(): void {
    if (affixRows.value.length >= 8) {
        return;
    }

    item.value.affixes = [...affixRows.value, { text: '' }];
}

function removeAffix(index: number): void {
    item.value.affixes = affixRows.value.filter(
        (_, position) => position !== index,
    );
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

    item.value.tempered = [...tempered, { affix: '', tier: null, value: null }];
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

        <div class="flex items-center gap-2">
            <span :class="LABEL_CLASS">Affixes</span>
            <span class="font-mono text-[11px] text-[var(--fg-3)]">
                key + value feed the computed stats
            </span>
            <Button
                type="button"
                size="sm"
                variant="ghost"
                icon="plus"
                class="ml-auto"
                :disabled="affixRows.length >= 8"
                @click="addAffix"
            >
                Add affix
            </Button>
        </div>

        <p
            v-if="errorFor('affixes')"
            class="font-mono text-[12px] text-[var(--red-400)]"
        >
            {{ errorFor('affixes') }}
        </p>

        <div
            v-for="(affix, index) in affixRows"
            :key="`affix-${index}`"
            class="grid items-center gap-2 md:grid-cols-[1fr_170px_80px_44px_30px]"
        >
            <Input
                v-model="affix.text"
                size="sm"
                placeholder="Affix line as displayed"
                :error="errorFor(`affixes.${index}`)"
            />
            <Input
                v-model="affix.affix"
                size="sm"
                mono
                placeholder="affix key"
            />
            <Input
                v-model="affix.value"
                size="sm"
                type="number"
                mono
                placeholder="Roll"
            />
            <Checkbox
                :model-value="affix.greater === true"
                label="GA"
                @update:model-value="affix.greater = $event === true"
            />
            <IconButton
                type="button"
                size="sm"
                icon="x"
                label="Remove affix"
                @click="removeAffix(index)"
            />
        </div>

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
            class="grid items-start gap-2 md:grid-cols-[1fr_80px_80px_30px]"
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
            <Input
                v-model="temper.value"
                size="sm"
                type="number"
                mono
                placeholder="Roll"
                :error="errorFor(`tempered.${index}.value`)"
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
