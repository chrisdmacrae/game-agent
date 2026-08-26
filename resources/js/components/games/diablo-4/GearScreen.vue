<script setup lang="ts">
import { computed } from 'vue';
import { LABEL_CLASS } from '@/components/byb/controls';
import {
    gearCells,
    itemLabel,
    masterworkLabel,
    rarityColor,
    weaponList,
} from '@/components/games/diablo-4/build';
import type { D4Gear, D4GearItem } from '@/components/games/diablo-4/types';
import { cn } from '@/lib/utils';

/**
 * The Diablo IV paperdoll. There are no item icons in the imported data, so a
 * slot is a card rather than a sprite well: the name in its rarity colour, the
 * greater-affix pips, the legendary aspect, then the affix lines with the
 * tempered ones marked. Weapons are their own row because how many a character
 * carries is per class.
 */
const props = defineProps<{
    gear: D4Gear | null;
    /** Rendered smaller, without the empty slots, inside the editor preview. */
    compact?: boolean;
}>();

const cells = computed(() => gearCells(props.gear));

const weapons = computed(() => weaponList(props.gear));

function pips(item: D4GearItem): number[] {
    return Array.from({ length: item.greater_affixes ?? 0 }, (_, i) => i);
}

const cardClass =
    'flex flex-col gap-2 rounded-[var(--radius-md)] border border-[var(--border-subtle)] bg-[var(--surface-card)] p-3 [box-shadow:var(--shadow-1)] [transition:var(--transition-control)] hover:border-[var(--border-strong)]';

const emptyClass =
    'flex min-h-[76px] flex-col items-center justify-center rounded-[var(--radius-md)] border border-dashed border-[var(--border-subtle)] p-3 text-center';

const chipClass =
    'inline-flex items-center rounded-[var(--radius-xs)] border px-1.5 py-0.5 font-mono text-[10px] leading-none font-bold tracking-[0.14em] uppercase';
</script>

