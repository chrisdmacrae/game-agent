<script setup lang="ts">
import { computed } from 'vue';
import { LABEL_CLASS } from '@/components/byb/controls';
import { D4_ACCENT, glyphMeta } from '@/components/games/diablo-4/build';
import EmptyBlock from '@/components/games/diablo-4/build/EmptyBlock.vue';
import ParagonCanvas from '@/components/games/diablo-4/ParagonCanvas.vue';
import type {
    D4ParagonBoardGrid,
    D4ParagonCell,
    D4ParagonEntry,
} from '@/components/games/diablo-4/types';

/**
 * The Diablo IV paragon plan.
 *
 * The build payload is the only thing this needs — a board is a name, a
 * rotation, a glyph and the notables the plan reaches, rendered as cards.
 * Grid data is a bonus: when the page has the imported
 * `d4_paragon_boards.grid` for the named boards, the plan additionally
 * renders as one pannable canvas of welded boards (ParagonCanvas) with the
 * allocated path lit. Boards with no grid stay card-only, which is what
 * every board does until the server sends the grids.
 */
const props = defineProps<{
    entries: D4ParagonEntry[];
    /** Imported grids, matched to entries by board name. Optional by design. */
    boards?: D4ParagonBoardGrid[];
    /** The editor turns canvas cells into buttons that toggle allocation. */
    editable?: boolean;
}>();

const emit = defineEmits<{
    /** A cell was clicked, addressed in PRE-rotation grid coordinates. */
    (
        event: 'toggle-node',
        entryIndex: number,
        node: { row: number; col: number },
        cell: D4ParagonCell,
    ): void;
}>();

const hasEntries = computed(() => props.entries.length > 0);

const gridNames = computed(
    () =>
        new Set(
            (props.boards ?? [])
                .filter((board) => board.grid.length > 0)
                .map((board) => board.name.toLowerCase()),
        ),
);

const hasCanvas = computed(() =>
    props.entries.some((entry) => gridNames.value.has(entry.board.toLowerCase())),
);

const totalPoints = computed(() =>
    props.entries.reduce((total, entry) => total + (entry.nodes?.length ?? 0), 0),
);

/** Only the vocabulary the canvas actually draws. */
const LEGEND = [
    { label: 'Allocated path', color: '#c79b5a' },
    { label: 'Start', color: '#f0d9a0' },
    { label: 'Glyph socket', color: '#9c7bff' },
    { label: 'Rare', color: '#ffc857' },
    { label: 'Magic', color: '#5aa9ff' },
    { label: 'Legendary', color: '#ff5a5f' },
] as const;
</script>

<template>
    <div class="flex flex-col gap-3">
        <EmptyBlock
            v-if="!hasEntries"
            message="No paragon boards on this build yet."
        />

        <template v-else>
            <!-- The tree itself: all boards with grid data, welded together. -->
            <template v-if="hasCanvas">
                <ParagonCanvas
                    :entries="entries"
                    :boards="boards ?? []"
                    :editable="editable"
                    @toggle-node="
                        (index, node, cell) =>
                            emit('toggle-node', index, node, cell)
                    "
                />

                <div
                    class="flex flex-wrap items-center gap-x-4 gap-y-1.5 font-mono text-[11px] text-[var(--fg-3)]"
                >
                    <span
                        v-if="totalPoints > 0"
                        class="font-bold text-[var(--fg-2)]"
                    >
                        {{ totalPoints }} points allocated
                    </span>
                    <span
                        v-for="key in LEGEND"
                        :key="`legend-${key.label}`"
                        class="inline-flex items-center gap-1.5"
                    >
                        <span
                            class="size-2 shrink-0 rounded-[2px]"
                            :style="{ background: key.color }"
                        />
                        {{ key.label }}
                    </span>
                </div>
            </template>

            <!-- The plan as cards: attachment order, glyphs and notables.
                 This is the whole rendering when no grids were sent. -->
            <div
                v-for="(entry, order) in entries"
                :key="`board-${order}-${entry.board}`"
                class="flex flex-col gap-2 rounded-[var(--radius-md)] border border-[var(--border-subtle)] bg-[var(--surface-card)] p-4 [box-shadow:var(--shadow-1)]"
            >
                <div class="flex flex-wrap items-center gap-3">
                    <span
                        class="flex size-7 shrink-0 items-center justify-center rounded-[var(--radius-xs)] border border-[var(--border-subtle)] bg-[var(--surface-card-hover)] font-mono text-[12px] font-bold"
                        :style="{ color: D4_ACCENT }"
                    >
                        {{ order + 1 }}
                    </span>
                    <div class="min-w-0">
                        <p
                            class="text-[15px] leading-tight font-semibold text-[var(--fg-1)]"
                        >
                            {{ entry.board }}
                        </p>
                        <p
                            v-if="glyphMeta(entry)"
                            class="mt-0.5 font-mono text-[12px] text-[var(--violet-400)]"
                            :data-entity="entry.glyph ?? undefined"
                        >
                            {{ glyphMeta(entry) }}
                        </p>
                    </div>
                    <span
                        class="ml-auto font-mono text-[12px] text-[var(--fg-3)]"
                    >
                        <template v-if="entry.nodes?.length">
                            {{ entry.nodes.length }} pts ·
                        </template>
                        rotated {{ entry.rotation ?? 0 }}°
                    </span>
                </div>

                <div v-if="entry.notables?.length" class="flex flex-col gap-2">
                    <p :class="LABEL_CLASS">Notables</p>
                    <div class="flex flex-wrap gap-1.5">
                        <span
                            v-for="notable in entry.notables"
                            :key="`${order}-${notable}`"
                            class="inline-flex items-center rounded-[var(--radius-pill)] border border-[var(--border-subtle)] bg-[var(--surface-card-hover)] px-2.5 py-1 font-mono text-[12px] leading-none text-[var(--fg-2)]"
                            :data-entity="notable"
                        >
                            {{ notable }}
                        </span>
                    </div>
                </div>
            </div>

            <p
                v-if="!hasCanvas"
                class="font-mono text-[12px] text-[var(--fg-3)]"
            >
                Board layouts are not rendered yet — the plan above is what the
                publisher recorded.
            </p>
        </template>
    </div>
</template>
