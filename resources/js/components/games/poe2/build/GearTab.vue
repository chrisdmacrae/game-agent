<script setup lang="ts">
import { computed } from 'vue';
import Card from '@/components/byb/Card.vue';
import { LABEL_CLASS } from '@/components/byb/controls';
import { socketSummary } from '@/components/games/poe2/build';
import EmptyBlock from '@/components/games/poe2/build/EmptyBlock.vue';
import GearScreen from '@/components/games/poe2/GearScreen.vue';
import type {
    Poe2BuildDefinition,
    Poe2GearView,
} from '@/components/games/poe2/types';

const props = defineProps<{
    definition: Poe2BuildDefinition;
    gearView: Poe2GearView;
}>();

const sockets = computed(() => socketSummary(props.definition.gear ?? []));

const hasGear = computed(
    () =>
        Object.keys(props.gearView.slots).length > 0 ||
        props.gearView.jewels.length > 0,
);

const charms = computed(() => props.definition.charms ?? []);
const flasks = computed(() => props.definition.flasks ?? []);
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex flex-wrap items-center gap-3">
            <p :class="LABEL_CLASS">Equipped</p>
            <span
                v-if="sockets"
                class="font-mono text-[12px] text-[var(--fg-3)]"
            >
                {{ sockets }}
            </span>
        </div>

        <GearScreen
            v-if="hasGear"
            :slots="gearView.slots"
            :jewels="gearView.jewels"
        />
        <EmptyBlock v-else message="No gear on this build yet." />

        <div
            v-if="charms.length || flasks.length"
            class="grid gap-4 md:grid-cols-2"
        >
            <Card v-if="charms.length">
                <p :class="LABEL_CLASS">Charms</p>
                <div class="mt-3 flex flex-col gap-2">
                    <div
                        v-for="charm in charms"
                        :key="charm.name"
                        class="flex items-center gap-3 rounded-[var(--radius-sm)] border border-[var(--border-hairline)] bg-[var(--surface-card-hover)] px-3 py-2"
                    >
                        <span
                            class="flex-1 text-[15px] font-semibold text-[var(--fg-1)]"
                        >
                            {{ charm.name }}
                        </span>
                        <span
                            v-if="charm.note"
                            class="font-mono text-[12px] text-[var(--fg-3)]"
                        >
                            {{ charm.note }}
                        </span>
                    </div>
                </div>
            </Card>

            <Card v-if="flasks.length">
                <p :class="LABEL_CLASS">Flasks</p>
                <div class="mt-3 flex flex-col gap-2">
                    <div
                        v-for="flask in flasks"
                        :key="flask.name"
                        class="flex items-center gap-3 rounded-[var(--radius-sm)] border border-[var(--border-hairline)] bg-[var(--surface-card-hover)] px-3 py-2"
                    >
                        <span
                            class="flex-1 text-[15px] font-semibold text-[var(--fg-1)]"
                        >
                            {{ flask.name }}
                        </span>
                        <span
                            v-if="flask.note"
                            class="font-mono text-[12px] text-[var(--fg-3)]"
                        >
                            {{ flask.note }}
                        </span>
                    </div>
                </div>
            </Card>
        </div>
    </div>
</template>
