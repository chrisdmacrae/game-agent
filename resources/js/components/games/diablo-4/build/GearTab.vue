<script setup lang="ts">
import { computed } from 'vue';
import { LABEL_CLASS } from '@/components/byb/controls';
import {
    equippedSummary,
    gearCells,
    weaponList,
} from '@/components/games/diablo-4/build';
import EmptyBlock from '@/components/games/diablo-4/build/EmptyBlock.vue';
import GearScreen from '@/components/games/diablo-4/GearScreen.vue';
import type { D4BuildDefinition } from '@/components/games/diablo-4/types';

const props = defineProps<{
    definition: D4BuildDefinition;
}>();

const gear = computed(() => props.definition.gear ?? null);

const summary = computed(() => equippedSummary(gear.value));

const hasGear = computed(
    () =>
        gearCells(gear.value).some((cell) => cell.item !== null) ||
        weaponList(gear.value).length > 0,
);
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex flex-wrap items-center gap-3">
            <p :class="LABEL_CLASS">Equipped</p>
            <span
                v-if="summary"
                class="font-mono text-[12px] text-[var(--fg-3)]"
            >
                {{ summary }}
            </span>
        </div>

        <GearScreen v-if="hasGear" :gear="gear" />
        <EmptyBlock v-else message="No gear on this build yet." />
    </div>
</template>
