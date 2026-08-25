<script setup lang="ts">
import { computed } from 'vue';
import Card from '@/components/byb/Card.vue';
import { LABEL_CLASS } from '@/components/byb/controls';
import { passivePointBudget, spriteStyle } from '@/components/games/poe2/build';
import EmptyBlock from '@/components/games/poe2/build/EmptyBlock.vue';
import PassiveTreeView from '@/components/games/poe2/PassiveTreeView.vue';
import type {
    Poe2BuildDefinition,
    Poe2Entity,
} from '@/components/games/poe2/types';

const props = defineProps<{
    definition: Poe2BuildDefinition;
    entityFor: (name: string) => Poe2Entity | null;
    spriteUrl: string;
    treeUrl: string | null;
    ascendancyKey: string | null;
    ascendancyPathIds: number[];
}>();

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
    <div class="flex flex-col gap-4">
        <Card>
            <div class="flex flex-wrap items-center gap-5">
                <div>
                    <p :class="LABEL_CLASS">Passive tree</p>
                    <p
                        class="mt-1 font-mono text-[14px]"
                        :style="{
                            color: overBudget
                                ? 'var(--red-400)'
                                : 'var(--fg-1)',
                        }"
                    >
                        <template v-if="pointsUsed !== null">
                            {{ pointsUsed }}
                            <template v-if="budget">/ {{ budget }}</template>
                            points
                        </template>
                        <template v-else>Key nodes highlighted</template>
                    </p>
                </div>
                <p
                    class="ml-auto max-w-[280px] text-right text-[13px] text-[var(--fg-3)]"
                >
                    Scroll to zoom, drag to pan. Allocation is not editable
                    here.
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
                message="No passives recorded on this build yet."
            />
        </Card>

        <Card v-if="keyPassives.length">
            <p :class="LABEL_CLASS">Key passives</p>
            <ul class="mt-4 flex flex-col gap-2">
                <li
                    v-for="passive in keyPassives"
                    :key="passive"
                    class="flex items-center gap-2 text-[13px]"
                >
                    <span
                        v-if="entityFor(passive)?.sprite"
                        class="inline-block shrink-0 rounded-[var(--radius-xs)]"
                        :style="spriteStyle(entityFor(passive)!, spriteUrl)!"
                    />
                    <span
                        class="entity-ref"
                        :class="
                            entityFor(passive)?.passive_kind === 'keystone'
                                ? 'text-[var(--teal-300)]'
                                : 'text-[var(--fg-2)]'
                        "
                        :data-entity="passive"
                    >
                        {{ passive }}
                    </span>
                    <span
                        v-if="entityFor(passive)?.passive_kind === 'keystone'"
                        class="font-mono text-[11px] tracking-[0.14em] text-[var(--fg-3)] uppercase"
                    >
                        Keystone
                    </span>
                </li>
            </ul>
        </Card>
    </div>
</template>
