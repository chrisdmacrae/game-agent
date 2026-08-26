<script setup lang="ts">
import { computed } from 'vue';
import { LABEL_CLASS } from '@/components/byb/controls';
import { D4_ACCENT, glyphMeta } from '@/components/games/diablo-4/build';
import EmptyBlock from '@/components/games/diablo-4/build/EmptyBlock.vue';
import type {
    D4ParagonBoardGrid,
    D4ParagonCell,
    D4ParagonEntry,
} from '@/components/games/diablo-4/types';

/**
 * The Diablo IV paragon plan: the boards a character attaches, in the order
 * they are attached, drawn as a vertical chain.
 *
 * The build payload is the only thing this needs — a board is a name, a
 * rotation, a glyph and the notables the plan reaches. Grid data is a bonus:
 * when the page has the imported `d4_paragon_boards.grid` for a board, the same
 * card grows an SVG of the board turned the way the entry says, with the glyph
 * socket marked. Boards with no grid render as a labelled card, which is what
 * every board does until the server sends the grids.
 */
const props = defineProps<{
    entries: D4ParagonEntry[];
    /** Imported grids, matched to entries by board name. Optional by design. */
    boards?: D4ParagonBoardGrid[];
}>();

/**
 * SVG presentation attributes cannot read CSS custom properties, so the design
 * system tokens are resolved to their hex values once here. Keep in step with
 * `resources/css/byb/colors.css`.
 */
const BOARD_COLORS = {
    /** --ink-900: the board plate behind the cells. */
    plate: '#0E1116',
    /** --ink-800: a cell that exists but holds nothing notable. */
    empty: '#131820',
    /** --ink-500: the hairline around every cell. */
    hairline: '#2F3A49',
    /** --ink-600: a plain attribute node. */
    normal: '#242D3A',
    /** --blue-400: magic node. */
    magic: '#5AA9FF',
    /** --gold-400: rare node. */
    rare: '#FFC857',
    /** --red-400: legendary node, and the Diablo IV accent. */
    legendary: '#FF5A5F',
    /** --violet-400: the glyph socket. */
    socket: '#9C7BFF',
    /** --fg-2: gate markers and labels. */
    gate: '#A2B1C2',
    /** --fg-3: dimmed label text. */
    label: '#6C7C8F',
} as const;

const RARITY_FILL: Record<string, string> = {
    normal: BOARD_COLORS.normal,
    magic: BOARD_COLORS.magic,
    rare: BOARD_COLORS.rare,
    legendary: BOARD_COLORS.legendary,
};

/** One cell is 10 units wide with a 2-unit gutter. */
const CELL = 10;
const GUTTER = 2;
const STEP = CELL + GUTTER;

const boardsByName = computed(() => {
    const index = new Map<string, D4ParagonBoardGrid>();

    for (const board of props.boards ?? []) {
        index.set(board.name.toLowerCase(), board);
    }

    return index;
});

type DrawnCell = {
    key: string;
    x: number;
    y: number;
    cell: D4ParagonCell;
};

type DrawnBoard = {
    entry: D4ParagonEntry;
    order: number;
    width: number;
    height: number;
    cells: DrawnCell[];
    sockets: DrawnCell[];
};

/**
 * Turn the matrix rather than the drawing. Rotating the data means the cells,
 * the glyph socket and its label all come out in final orientation, so nothing
 * has to be counter-rotated to stay readable.
 */
function rotate(
    grid: (D4ParagonCell | null)[][],
    degrees: number,
): (D4ParagonCell | null)[][] {
    const size = Math.max(grid.length, ...grid.map((row) => row.length), 1);
    const square: (D4ParagonCell | null)[][] = Array.from(
        { length: size },
        (_, row) =>
            Array.from({ length: size }, (_, col) => grid[row]?.[col] ?? null),
    );

    const turns = (((degrees % 360) + 360) % 360) / 90;

    if (!Number.isInteger(turns) || turns === 0) {
        return square;
    }

    let result = square;

    for (let turn = 0; turn < turns; turn += 1) {
        const source = result;
        result = Array.from({ length: size }, (_, row) =>
            Array.from(
                { length: size },
                (_, col) => source[size - 1 - col][row],
            ),
        );
    }

    return result;
}

