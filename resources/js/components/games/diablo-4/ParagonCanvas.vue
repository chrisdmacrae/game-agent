<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type {
    D4ParagonBoardGrid,
    D4ParagonCell,
    D4ParagonEntry,
} from '@/components/games/diablo-4/types';

/**
 * The paragon tree as one continuous, pannable, zoomable canvas, styled
 * after the in-game board view: dark plates, socket-like nodes, the
 * allocated path drawn as a lit route, and explicit START / ENTRY markers so
 * the reading order is never in question. Boards weld together the way the
 * plan attaches them — each child sits past the gate it enters through.
 *
 * Canvas, not SVG: the tree spans thousands of cells across boards, and the
 * in-game feel (drag to pan, wheel to zoom) is the point.
 */
const props = defineProps<{
    entries: D4ParagonEntry[];
    boards: D4ParagonBoardGrid[];
    /** The editor turns cells into buttons that toggle path allocation. */
    editable?: boolean;
}>();

const emit = defineEmits<{
    (
        event: 'toggle-node',
        entryIndex: number,
        node: { row: number; col: number },
        cell: D4ParagonCell,
    ): void;
}>();

/**
 * Resolved hex, because canvas cannot read CSS custom properties. The board
 * deliberately keeps the game's dark plate in both themes — it is a game
 * surface, not a document — so every color is painted explicitly. Keep the
 * accents in step with resources/css/byb/colors.css.
 */
const C = {
    plate: '#0b0e13',
    plateEdge: '#242d3a',
    label: '#6c7c8f',
    labelBright: '#a2b1c2',
    cellEmpty: '#131820',
    cellStroke: '#2f3a49',
    bone: '#c9d2dc',
    magic: '#5aa9ff',
    rare: '#ffc857',
    legendary: '#ff5a5f',
    socket: '#9c7bff',
    path: '#ff5a5f',
    start: '#7ee0c2',
    gate: '#a2b1c2',
} as const;

const RARITY: Record<string, string> = {
    normal: C.bone,
    magic: C.magic,
    rare: C.rare,
    legendary: C.legendary,
};

/** World units: one cell plus its gutter. */
const CELL = 30;
const GAP = 8;
const STEP = CELL + GAP;
/** Empty world-space between welded boards. */
const WELD = STEP;

type WorldCell = {
    entryIndex: number;
    /** Pre-rotation payload coordinates. */
    source: { row: number; col: number };
    /** World-space center. */
    x: number;
    y: number;
    cell: D4ParagonCell;
    allocated: boolean;
    isStart: boolean;
    isEntryGate: boolean;
    hasSocket: boolean;
};

type BoardFrame = {
    entryIndex: number;
    name: string;
    glyph: string | null;
    x: number;
    y: number;
    w: number;
    h: number;
    socket: WorldCell | null;
    glyphLevel: number | null;
    hasAllocation: boolean;
};

