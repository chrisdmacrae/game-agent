<script setup lang="ts">
import { computed } from 'vue';
import Card from '@/components/byb/Card.vue';
import { LABEL_CLASS } from '@/components/byb/controls';
import { PARAGON_UNLOCK_LEVEL } from '@/components/games/diablo-4/build';
import EmptyBlock from '@/components/games/diablo-4/build/EmptyBlock.vue';
import ParagonView from '@/components/games/diablo-4/ParagonView.vue';
import { D4_MAX_PARAGON_BOARDS } from '@/components/games/diablo-4/types';
import type {
    D4BuildDefinition,
    D4ParagonBoardGrid,
} from '@/components/games/diablo-4/types';

const props = defineProps<{
    definition: D4BuildDefinition;
    boards?: D4ParagonBoardGrid[];
}>();

const entries = computed(() => props.definition.paragon ?? []);

/** Boards unlock at 60, so a leveling guide having none is not a gap. */
const belowUnlock = computed(
    () =>
        typeof props.definition.level === 'number' &&
        props.definition.level < PARAGON_UNLOCK_LEVEL,
);

const glyphs = computed(() =>
    entries.value
        .filter((entry) => entry.glyph)
        .map((entry) => ({
            board: entry.board,
            glyph: entry.glyph as string,
            level: entry.glyph_level ?? null,
        })),
);
</script>

<template>
    <div class="flex flex-col gap-4">
        <Card>
            <div class="flex flex-wrap items-center gap-5">
                <div>
                    <p :class="LABEL_CLASS">Paragon</p>
                    <p class="mt-1 font-mono text-[14px] text-[var(--fg-1)]">
                        {{ entries.length }} of
                        {{ D4_MAX_PARAGON_BOARDS }} boards
                    </p>
                </div>
                <p
                    class="ml-auto max-w-[300px] text-right text-[13px] text-[var(--fg-3)]"
                >
                    Boards attach in the order listed, each turned to meet the
                    one before it.
                </p>
            </div>

            <EmptyBlock
                v-if="entries.length === 0 && belowUnlock"
                class="mt-4"
                :message="`Paragon unlocks at level ${PARAGON_UNLOCK_LEVEL}; this build is below it.`"
            />
            <ParagonView
                v-else
                class="mt-4"
                :entries="entries"
                :boards="boards"
            />
        </Card>

        <Card v-if="glyphs.length">
            <p :class="LABEL_CLASS">Glyphs</p>
            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                <div
                    v-for="glyph in glyphs"
                    :key="`${glyph.board}-${glyph.glyph}`"
                    class="flex items-center gap-3 rounded-[var(--radius-sm)] border border-[var(--border-hairline)] bg-[var(--surface-card-hover)] px-3 py-2"
                >
                    <span
                        class="flex-1 text-[15px] font-semibold text-[var(--violet-400)]"
                    >
                        {{ glyph.glyph }}
                    </span>
                    <span class="font-mono text-[12px] text-[var(--fg-3)]">
                        {{ glyph.board
                        }}<template v-if="glyph.level">
                            · lvl {{ glyph.level }}</template
                        >
                    </span>
                </div>
            </div>
        </Card>
    </div>
</template>