/** The entries that have grid data, laid out and ready to draw. */
const drawn = computed<Record<number, DrawnBoard>>(() => {
    const output: Record<number, DrawnBoard> = {};

    props.entries.forEach((entry, order) => {
        const board = boardsByName.value.get(entry.board.toLowerCase());

        if (!board || board.grid.length === 0) {
            return;
        }

        const grid = rotate(board.grid, entry.rotation ?? 0);

        // The imported grid is a fixed square with blank rows and columns
        // around the board; crop to what is actually there so the drawing
        // fills its box instead of floating in padding.
        const occupied: { row: number; col: number; cell: D4ParagonCell }[] =
            [];

        grid.forEach((row, rowIndex) => {
            row.forEach((cell, colIndex) => {
                if (cell) {
                    occupied.push({ row: rowIndex, col: colIndex, cell });
                }
            });
        });

        if (occupied.length === 0) {
            return;
        }

        const minRow = Math.min(...occupied.map((entry) => entry.row));
        const minCol = Math.min(...occupied.map((entry) => entry.col));
        const maxRow = Math.max(...occupied.map((entry) => entry.row));
        const maxCol = Math.max(...occupied.map((entry) => entry.col));

        const cells: DrawnCell[] = [];
        const sockets: DrawnCell[] = [];

        for (const { row, col, cell } of occupied) {
            const drawnCell: DrawnCell = {
                key: `${order}-${row}-${col}`,
                x: (col - minCol) * STEP,
                y: (row - minRow) * STEP,
                cell,
            };

            cells.push(drawnCell);

            if (cell.has_socket) {
                sockets.push(drawnCell);
            }
        }

        output[order] = {
            entry,
            order,
            width: (maxCol - minCol + 1) * STEP - GUTTER,
            height: (maxRow - minRow + 1) * STEP - GUTTER,
            cells,
            sockets,
        };
    });

    return output;
});

function fillFor(cell: D4ParagonCell): string {
    if (cell.has_socket) {
        return BOARD_COLORS.plate;
    }

    return RARITY_FILL[cell.rarity ?? 'normal'] ?? BOARD_COLORS.empty;
}

function strokeFor(cell: D4ParagonCell): string {
    if (cell.has_socket) {
        return BOARD_COLORS.socket;
    }

    return cell.is_gate ? BOARD_COLORS.gate : BOARD_COLORS.hairline;
}

/** A datamined key like `Generic_Normal_Will` is not a name worth printing. */
function cellTitle(cell: D4ParagonCell): string {
    const name = cell.name ?? cell.key ?? 'Node';

    return name.replace(/_/g, ' ');
}

/** The socket names its glyph on hover; the legend names it in the open. */
function socketTitle(entry: D4ParagonEntry): string {
    return entry.glyph ? `Glyph socket — ${entry.glyph}` : 'Glyph socket';
}

function rotationLabel(entry: D4ParagonEntry): string {
    return `${entry.rotation ?? 0}°`;
}

type LegendKey = { label: string; color: string; dashed?: boolean };

/** Only the keys this board actually uses, so the legend stays short. */
function legendFor(order: number): LegendKey[] {
    const board = drawn.value[order];

    if (!board) {
        return [];
    }

    const cells = board.cells.map((drawnCell) => drawnCell.cell);
    const keys: LegendKey[] = [];

    if (board.sockets.length > 0) {
        keys.push({
            label: board.entry.glyph
                ? `Glyph socket · ${board.entry.glyph}`
                : 'Glyph socket',
            color: BOARD_COLORS.socket,
        });
    }

    for (const rarity of ['legendary', 'rare', 'magic'] as const) {
        if (cells.some((cell) => cell.rarity === rarity)) {
            keys.push({
                label: rarity.charAt(0).toUpperCase() + rarity.slice(1),
                color: RARITY_FILL[rarity],
            });
        }
    }

    if (cells.some((cell) => cell.is_gate)) {
        keys.push({
            label: 'Attachment gate',
            color: BOARD_COLORS.gate,
            dashed: true,
        });
    }

    return keys;
}

const hasEntries = computed(() => props.entries.length > 0);
</script>