<template>
    <div class="flex flex-col gap-3">
        <!-- Two columns of keyed slots, the way the character sheet stacks
             armour on the left and jewellery on the right. -->
        <div class="grid items-start gap-3 md:grid-cols-2">
            <template v-for="cell in cells" :key="cell.slot">
                <div v-if="cell.item" :class="cardClass">
                    <div class="flex items-start gap-2">
                        <div class="min-w-0 flex-1">
                            <p
                                class="text-[15px] leading-tight font-semibold"
                                :style="{
                                    color: rarityColor(cell.item.rarity),
                                }"
                            >
                                {{ itemLabel(cell.item, cell.label) }}
                            </p>
                            <p
                                class="mt-0.5 font-mono text-[11px] tracking-[0.14em] text-[var(--fg-3)] uppercase"
                            >
                                {{ cell.label
                                }}<template v-if="cell.item.item_type">
                                    · {{ cell.item.item_type }}</template
                                >
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-1.5">
                            <span
                                v-if="pips(cell.item).length"
                                class="flex items-center gap-0.5"
                                :title="`${cell.item.greater_affixes} greater affixes`"
                            >
                                <span
                                    v-for="pip in pips(cell.item)"
                                    :key="`${cell.slot}-pip-${pip}`"
                                    class="size-1.5 rounded-full bg-[var(--gold-400)]"
                                />
                            </span>
                            <span
                                v-if="masterworkLabel(cell.item)"
                                :class="
                                    cn(
                                        chipClass,
                                        'border-[var(--border-strong)] text-[var(--fg-2)]',
                                    )
                                "
                            >
                                {{ masterworkLabel(cell.item) }}
                            </span>
                        </div>
                    </div>

                    <p
                        v-if="cell.item.aspect"
                        class="font-mono text-[12px] text-[var(--violet-400)]"
                    >
                        {{ cell.item.aspect }}
                    </p>

                    <ul
                        v-if="cell.item.affixes?.length"
                        class="flex flex-col gap-0.5 font-mono text-[12px] text-[var(--fg-2)]"
                    >
                        <li
                            v-for="affix in cell.item.affixes"
                            :key="`${cell.slot}-${affix}`"
                        >
                            {{ affix }}
                        </li>
                    </ul>

                    <ul
                        v-if="cell.item.tempered?.length"
                        class="flex flex-col gap-0.5 font-mono text-[12px] text-[var(--teal-300)]"
                    >
                        <li
                            v-for="(temper, index) in cell.item.tempered"
                            :key="`${cell.slot}-temper-${index}`"
                        >
                            {{ temper.affix }}
                            <span class="text-[var(--fg-3)]">
                                — tempered<template v-if="temper.tier">
                                    T{{ temper.tier }}</template
                                >
                            </span>
                        </li>
                    </ul>

                    <div
                        v-if="cell.item.runes?.length"
                        class="flex flex-wrap gap-1"
                    >
                        <span
                            v-for="rune in cell.item.runes"
                            :key="`${cell.slot}-rune-${rune}`"
                            :class="
                                cn(
                                    chipClass,
                                    'border-[var(--border-strong)] bg-[var(--surface-card-hover)] text-[var(--fg-2)]',
                                )
                            "
                        >
                            {{ rune }}
                        </span>
                    </div>
                </div>

                <div v-else-if="!props.compact" :class="emptyClass">
                    <p :class="LABEL_CLASS">{{ cell.label }}</p>
                    <p class="mt-1 text-[13px] text-[var(--fg-3)]">Empty</p>
                </div>
            </template>
        </div>

        <!-- Weapons row -->
        <div v-if="weapons.length" class="flex flex-col gap-2">
            <p :class="LABEL_CLASS">Weapons</p>
            <div class="grid items-start gap-3 md:grid-cols-2">
                <div
                    v-for="(weapon, index) in weapons"
                    :key="`weapon-${index}`"
                    :class="cardClass"
                >
                    <div class="flex items-start gap-2">
                        <div class="min-w-0 flex-1">
                            <p
                                class="text-[15px] leading-tight font-semibold"
                                :style="{ color: rarityColor(weapon.rarity) }"
                            >
                                {{ itemLabel(weapon, `Weapon ${index + 1}`) }}
                            </p>
                            <p
                                class="mt-0.5 font-mono text-[11px] tracking-[0.14em] text-[var(--fg-3)] uppercase"
                            >
                                {{ weapon.item_type ?? `Weapon ${index + 1}` }}
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-1.5">
                            <span
                                v-if="pips(weapon).length"
                                class="flex items-center gap-0.5"
                                :title="`${weapon.greater_affixes} greater affixes`"
                            >
                                <span
                                    v-for="pip in pips(weapon)"
                                    :key="`weapon-${index}-pip-${pip}`"
                                    class="size-1.5 rounded-full bg-[var(--gold-400)]"
                                />
                            </span>
                            <span
                                v-if="masterworkLabel(weapon)"
                                :class="
                                    cn(
                                        chipClass,
                                        'border-[var(--border-strong)] text-[var(--fg-2)]',
                                    )
                                "
                            >
                                {{ masterworkLabel(weapon) }}
                            </span>
                        </div>
                    </div>

                    <p
                        v-if="weapon.aspect"
                        class="font-mono text-[12px] text-[var(--violet-400)]"
                    >
                        {{ weapon.aspect }}
                    </p>

                    <ul
                        v-if="weapon.affixes?.length"
                        class="flex flex-col gap-0.5 font-mono text-[12px] text-[var(--fg-2)]"
                    >
                        <li
                            v-for="affix in weapon.affixes"
                            :key="`weapon-${index}-${affix}`"
                        >
                            {{ affix }}
                        </li>
                    </ul>

                    <ul
                        v-if="weapon.tempered?.length"
                        class="flex flex-col gap-0.5 font-mono text-[12px] text-[var(--teal-300)]"
                    >
                        <li
                            v-for="(temper, tIndex) in weapon.tempered"
                            :key="`weapon-${index}-temper-${tIndex}`"
                        >
                            {{ temper.affix }}
                            <span class="text-[var(--fg-3)]">
                                — tempered<template v-if="temper.tier">
                                    T{{ temper.tier }}</template
                                >
                            </span>
                        </li>
                    </ul>

                    <div
                        v-if="weapon.runes?.length"
                        class="flex flex-wrap gap-1"
                    >
                        <span
                            v-for="rune in weapon.runes"
                            :key="`weapon-${index}-rune-${rune}`"
                            :class="
                                cn(
                                    chipClass,
                                    'border-[var(--border-strong)] bg-[var(--surface-card-hover)] text-[var(--fg-2)]',
                                )
                            "
                        >
                            {{ rune }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