function rotate<T>(grid: (T | null)[][], degrees: number): (T | null)[][] {
    const size = Math.max(grid.length, ...grid.map((row) => row.length), 1);
    const square: (T | null)[][] = Array.from({ length: size }, (_, row) =>
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

const boardsByName = computed(() => {
    const index = new Map<string, D4ParagonBoardGrid>();

    for (const board of props.boards) {
        index.set(board.name.toLowerCase(), board);
    }

    return index;
});

/** The whole tree laid out in world space. */
const scene = computed(() => {
    const cells: WorldCell[] = [];
    const frames: BoardFrame[] = [];

    /** Where the NEXT board welds on: world position just past the previous
     * board's top edge, aligned to its topmost gate column. */
    let weldX = 0;
    let weldTop = 0;
    let placedAny = false;

    props.entries.forEach((entry, entryIndex) => {
        const board = boardsByName.value.get(entry.board.toLowerCase());

        if (!board || board.grid.length === 0) {
            return;
        }

        type Tagged = {
            cell: D4ParagonCell;
            source: { row: number; col: number };
            allocated: boolean;
            isEntryGate: boolean;
        };

        const allocatedKeys = new Set(
            (entry.nodes ?? []).map((node) => `${node.row},${node.col}`),
        );
        const gate = entry.attach?.gate;
        const gateKey = gate ? `${gate.row},${gate.col}` : null;

        const tagged: (Tagged | null)[][] = board.grid.map((row, rowIndex) =>
            row.map((cell, colIndex) =>
                cell
                    ? {
                          cell,
                          source: { row: rowIndex, col: colIndex },
                          allocated: allocatedKeys.has(
                              `${rowIndex},${colIndex}`,
                          ),
                          isEntryGate: gateKey === `${rowIndex},${colIndex}`,
                      }
                    : null,
            ),
        );

        const grid = rotate(tagged, entry.rotation ?? 0);

        // Crop to the occupied bounding box.
        const occupied: { row: number; col: number; tag: Tagged }[] = [];

        grid.forEach((row, rowIndex) => {
            row.forEach((tag, colIndex) => {
                if (tag) {
                    occupied.push({ row: rowIndex, col: colIndex, tag });
                }
            });
        });

        if (occupied.length === 0) {
            return;
        }

        const minRow = Math.min(...occupied.map((o) => o.row));
        const minCol = Math.min(...occupied.map((o) => o.col));
        const maxRow = Math.max(...occupied.map((o) => o.row));
        const maxCol = Math.max(...occupied.map((o) => o.col));
        const rows = maxRow - minRow + 1;
        const cols = maxCol - minCol + 1;

        // The board's entry sits on its bottom side (child boards enter from
        // the board below), so weld this board's ENTRY column onto the weld
        // point. The first board just starts at the origin.
        const entryGate = occupied.find((o) => o.tag.isEntryGate);
        const anchorCol = entryGate ? entryGate.col - minCol : (cols - 1) / 2;

        const originY = placedAny ? weldTop - WELD - rows * STEP : 0;
        const originX = placedAny ? weldX - anchorCol * STEP : 0;

        const frame: BoardFrame = {
            entryIndex,
            name: entry.board,
            glyph: entry.glyph ?? null,
            glyphLevel: entry.glyph_level ?? null,
            x: originX - GAP,
            y: originY - GAP,
            w: cols * STEP + GAP,
            h: rows * STEP + GAP,
            socket: null,
            hasAllocation: allocatedKeys.size > 0,
        };

        let topGateCol: number | null = null;
        let topGateRow = Number.POSITIVE_INFINITY;

        for (const { row, col, tag } of occupied) {
            const world: WorldCell = {
                entryIndex,
                source: tag.source,
                x: originX + (col - minCol) * STEP + CELL / 2,
                y: originY + (row - minRow) * STEP + CELL / 2,
                cell: tag.cell,
                allocated: tag.allocated,
                isStart:
                    entryIndex === 0 &&
                    (tag.cell.key ?? '').toLowerCase().startsWith('startnode'),
                isEntryGate: tag.isEntryGate,
                hasSocket: Boolean(tag.cell.has_socket),
            };

            cells.push(world);

            if (world.hasSocket) {
                frame.socket = world;
            }

            if (tag.cell.is_gate && row < topGateRow) {
                topGateRow = row;
                topGateCol = col;
            }
        }

        frames.push(frame);

        // Next board welds above this one, at this board's topmost gate.
        weldX =
            originX +
            ((topGateCol ?? (minCol + maxCol) / 2) - minCol) * STEP;
        weldTop = originY;
        placedAny = true;
    });

    // Path links between orthogonally-adjacent allocated cells of one board.
    const byPos = new Map<string, WorldCell>();

    for (const cell of cells) {
        byPos.set(`${cell.entryIndex}:${cell.x},${cell.y}`, cell);
    }

    const links: { a: WorldCell; b: WorldCell }[] = [];

    for (const cell of cells) {
        if (!cell.allocated && !cell.isStart) {
            continue;
        }

        for (const [dx, dy] of [
            [STEP, 0],
            [0, STEP],
        ] as const) {
            const other = byPos.get(
                `${cell.entryIndex}:${cell.x + dx},${cell.y + dy}`,
            );

            if (other && (other.allocated || other.isStart)) {
                links.push({ a: cell, b: other });
            }
        }
    }

    return { cells, frames, links };
});

const container = ref<HTMLDivElement | null>(null);
const canvas = ref<HTMLCanvasElement | null>(null);

type HoverCard = {
    x: number;
    y: number;
    title: string;
    meta: string;
    lines: string[];
};

const hoverLabel = ref<HoverCard | null>(null);

/** The explainer card for a hovered cell, built from the imported grid data. */
function hoverCardFor(cell: WorldCell): Omit<HoverCard, 'x' | 'y'> {
    const pretty = (value: string) => value.replace(/_/g, ' ');
    const entry = props.entries[cell.entryIndex];
    const lines: string[] = [];

    if (cell.isStart) {
        lines.push('The free starting node — the path grows from here.');
    }

    if (cell.cell.is_gate) {
        lines.push(
            cell.isEntryGate
                ? 'Entry gate — this board is entered here from the board below.'
                : 'Attachment gate — another board can weld on here.',
        );
    }

    if (cell.hasSocket) {
        lines.push(
            entry?.glyph
                ? `Glyph socket — ${entry.glyph}${entry.glyph_level ? ` (lvl ${entry.glyph_level})` : ''} is socketed here.`
                : 'Glyph socket — empty.',
        );
    }

    for (const attribute of cell.cell.attributes ?? []) {
        lines.push(pretty(attribute));
    }

    const meta = [
        cell.cell.rarity ?? null,
        cell.allocated ? 'allocated' : cell.isStart ? null : 'not taken',
    ]
        .filter(Boolean)
        .join(' · ');

    return {
        title: pretty(cell.cell.name ?? cell.cell.key ?? 'Node'),
        meta,
        lines,
    };
}

const view = { x: 0, y: 0, zoom: 1 };
let sized = false;

function fitToScene(): void {
    const el = canvas.value;
    const { cells } = scene.value;

    if (!el || cells.length === 0) {
        return;
    }

    const xs = cells.map((c) => c.x);
    const ys = cells.map((c) => c.y);
    const minX = Math.min(...xs) - STEP * 1.5;
    const maxX = Math.max(...xs) + STEP * 1.5;
    const minY = Math.min(...ys) - STEP * 2;
    const maxY = Math.max(...ys) + STEP * 2;

    const width = el.clientWidth;
    const height = el.clientHeight;

    view.zoom = Math.min(
        2,
        Math.min(width / (maxX - minX), height / (maxY - minY)),
    );
    view.x = width / 2 - ((minX + maxX) / 2) * view.zoom;
    view.y = height / 2 - ((minY + maxY) / 2) * view.zoom;
}

function roundedRect(
    context: CanvasRenderingContext2D,
    x: number,
    y: number,
    w: number,
    h: number,
    r: number,
): void {
    context.beginPath();
    context.roundRect(x, y, w, h, r);
}

function diamond(
    context: CanvasRenderingContext2D,
    cx: number,
    cy: number,
    half: number,
): void {
    context.beginPath();
    context.moveTo(cx, cy - half);
    context.lineTo(cx + half, cy);
    context.lineTo(cx, cy + half);
    context.lineTo(cx - half, cy);
    context.closePath();
}

/**
 * Atlas sheets, loaded lazily by texture SNO id. A sheet that has not been
 * extracted 404s once, is remembered as missing, and the node falls back to
 * its plain gem shape — the same letter-badge philosophy as the rest of the
 * icon pipeline.
 */
const atlasCache = new Map<number, HTMLImageElement | 'missing'>();

function atlasImage(texture: number): HTMLImageElement | null {
    const cached = atlasCache.get(texture);

    if (cached === 'missing') {
        return null;
    }

    if (cached) {
        return cached.complete && cached.naturalWidth > 0 ? cached : null;
    }

    const image = new Image();
    image.src = `/games/diablo-4/icons/${texture}.webp`;
    image.onload = () => draw();
    image.onerror = () => {
        atlasCache.set(texture, 'missing');
    };
    atlasCache.set(texture, image);

    return null;
}

/** Draw a cell's icon mask, cropped from its atlas, clipped to the path
 * currently defined on the context. */
function drawCellIcon(
    context: CanvasRenderingContext2D,
    cell: WorldCell,
    size: number,
    alpha: number,
): boolean {
    const icon = cell.cell.icon;

    if (!icon || typeof icon.texture !== 'number') {
        return false;
    }

    const sheet = atlasImage(icon.texture);

    if (!sheet) {
        return false;
    }

    const sx = icon.u0 * sheet.naturalWidth;
    const sy = icon.v0 * sheet.naturalHeight;
    const sw = (icon.u1 - icon.u0) * sheet.naturalWidth;
    const sh = (icon.v1 - icon.v0) * sheet.naturalHeight;

    if (sw <= 0 || sh <= 0) {
        return false;
    }

    context.save();
    context.clip();
    context.globalAlpha = alpha;
    context.drawImage(
        sheet,
        sx,
        sy,
        sw,
        sh,
        cell.x - size / 2,
        cell.y - size / 2,
        size,
        size,
    );
    context.restore();

    return true;
}

function draw(): void {
    const el = canvas.value;
    const context = el?.getContext('2d');

    if (!el || !context) {
        return;
    }

    const dpr = window.devicePixelRatio || 1;
    const width = el.clientWidth;
    const height = el.clientHeight;

    if (el.width !== width * dpr || el.height !== height * dpr) {
        el.width = width * dpr;
        el.height = height * dpr;
    }

    if (!sized) {
        fitToScene();
        sized = true;
    }

    context.setTransform(dpr, 0, 0, dpr, 0, 0);
    context.clearRect(0, 0, width, height);
    context.translate(view.x, view.y);
    context.scale(view.zoom, view.zoom);

    const { cells, frames, links } = scene.value;
    const mono = '"JetBrains Mono", ui-monospace, monospace';

    // Board plates.
    for (const frame of frames) {
        context.fillStyle = C.plate;
        roundedRect(context, frame.x, frame.y, frame.w, frame.h, 10);
        context.fill();
        context.strokeStyle = C.plateEdge;
        context.lineWidth = 1.5;
        context.stroke();

        context.fillStyle = C.labelBright;
        context.font = `600 ${13}px ${mono}`;
        context.textAlign = 'left';
        context.textBaseline = 'alphabetic';
        context.fillText(
            frame.name.toUpperCase(),
            frame.x + 10,
            frame.y - 8,
        );

        if (frame.glyph) {
            context.fillStyle = C.socket;
            context.font = `400 ${11}px ${mono}`;
            context.fillText(
                `◆ ${frame.glyph}${frame.glyphLevel ? ` · lvl ${frame.glyphLevel}` : ''}`,
                frame.x + 10 + context.measureText(frame.name).width + 60,
                frame.y - 8,
            );
        }
    }

    // Glyph radius rings, beneath everything else on the plate.
    for (const frame of frames) {
        if (!frame.socket || !frame.glyph) {
            continue;
        }

        // Radius by glyph level, matching the game's thresholds (3 base,
        // 4 at 25, 5 at 50) — display aid only, the server owns the rule.
        const level = frame.glyphLevel ?? 1;
        const radius = (level >= 50 ? 5 : level >= 25 ? 4 : 3) * STEP;

        context.beginPath();
        context.arc(frame.socket.x, frame.socket.y, radius, 0, Math.PI * 2);
        context.strokeStyle = C.socket + '55';
        context.lineWidth = 2;
        context.setLineDash([6, 6]);
        context.stroke();
        context.setLineDash([]);
        context.fillStyle = C.socket + '0d';
        context.fill();
    }

    // The allocated route, drawn as lit rails beneath the cells.
    context.lineCap = 'round';

    for (const { a, b } of links) {
        context.beginPath();
        context.moveTo(a.x, a.y);
        context.lineTo(b.x, b.y);
        context.strokeStyle = C.path;
        context.lineWidth = 5;
        context.globalAlpha = 0.85;
        context.stroke();
        context.globalAlpha = 1;
    }

    // Cells.
    for (const cell of cells) {
        const gateHalf = CELL / 2;
        const rarity = RARITY[cell.cell.rarity ?? 'normal'] ?? C.bone;
        const showDim = frames.some(
            (f) => f.entryIndex === cell.entryIndex && f.hasAllocation,
        );

        if (cell.cell.is_gate) {
            // Gates are arches, not sockets.
            context.strokeStyle = cell.isEntryGate ? C.path : C.gate;
            context.lineWidth = cell.isEntryGate ? 3 : 1.5;
            context.setLineDash(cell.isEntryGate ? [] : [4, 3]);
            roundedRect(
                context,
                cell.x - gateHalf,
                cell.y - gateHalf,
                CELL,
                CELL,
                gateHalf,
            );
            context.stroke();
            context.setLineDash([]);
            continue;
        }

        const lit = cell.allocated || cell.isStart;
        const isRare = cell.cell.rarity === 'rare';
        const isLegendary = cell.cell.rarity === 'legendary';
        const size = isLegendary ? CELL + 12 : isRare ? CELL + 4 : CELL;
        const half = size / 2;

        // D4's node vocabulary: normals and magics are round gems, rares are
        // gold diamonds, legendaries are the big centerpiece diamond.
        const shape = () => {
            if (isRare || isLegendary) {
                diamond(context, cell.x, cell.y, half);
            } else {
                context.beginPath();
                context.arc(cell.x, cell.y, half - 1, 0, Math.PI * 2);
            }
        };

        if (lit) {
            context.shadowColor = rarity;
            context.shadowBlur = 16;
        }

        shape();
        context.fillStyle = lit ? rarity : C.cellEmpty;
        context.globalAlpha = lit ? 1 : showDim ? 0.55 : 0.9;
        context.fill();
        context.shadowBlur = 0;

        // Node art from the atlas, sitting on the gem. Unallocated nodes keep
        // a ghost of their icon so the board reads like the game's.
        shape();
        const drewIcon = drawCellIcon(
            context,
            cell,
            size * 0.86,
            lit ? 0.95 : showDim ? 0.3 : 0.55,
        );

        shape();
        context.strokeStyle = lit
            ? rarity
            : cell.cell.rarity && cell.cell.rarity !== 'normal'
              ? rarity + '77'
              : C.cellStroke;
        context.lineWidth = lit ? 2 : 1.2;
        context.stroke();
        context.globalAlpha = 1;

        // A lit gem with no art gets an inner core so it still reads as taken.
        if (lit && !drewIcon) {
            context.beginPath();
            context.arc(cell.x, cell.y, size / 7, 0, Math.PI * 2);
            context.fillStyle = C.plate;
            context.globalAlpha = 0.55;
            context.fill();
            context.globalAlpha = 1;
        }

        // Socket cells carry the glyph gem.
        if (cell.hasSocket) {
            context.beginPath();
            context.arc(cell.x, cell.y, CELL / 3.4, 0, Math.PI * 2);
            context.fillStyle = C.plate;
            context.fill();
            context.strokeStyle = C.socket;
            context.lineWidth = 2.5;
            context.stroke();

            context.beginPath();
            context.arc(cell.x, cell.y, CELL / 8, 0, Math.PI * 2);
            context.fillStyle = C.socket;
            context.fill();
        }
    }

    // START and ENTRY markers, above everything.
    context.textAlign = 'center';

    for (const cell of cells) {
        if (cell.isStart) {
            context.beginPath();
            context.arc(cell.x, cell.y, CELL / 2 + 7, 0, Math.PI * 2);
            context.strokeStyle = C.start;
            context.lineWidth = 3;
            context.stroke();

            context.fillStyle = C.start;
            context.font = `700 12px ${mono}`;
            context.fillText('START', cell.x, cell.y + CELL + 16);
        }

        if (cell.isEntryGate) {
            context.fillStyle = C.path;
            context.font = `700 11px ${mono}`;
            context.fillText('ENTRY', cell.x, cell.y + CELL + 12);
        }
    }
}

/* Interaction: drag to pan, wheel to zoom, click to toggle (editor). */
let dragging = false;
let moved = 0;
let last = { x: 0, y: 0 };

function toWorld(clientX: number, clientY: number): { x: number; y: number } {
    const rect = canvas.value!.getBoundingClientRect();

    return {
        x: (clientX - rect.left - view.x) / view.zoom,
        y: (clientY - rect.top - view.y) / view.zoom,
    };
}

function hitCell(clientX: number, clientY: number): WorldCell | null {
    const world = toWorld(clientX, clientY);

    for (const cell of scene.value.cells) {
        if (
            Math.abs(world.x - cell.x) <= CELL / 2 + 3 &&
            Math.abs(world.y - cell.y) <= CELL / 2 + 3
        ) {
            return cell;
        }
    }

    return null;
}

function onPointerDown(event: PointerEvent): void {
    dragging = true;
    moved = 0;
    last = { x: event.clientX, y: event.clientY };
    canvas.value?.setPointerCapture(event.pointerId);
}

function onPointerMove(event: PointerEvent): void {
    if (dragging) {
        const dx = event.clientX - last.x;
        const dy = event.clientY - last.y;
        moved += Math.abs(dx) + Math.abs(dy);
        view.x += dx;
        view.y += dy;
        last = { x: event.clientX, y: event.clientY };
        draw();
        return;
    }

    const cell = hitCell(event.clientX, event.clientY);
    const rect = canvas.value!.getBoundingClientRect();

    hoverLabel.value = cell
        ? {
              x: Math.min(
                  Math.max(event.clientX - rect.left, 110),
                  rect.width - 110,
              ),
              y: event.clientY - rect.top - 16,
              ...hoverCardFor(cell),
          }
        : null;

    if (canvas.value) {
        canvas.value.style.cursor =
            cell && props.editable ? 'pointer' : 'grab';
    }
}

function onPointerUp(event: PointerEvent): void {
    dragging = false;

    // A click, not a drag.
    if (moved < 6 && props.editable) {
        const cell = hitCell(event.clientX, event.clientY);

        if (cell) {
            emit('toggle-node', cell.entryIndex, { ...cell.source }, cell.cell);
        }
    }
}

function onWheel(event: WheelEvent): void {
    event.preventDefault();

    const factor = event.deltaY < 0 ? 1.12 : 1 / 1.12;
    const next = Math.min(2.5, Math.max(0.35, view.zoom * factor));
    const rect = canvas.value!.getBoundingClientRect();
    const px = event.clientX - rect.left;
    const py = event.clientY - rect.top;

    // Zoom around the cursor.
    view.x = px - ((px - view.x) / view.zoom) * next;
    view.y = py - ((py - view.y) / view.zoom) * next;
    view.zoom = next;
    draw();
}

function zoomBy(factor: number): void {
    const el = canvas.value;

    if (!el) {
        return;
    }

    const next = Math.min(2.5, Math.max(0.35, view.zoom * factor));
    const cx = el.clientWidth / 2;
    const cy = el.clientHeight / 2;

    view.x = cx - ((cx - view.x) / view.zoom) * next;
    view.y = cy - ((cy - view.y) / view.zoom) * next;
    view.zoom = next;
    draw();
}

function resetView(): void {
    fitToScene();
    draw();
}

let observer: ResizeObserver | null = null;

onMounted(() => {
    draw();
    observer = new ResizeObserver(() => draw());

    if (container.value) {
        observer.observe(container.value);
    }
});

onBeforeUnmount(() => observer?.disconnect());

watch(scene, () => {
    sized = false;
    draw();
});
</script>

<template>
    <div
        ref="container"
        class="relative h-[520px] w-full overflow-hidden rounded-[var(--radius-md)] border border-[var(--border-subtle)]"
        style="background: #07090d"
    >
        <canvas
            ref="canvas"
            class="block h-full w-full touch-none"
            @pointerdown="onPointerDown"
            @pointermove="onPointerMove"
            @pointerup="onPointerUp"
            @pointerleave="hoverLabel = null"
            @wheel="onWheel"
            @dblclick="resetView"
        />

        <div
            v-if="hoverLabel"
            class="pointer-events-none absolute z-10 w-[220px] -translate-x-1/2 -translate-y-full rounded-[var(--radius-sm)] border p-2.5"
            :style="{
                left: `${hoverLabel.x}px`,
                top: `${hoverLabel.y}px`,
                background: '#151a21',
                borderColor: '#2f3a49',
                boxShadow: '0 8px 24px rgba(0,0,0,0.5)',
            }"
        >
            <p
                class="text-[13px] leading-tight font-semibold"
                style="color: #e8ebef"
            >
                {{ hoverLabel.title }}
            </p>
            <p
                v-if="hoverLabel.meta"
                class="mt-0.5 font-mono text-[11px] uppercase"
                style="color: #6c7c8f; letter-spacing: 0.08em"
            >
                {{ hoverLabel.meta }}
            </p>
            <ul
                v-if="hoverLabel.lines.length"
                class="mt-1.5 flex flex-col gap-1 font-mono text-[11.5px]"
                style="color: #a2b1c2"
            >
                <li v-for="line in hoverLabel.lines" :key="line">
                    {{ line }}
                </li>
            </ul>
        </div>

        <div class="absolute right-3 bottom-3 flex flex-col gap-1.5">
            <button
                type="button"
                aria-label="Zoom in"
                class="size-8 rounded-[var(--radius-xs)] border font-mono text-[15px] leading-none"
                style="
                    background: #151a21;
                    border-color: #2f3a49;
                    color: #a2b1c2;
                "
                @click="zoomBy(1.25)"
            >
                +
            </button>
            <button
                type="button"
                aria-label="Zoom out"
                class="size-8 rounded-[var(--radius-xs)] border font-mono text-[15px] leading-none"
                style="
                    background: #151a21;
                    border-color: #2f3a49;
                    color: #a2b1c2;
                "
                @click="zoomBy(0.8)"
            >
                −
            </button>
            <button
                type="button"
                aria-label="Reset view"
                class="size-8 rounded-[var(--radius-xs)] border font-mono text-[11px] leading-none"
                style="
                    background: #151a21;
                    border-color: #2f3a49;
                    color: #a2b1c2;
                "
                @click="resetView"
            >
                ⌂
            </button>
        </div>

        <p
            class="pointer-events-none absolute bottom-3 left-3 font-mono text-[11px]"
            style="color: #6c7c8f"
        >
            drag to pan · scroll to zoom · double-click to reset{{
                editable ? ' · click a cell to allocate' : ''
            }}
        </p>
    </div>
</template>
