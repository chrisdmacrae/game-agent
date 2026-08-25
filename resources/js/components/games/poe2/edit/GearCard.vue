<script setup lang="ts">
import Button from '@/components/byb/Button.vue';
import Card from '@/components/byb/Card.vue';
import { LABEL_CLASS } from '@/components/byb/controls';
import IconButton from '@/components/byb/IconButton.vue';
import Input from '@/components/byb/Input.vue';
import Select from '@/components/byb/Select.vue';
import {
    POE2_GEAR_SLOTS,
    POE2_RARITIES,
    POE2_SLOT_LABELS,
} from '@/components/games/poe2/types';
import type { Poe2GearItem } from '@/components/games/poe2/types';

/**
 * One block per equipped slot. Runes are typed as a comma-separated list, in
 * socket order — a blank entry between commas is an empty socket.
 */
const gear = defineModel<Poe2GearItem[]>({ required: true });

const props = defineProps<{
    errors: Record<string, string>;
}>();

const slotOptions = POE2_GEAR_SLOTS.map((slot) => ({
    value: slot,
    label: POE2_SLOT_LABELS[slot],
}));

const rarityOptions = POE2_RARITIES.map((rarity) => ({
    value: rarity,
    label: rarity.charAt(0).toUpperCase() + rarity.slice(1),
}));

function modsValue(item: Poe2GearItem): string {
    return (item.mods ?? []).join(', ');
}

function setMods(
    index: number,
    value: string | number | null | undefined,
): void {
    gear.value[index].mods = String(value ?? '')
        .split(',')
        .map((part) => part.trim())
        .filter((part) => part !== '');
}

function runesValue(item: Poe2GearItem): string {
    return (item.runes ?? []).map((rune) => rune ?? '').join(', ');
}

function setRunes(
    index: number,
    value: string | number | null | undefined,
): void {
    const raw = String(value ?? '');

    gear.value[index].runes =
        raw.trim() === ''
            ? []
            : raw.split(',').map((part) => {
                  const rune = part.trim();

                  return rune === '' ? null : rune;
              });
}

/** `2 sockets · 1 filled` — the mono readout beside each slot. */
function socketReadout(item: Poe2GearItem): string {
    const runes = item.runes ?? [];
    const noun = runes.length === 1 ? 'socket' : 'sockets';

    return `${runes.length} ${noun} · ${runes.filter(Boolean).length} filled`;
}

function addSlot(): void {
    gear.value.push({
        slot: 'body',
        rarity: 'rare',
        name: '',
        mods: [],
        runes: [],
    });
}

function removeSlot(index: number): void {
    gear.value.splice(index, 1);
}

function errorFor(index: number, field: string): string | undefined {
    return props.errors[`build.gear.${index}.${field}`];
}
</script>

<template>
    <Card padding="var(--sp-7)">
        <div class="flex items-center gap-3">
            <p :class="LABEL_CLASS">Gear and runes</p>
            <Button
                type="button"
                size="sm"
                variant="ghost"
                icon="plus"
                class="ml-auto"
                @click="addSlot"
            >
                Add slot
            </Button>
        </div>

        <div class="mt-4 flex flex-col gap-3">
            <div
                v-for="(item, index) in gear"
                :key="`gear-${index}`"
                class="flex flex-col gap-2 rounded-[var(--radius-sm)] border border-[var(--border-subtle)] bg-[var(--surface-card-hover)] p-4"
            >
                <div class="flex items-center gap-3">
                    <span :class="LABEL_CLASS" class="flex-1">
                        {{ POE2_SLOT_LABELS[item.slot] ?? 'New slot' }}
                    </span>
                    <span class="font-mono text-[12px] text-[var(--fg-3)]">
                        {{ socketReadout(item) }}
                    </span>
                    <IconButton
                        type="button"
                        size="sm"
                        icon="x"
                        label="Remove slot"
                        @click="removeSlot(index)"
                    />
                </div>

                <div class="grid gap-2 md:grid-cols-[160px_1fr_130px]">
                    <Select
                        v-model="item.slot"
                        size="sm"
                        :options="slotOptions"
                    />
                    <Input
                        v-model="item.name"
                        size="sm"
                        placeholder="Item name"
                        :error="errorFor(index, 'name')"
                    />
                    <Select
                        v-model="item.rarity"
                        size="sm"
                        :options="rarityOptions"
                    />
                </div>

                <Input
                    size="sm"
                    placeholder="Key modifiers — comma separated"
                    :model-value="modsValue(item)"
                    @update:model-value="setMods(index, $event)"
                />
                <Input
                    size="sm"
                    mono
                    placeholder="Runes — comma separated, blank entry for an empty socket"
                    :model-value="runesValue(item)"
                    @update:model-value="setRunes(index, $event)"
                />
            </div>
        </div>
    </Card>
</template>
