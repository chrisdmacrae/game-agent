<script setup lang="ts">
import { ref } from 'vue';
import { LABEL_CLASS } from '@/components/byb/controls';
import { rarityColor } from '@/components/games/poe2/build';
import type { Poe2GearViewItem } from '@/components/games/poe2/types';
import { cn } from '@/lib/utils';

/**
 * The PoE 2 paperdoll: slots laid out the way the in-game inventory places
 * them, plus the weapon-swap set and jewels underneath. Hovering a cell opens
 * the item card with implicits, mods and the instilled notable.
 */
defineProps<{
    slots: Record<string, Poe2GearViewItem>;
    jewels: Poe2GearViewItem[];
}>();

const layout: { slot: string; label: string; area: string }[] = [
    { slot: 'weapon1', label: 'Weapon', area: 'w1' },
    { slot: 'helmet', label: 'Helmet', area: 'helm' },
    { slot: 'offhand1', label: 'Off-hand', area: 'o1' },
    { slot: 'ring1', label: 'Ring', area: 'r1' },
    { slot: 'body', label: 'Body armour', area: 'body' },
    { slot: 'ring2', label: 'Ring', area: 'r2' },
    { slot: 'gloves', label: 'Gloves', area: 'glove' },
    { slot: 'belt', label: 'Belt', area: 'belt' },
    { slot: 'boots', label: 'Boots', area: 'boot' },
    { slot: 'amulet', label: 'Amulet', area: 'amu' },
];

const swapLayout: { slot: string; label: string }[] = [
    { slot: 'weapon2', label: 'Weapon (set II)' },
    { slot: 'offhand2', label: 'Off-hand (set II)' },
];

const hovered = ref<Poe2GearViewItem | null>(null);
const cardStyle = ref<Record<string, string>>({});
const container = ref<HTMLDivElement | null>(null);

function onEnter(item: Poe2GearViewItem | undefined, event: MouseEvent): void {
    if (!item || !container.value) {
        return;
    }

    const rect = container.value.getBoundingClientRect();
    const target = (event.currentTarget as HTMLElement).getBoundingClientRect();
    const x = Math.min(Math.max(8, target.left - rect.left), rect.width - 300);

    cardStyle.value = {
        left: `${x}px`,
        top: `${target.bottom - rect.top + 6}px`,
    };
    hovered.value = item;
}

function onLeave(): void {
    hovered.value = null;
}

function itemLabel(item: Poe2GearViewItem, fallback: string): string {
    return item.name ?? item.base ?? fallback;
}

const socketChipClass =
    'inline-flex items-center rounded-[var(--radius-xs)] px-1.5 py-0.5 font-mono text-[10px] leading-none font-bold tracking-[0.14em] uppercase';
</script>