<template>
    <div class="flex flex-col gap-3">
        <EmptyBlock
            v-if="!hasEntries"
            message="No paragon boards on this build yet."
        />

        <template v-else>
            <div
                v-for="(entry, order) in entries"
                :key="`board-${order}-${entry.board}`"
                class="relative flex flex-col gap-3 rounded-[var(--radius-md)] border border-[var(--border-subtle)] bg-[var(--surface-card)] p-4 [box-shadow:var(--shadow-1)]"
            >
                <!-- The chain connector: every board after the first attaches
                     to the one above it. -->
                <span
                    v-if="order > 0"
                    aria-hidden="true"
                    class="absolute -top-3 left-8 h-3 w-px bg-[var(--border-strong)]"
                />

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
                        >
                            {{ glyphMeta(entry) }}
                        </p>
                    </div>
                    <span
                        class="ml-auto font-mono text-[12px] text-[var(--fg-3)]"
                    >
                        rotated {{ rotationLabel(entry) }}
                    </span>
                </div>

                <!-- The board itself, when the page was given its grid. -->
                <svg
                    v-if="drawn[order]"
                    :viewBox="`-2 -2 ${drawn[order].width + 4} ${drawn[order].height + 4}`"
                    role="img"
                    :aria-label="`${entry.board}, rotated ${rotationLabel(entry)}`"
                    class="block w-full max-w-[320px] self-center rounded-[var(--radius-sm)] border border-[var(--border-hairline)] bg-[var(--ink-950)] p-2"
                >
                    <g
                        v-for="drawnCell in drawn[order].cells"
                        :key="drawnCell.key"
                    >
                        <title>
                            {{
                                drawnCell.cell.has_socket
                                    ? socketTitle(entry)
                                    : cellTitle(drawnCell.cell)
                            }}
                        </title>
                        <rect
                            :x="drawnCell.x"
                            :y="drawnCell.y"
                            :width="CELL"
                            :height="CELL"
                            rx="2"
                            :fill="fillFor(drawnCell.cell)"
                            :stroke="strokeFor(drawnCell.cell)"
                            :stroke-width="
                                drawnCell.cell.has_socket ||
                                drawnCell.cell.is_gate
                                    ? 1.4
                                    : 0.6
                            "
                            :stroke-dasharray="
                                drawnCell.cell.is_gate ? '2 1.5' : undefined
                            "
                        />
                        <circle
                            v-if="drawnCell.cell.has_socket"
                            :cx="drawnCell.x + CELL / 2"
                            :cy="drawnCell.y + CELL / 2"
                            :r="CELL / 4"
                            :fill="BOARD_COLORS.socket"
                        />
                    </g>
                </svg>

                <!-- The board legend. The glyph name is written here rather
                     than beside its socket: at this scale a cell is a few
                     pixels wide and a label on it would be unreadable. -->
                <div
                    v-if="drawn[order]"
                    class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1.5 font-mono text-[11px] text-[var(--fg-3)]"
                >
                    <span
                        v-for="key in legendFor(order)"
                        :key="`${order}-legend-${key.label}`"
                        class="inline-flex items-center gap-1.5"
                    >
                        <span
                            class="size-2 shrink-0 rounded-[2px]"
                            :class="key.dashed ? 'border border-dashed' : ''"
                            :style="
                                key.dashed
                                    ? { borderColor: key.color }
                                    : { background: key.color }
                            "
                        />
                        {{ key.label }}
                    </span>
                </div>

                <div v-if="entry.notables?.length" class="flex flex-col gap-2">
                    <p :class="LABEL_CLASS">Notables</p>
                    <div class="flex flex-wrap gap-1.5">
                        <span
                            v-for="notable in entry.notables"
                            :key="`${order}-${notable}`"
                            class="inline-flex items-center rounded-[var(--radius-pill)] border border-[var(--border-subtle)] bg-[var(--surface-card-hover)] px-2.5 py-1 font-mono text-[12px] leading-none text-[var(--fg-2)]"
                        >
                            {{ notable }}
                        </span>
                    </div>
                </div>
            </div>

            <p
                v-if="(boards ?? []).length === 0"
                class="font-mono text-[12px] text-[var(--fg-3)]"
            >
                Board layouts are not rendered yet — the plan above is what the
                publisher recorded.
            </p>
        </template>
    </div>
</template>
