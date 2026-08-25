<script setup lang="ts">
import { computed } from 'vue';
import Card from '@/components/byb/Card.vue';
import { LABEL_CLASS } from '@/components/byb/controls';
import Input from '@/components/byb/Input.vue';
import { passivePointBudget } from '@/components/games/poe2/build';
import EmptyBlock from '@/components/games/poe2/build/EmptyBlock.vue';
import PassiveTreeView from '@/components/games/poe2/PassiveTreeView.vue';
import type { Poe2BuildDefinition } from '@/components/games/poe2/types';

/**
 * The tree is read-only here on purpose: click-to-allocate is deferred, so the
 * card shows the point budget, the current allocation, and the import string
 * the owner pasted out of the in-game planner.
 */
const props = defineProps<{
    definition: Poe2BuildDefinition;
    spriteUrl: string;
    treeUrl: string | null;
    ascendancyKey: string | null;
    ascendancyPathIds: number[];
    error?: string;
}>();

const importString = defineModel<string | number | null>({ required: true });

const passives = computed(() => props.definition.passives ?? {});

const keyPassives = computed(() => [
    ...(passives.value.keystones ?? []),
    ...(passives.value.notables ?? []),
]);

const pointsUsed = computed(
    () => passives.value.points_used ?? passives.value.node_ids?.length ?? null,
);

const budget = computed(() => passivePointBudget(props.definition.level));

const overBudget = computed(
    () =>
        pointsUsed.value !== null &&
        budget.value > 0 &&
        pointsUsed.value > budget.value,
);

const hasTree = computed(
    () =>
        Boolean(props.treeUrl) &&
        ((passives.value.node_ids?.length ?? 0) > 0 ||
            keyPassives.value.length > 0),
);
</script>

<template>
    <Card padding="var(--sp-7)">
        <div class="flex flex-wrap items-center gap-5">
            <div>
                <p :class="LABEL_CLASS">Passive tree</p>
                <p
                    class="mt-1 font-mono text-[14px]"
                    :style="{
                        color: overBudget ? 'var(--red-400)' : 'var(--fg-1)',
                    }"
                >
                    <template v-if="pointsUsed !== null">
                        {{ pointsUsed }}
                        <template v-if="budget">/ {{ budget }}</template>
                        points
                    </template>
                    <template v-else>No allocation saved</template>
                </p>
            </div>
            <p
                class="ml-auto max-w-[300px] text-right text-[13px] [text-wrap:pretty] text-[var(--fg-3)]"
            >
                Allocation is set by your assistant. This preview is read-only.
            </p>
        </div>

        <PassiveTreeView
            v-if="hasTree && treeUrl"
            class="mt-4"
            :tree-url="treeUrl"
            :sprite-url="spriteUrl"
            :highlight-names="keyPassives"
            :ascendancy-nodes="passives.ascendancy_nodes ?? []"
            :node-ids="passives.node_ids ?? []"
            :granted-ids="
                (passives.granted_nodes ?? []).map((node) => node.node_id)
            "
            :class-name="definition.class ?? undefined"
            :ascendancy-key="ascendancyKey"
            :ascendancy-name="definition.ascendancy ?? undefined"
            :ascendancy-path-ids="ascendancyPathIds"
        />
        <EmptyBlock
            v-else
            class="mt-4"
            message="No passives saved yet. Ask your assistant to allocate them."
        />

        <Input
            v-model="importString"
            class="mt-4"
            label="Passive tree import string"
            mono
            hint="Pastes into the in-game planner."
            :error="error"
        />
    </Card>
</template>