<template>
    <div ref="container" class="relative">
        <div class="gear-grid gap-3">
            <div
                v-for="cell in layout"
                :key="cell.slot"
                :style="{ gridArea: cell.area }"
                :class="
                    cn(
                        'flex min-h-28 flex-col items-center justify-center gap-1.5 rounded-[var(--radius-md)] border p-2.5 text-center [transition:var(--transition-control)]',
                        slots[cell.slot]
                            ? 'cursor-help border-[var(--border-subtle)] bg-[var(--surface-card)] [box-shadow:var(--shadow-1)] hover:border-[var(--border-strong)]'
                            : 'border-dashed border-[var(--border-subtle)] bg-transparent',
                    )
                "
                @mouseenter="onEnter(slots[cell.slot], $event)"
                @mouseleave="onLeave"
            >
                <template v-if="slots[cell.slot]">
                    <img
                        v-if="slots[cell.slot].icon"
                        :src="slots[cell.slot].icon!"
                        :alt="itemLabel(slots[cell.slot], cell.label)"
                        class="max-h-14 object-contain"
                    />
                    <span
                        v-else
                        class="flex size-10 items-center justify-center rounded-[var(--radius-xs)] border border-[var(--border-subtle)] font-mono text-[15px] font-bold text-[var(--fg-3)]"
                    >
                        {{ itemLabel(slots[cell.slot], cell.label).charAt(0) }}
                    </span>
                    <p
                        class="line-clamp-2 text-[13px] leading-tight font-semibold"
                        :style="{ color: rarityColor(slots[cell.slot].rarity) }"
                    >
                        {{ itemLabel(slots[cell.slot], cell.label) }}
                    </p>
                    <p
                        v-if="slots[cell.slot].instill"
                        class="font-mono text-[10px] tracking-[0.14em] text-[var(--violet-400)] uppercase"
                    >
                        Instilled
                    </p>
                    <div
                        v-if="slots[cell.slot].runes?.length"
                        class="mt-auto flex flex-wrap justify-center gap-1 pt-1"
                    >
                        <span
                            v-for="(rune, index) in slots[cell.slot].runes"
                            :key="`${cell.slot}-socket-${index}`"
                            :class="
                                cn(
                                    socketChipClass,
                                    rune
                                        ? 'border border-solid border-[var(--border-strong)] bg-[var(--surface-card-hover)] text-[var(--fg-2)]'
                                        : 'border border-dashed border-[var(--ink-500)] text-[var(--fg-3)]',
                                )
                            "
                        >
                            {{ rune || 'empty socket' }}
                        </span>
                    </div>
                </template>
                <template v-else>
                    <p :class="LABEL_CLASS">{{ cell.label }}</p>
                </template>
            </div>
        </div>

        <!-- Weapon swap + jewels row -->
        <div
            v-if="swapLayout.some((cell) => slots[cell.slot]) || jewels.length"
            class="mt-3 flex flex-wrap gap-3"
        >
            <div
                v-for="cell in swapLayout.filter((c) => slots[c.slot])"
                :key="cell.slot"
                class="flex min-w-32 cursor-help flex-col items-center gap-1.5 rounded-[var(--radius-md)] border border-[var(--border-subtle)] bg-[var(--surface-card)] p-2.5 text-center [box-shadow:var(--shadow-1)] [transition:var(--transition-control)] hover:border-[var(--border-strong)]"
                @mouseenter="onEnter(slots[cell.slot], $event)"
                @mouseleave="onLeave"
            >
                <img
                    v-if="slots[cell.slot].icon"
                    :src="slots[cell.slot].icon!"
                    :alt="cell.label"
                    class="max-h-12 object-contain"
                />
                <p
                    class="text-[13px] font-semibold"
                    :style="{ color: rarityColor(slots[cell.slot].rarity) }"
                >
                    {{ itemLabel(slots[cell.slot], cell.label) }}
                </p>
                <p :class="LABEL_CLASS">{{ cell.label }}</p>
            </div>
            <div
                v-for="(jewel, index) in jewels"
                :key="`jewel-${index}`"
                class="flex min-w-32 cursor-help flex-col items-center gap-1.5 rounded-[var(--radius-md)] border border-[var(--border-subtle)] bg-[var(--surface-card)] p-2.5 text-center [box-shadow:var(--shadow-1)] [transition:var(--transition-control)] hover:border-[var(--border-strong)]"
                @mouseenter="onEnter(jewel, $event)"
                @mouseleave="onLeave"
            >
                <img
                    v-if="jewel.icon"
                    :src="jewel.icon!"
                    :alt="jewel.name ?? 'Jewel'"
                    class="max-h-12 object-contain"
                />
                <p
                    class="text-[13px] font-semibold"
                    :style="{ color: rarityColor(jewel.rarity) }"
                >
                    {{ jewel.name }}
                </p>
                <p :class="LABEL_CLASS">Jewel</p>
            </div>
        </div>

        <!-- Item card -->
        <div
            v-if="hovered"
            class="pointer-events-none absolute z-10 w-[290px] rounded-[var(--radius-md)] border border-[var(--border-subtle)] bg-[var(--surface-raised)] p-3 [box-shadow:var(--shadow-2)]"
            :style="cardStyle"
        >
            <p
                class="text-[15px] font-semibold"
                :style="{ color: rarityColor(hovered.rarity) }"
            >
                {{ hovered.name ?? hovered.base }}
            </p>
            <p
                v-if="hovered.name && hovered.base"
                class="font-mono text-[12px] text-[var(--fg-3)]"
            >
                {{ hovered.base }}
            </p>
            <ul
                v-if="hovered.implicits.length"
                class="mt-2 border-b border-[var(--border-hairline)] pb-2 font-mono text-[12px] text-[var(--fg-3)]"
            >
                <li v-for="implicit in hovered.implicits" :key="implicit">
                    {{ implicit }}
                </li>
            </ul>
            <ul
                v-if="hovered.mods.length"
                class="mt-2 space-y-0.5 font-mono text-[12px] text-[var(--fg-2)]"
            >
                <li v-for="mod in hovered.mods" :key="mod">{{ mod }}</li>
            </ul>
            <p
                v-if="hovered.instill"
                class="mt-2 text-[13px] text-[var(--violet-400)]"
            >
                Instilled: {{ hovered.instill.notable }}
                <span
                    v-if="hovered.instill.emotions?.length"
                    class="text-[var(--fg-3)]"
                >
                    ({{ hovered.instill.emotions.join(' + ') }})
                </span>
            </p>
        </div>
    </div>
</template>

<style scoped>
.gear-grid {
    display: grid;
    grid-template-areas:
        'w1 helm o1'
        'w1 body o1'
        'r1 body r2'
        'glove belt boot'
        '. amu .';
    grid-template-columns: 1fr 1fr 1fr;
}
</style>
