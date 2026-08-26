<script setup lang="ts">
import { computed, reactive } from 'vue';
import Button from '@/components/byb/Button.vue';
import Card from '@/components/byb/Card.vue';
import { LABEL_CLASS } from '@/components/byb/controls';
import IconButton from '@/components/byb/IconButton.vue';
import { hasItem, rarityColor } from '@/components/games/diablo-4/build';
import ItemFields from '@/components/games/diablo-4/edit/ItemFields.vue';
import {
    D4_GEAR_SLOTS,
    D4_MAX_WEAPONS,
    D4_SLOT_LABELS,
} from '@/components/games/diablo-4/types';
import type {
    D4Gear,
    D4GearItem,
    D4GearSlot,
} from '@/components/games/diablo-4/types';

/**
 * Gear is a map keyed by slot plus a weapons list, so this is not the PoE 2
 * "add a row and pick its slot" form: the nine places a character can equip
 * something are always listed, and an empty one stays folded until you open it.
 */
const gear = defineModel<D4Gear>({ required: true });

const props = defineProps<{
    errors: Record<string, string>;
}>();

/** Slots opened by hand this session; ones with an item are open anyway. */
const opened = reactive(new Set<string>());

const slots = computed(() =>
    D4_GEAR_SLOTS.map((slot) => ({
        slot,
        label: D4_SLOT_LABELS[slot],
        item: gear.value[slot] as D4GearItem | undefined,
        open: opened.has(slot) || hasItem(gear.value[slot]),
    })),
);

const weapons = computed(() => gear.value.weapons ?? []);

function toggle(slot: string): void {
    if (opened.has(slot)) {
        opened.delete(slot);

        return;
    }

    opened.add(slot);
}

function clearSlot(slot: D4GearSlot): void {
    gear.value[slot] = {};
    opened.delete(slot);
}

function addWeapon(): void {
    if (weapons.value.length >= D4_MAX_WEAPONS) {
        return;
    }

    gear.value.weapons = [...weapons.value, {}];
}

function removeWeapon(index: number): void {
    gear.value.weapons = weapons.value.filter(
        (_, position) => position !== index,
    );
}

function slotSummary(item: D4GearItem | undefined): string {
    if (!item || !hasItem(item)) {
        return 'Empty';
    }

    return item.name || item.item_type || 'Unnamed item';
}

const blockClass =
    'flex flex-col gap-2 rounded-[var(--radius-sm)] border border-[var(--border-subtle)] bg-[var(--surface-card-hover)] p-4';
</script>

<template>
    <Card padding="var(--sp-7)">
        <div class="flex items-center gap-3">
            <p :class="LABEL_CLASS">Gear</p>
            <span class="font-mono text-[12px] text-[var(--fg-3)]">
                keyed slots + up to {{ D4_MAX_WEAPONS }} weapons
            </span>
        </div>

        <div class="mt-4 flex flex-col gap-3">
            <div v-for="entry in slots" :key="entry.slot" :class="blockClass">
                <div class="flex items-center gap-3">
                    <span :class="LABEL_CLASS" class="shrink-0">
                        {{ entry.label }}
                    </span>
                    <span
                        class="min-w-0 flex-1 truncate text-[13px]"
                        :style="{ color: rarityColor(entry.item?.rarity) }"
                    >
                        {{ slotSummary(entry.item) }}
                    </span>
                    <IconButton
                        v-if="hasItem(entry.item)"
                        type="button"
                        size="sm"
                        icon="trash-2"
                        :label="`Clear ${entry.label}`"
                        @click="clearSlot(entry.slot)"
                    />
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        @click="toggle(entry.slot)"
                    >
                        {{ entry.open ? 'Close' : 'Edit' }}
                    </Button>
                </div>

                <ItemFields
                    v-if="entry.open"
                    v-model="gear[entry.slot]!"
                    :path="`build.gear.${entry.slot}`"
                    :errors="props.errors"
                />
            </div>

            <div :class="blockClass">
                <div class="flex items-center gap-3">
                    <p :class="LABEL_CLASS">Weapons</p>
                    <span class="font-mono text-[12px] text-[var(--fg-3)]">
                        {{ weapons.length }} / {{ D4_MAX_WEAPONS }}
                    </span>
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        icon="plus"
                        class="ml-auto"
                        :disabled="weapons.length >= D4_MAX_WEAPONS"
                        @click="addWeapon"
                    >
                        Add weapon
                    </Button>
                </div>

                <p
                    v-if="props.errors['build.gear.weapons']"
                    class="font-mono text-[12px] text-[var(--red-400)]"
                >
                    {{ props.errors['build.gear.weapons'] }}
                </p>

                <div
                    v-for="(weapon, index) in weapons"
                    :key="`weapon-${index}`"
                    class="flex flex-col gap-2 rounded-[var(--radius-sm)] border border-[var(--border-hairline)] p-3"
                >
                    <div class="flex items-center gap-3">
                        <span :class="LABEL_CLASS" class="flex-1">
                            Weapon {{ index + 1 }}
                        </span>
                        <IconButton
                            type="button"
                            size="sm"
                            icon="x"
                            label="Remove weapon"
                            @click="removeWeapon(index)"
                        />
                    </div>
                    <ItemFields
                        v-model="gear.weapons![index]"
                        :path="`build.gear.weapons.${index}`"
                        :errors="props.errors"
                    />
                </div>
            </div>
        </div>
    </Card>
</template>
