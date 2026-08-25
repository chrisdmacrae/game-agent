<script setup lang="ts">
import { ref } from 'vue';

export interface GearViewItem {
    slot: string | null;
    rarity: string;
    name: string | null;
    base: string | null;
    icon: string | null;
    implicits: string[];
    mods: string[];
    instill?: { notable: string; emotions?: string[] } | null;
}

const props = defineProps<{
    slots: Record<string, GearViewItem>;
    jewels: GearViewItem[];
}>();

// Paper-doll layout via grid areas, roughly matching the in-game inventory.
const layout: { slot: string; label: string; area: string }[] = [
    { slot: 'weapon1', label: 'Weapon', area: 'w1' },
    { slot: 'helmet', label: 'Helmet', area: 'helm' },
    { slot: 'offhand1', label: 'Off-hand', area: 'o1' },
    { slot: 'ring1', label: 'Ring', area: 'r1' },
    { slot: 'body', label: 'Body Armour', area: 'body' },
    { slot: 'ring2', label: 'Ring', area: 'r2' },
    { slot: 'gloves', label: 'Gloves', area: 'glove' },
    { slot: 'belt', label: 'Belt', area: 'belt' },
    { slot: 'boots', label: 'Boots', area: 'boot' },
    { slot: 'amulet', label: 'Amulet', area: 'amu' },
];

const swapLayout: { slot: string; label: string }[] = [
    { slot: 'weapon2', label: 'Weapon (Set II)' },
    { slot: 'offhand2', label: 'Off-hand (Set II)' },
];

const rarityStyles: Record<string, { name: string; border: string }> = {
    unique: { name: 'text-orange-300', border: 'border-orange-500/50' },
    rare: { name: 'text-yellow-200', border: 'border-yellow-500/40' },
    magic: { name: 'text-blue-300', border: 'border-blue-500/40' },
    normal: { name: 'text-zinc-200', border: 'border-zinc-600' },
};

const hovered = ref<GearViewItem | null>(null);
const cardStyle = ref<Record<string, string>>({});
const container = ref<HTMLDivElement | null>(null);

function onEnter(item: GearViewItem | undefined, event: MouseEvent) {
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

function onLeave() {
    hovered.value = null;
}
</script>

<template>
    <div ref="container" class="relative">
        <div class="gear-grid gap-2">
            <div
                v-for="cell in layout"
                :key="cell.slot"
                :style="{ gridArea: cell.area }"
                class="flex min-h-24 flex-col items-center justify-center rounded-lg border p-2 text-center"
                :class="
                    slots[cell.slot]
                        ? `bg-zinc-900/70 ${rarityStyles[slots[cell.slot].rarity]?.border ?? 'border-zinc-700'} cursor-help`
                        : 'border-dashed border-zinc-800 bg-zinc-950'
                "
                @mouseenter="onEnter(slots[cell.slot], $event)"
                @mouseleave="onLeave"
            >
                <template v-if="slots[cell.slot]">
                    <img
                        v-if="slots[cell.slot].icon"
                        :src="slots[cell.slot].icon!"
                        :alt="slots[cell.slot].name ?? cell.label"
                        class="max-h-14 object-contain"
                    />
                    <span
                        v-else
                        class="flex h-10 w-10 items-center justify-center rounded border border-zinc-700 text-lg font-bold text-zinc-500"
                        >{{
                            (slots[cell.slot].name ?? cell.label).charAt(0)
                        }}</span
                    >
                    <p
                        class="mt-1 line-clamp-2 text-xs leading-tight font-medium"
                        :class="rarityStyles[slots[cell.slot].rarity]?.name"
                    >
                        {{
                            slots[cell.slot].name ??
                            slots[cell.slot].base ??
                            cell.label
                        }}
                    </p>
                    <p
                        v-if="slots[cell.slot].instill"
                        class="text-[10px] text-violet-400"
                    >
                        instilled
                    </p>
                </template>
                <template v-else>
                    <p class="text-xs text-zinc-700">{{ cell.label }}</p>
                </template>
            </div>
        </div>

        <!-- Weapon swap + jewels row -->
        <div
            v-if="swapLayout.some((cell) => slots[cell.slot]) || jewels.length"
            class="mt-2 flex flex-wrap gap-2"
        >
            <div
                v-for="cell in swapLayout.filter((c) => slots[c.slot])"
                :key="cell.slot"
                class="flex min-w-28 cursor-help flex-col items-center rounded-lg border bg-zinc-900/70 p-2 text-center"
                :class="
                    rarityStyles[slots[cell.slot].rarity]?.border ??
                    'border-zinc-700'
                "
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
                    class="mt-1 text-xs font-medium"
                    :class="rarityStyles[slots[cell.slot].rarity]?.name"
                >
                    {{
                        slots[cell.slot].name ??
                        slots[cell.slot].base ??
                        cell.label
                    }}
                </p>
                <p class="text-[10px] text-zinc-600">{{ cell.label }}</p>
            </div>
            <div
                v-for="(jewel, index) in jewels"
                :key="`j${index}`"
                class="flex min-w-28 cursor-help flex-col items-center rounded-lg border bg-zinc-900/70 p-2 text-center"
                :class="rarityStyles[jewel.rarity]?.border ?? 'border-zinc-700'"
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
                    class="mt-1 text-xs font-medium"
                    :class="rarityStyles[jewel.rarity]?.name"
                >
                    {{ jewel.name }}
                </p>
                <p class="text-[10px] text-zinc-600">Jewel</p>
            </div>
        </div>

        <!-- Item card -->
        <div
            v-if="hovered"
            class="pointer-events-none absolute z-10 w-[290px] rounded-lg border border-zinc-700 bg-zinc-900 p-3 shadow-xl shadow-black/50"
            :style="cardStyle"
        >
            <p
                class="font-semibold"
                :class="rarityStyles[hovered.rarity]?.name"
            >
                {{ hovered.name ?? hovered.base }}
            </p>
            <p
                v-if="hovered.name && hovered.base"
                class="text-xs text-zinc-500"
            >
                {{ hovered.base }}
            </p>
            <ul
                v-if="hovered.implicits.length"
                class="mt-1.5 border-b border-zinc-800 pb-1.5 text-sm text-zinc-400"
            >
                <li v-for="implicit in hovered.implicits" :key="implicit">
                    {{ implicit }}
                </li>
            </ul>
            <ul
                v-if="hovered.mods.length"
                class="mt-1.5 space-y-0.5 text-sm text-sky-200/80"
            >
                <li v-for="mod in hovered.mods" :key="mod">{{ mod }}</li>
            </ul>
            <p v-if="hovered.instill" class="mt-1.5 text-sm text-violet-300">
                Instilled: {{ hovered.instill.notable }}
                <span
                    v-if="hovered.instill.emotions?.length"
                    class="text-zinc-500"
                    >({{ hovered.instill.emotions.join(' + ') }})</span
                >
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
