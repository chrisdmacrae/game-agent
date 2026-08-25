<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';

interface TreeNode {
    id: number;
    x: number;
    y: number;
    k: 'small' | 'notable' | 'keystone' | 'jewel' | 'start' | 'ascstart';
    n?: string;
    st?: string[];
    a?: string;
    s?: [number, number, number, number];
    ci?: number[];
}

interface TreeData {
    bounds: { min_x: number; min_y: number; max_x: number; max_y: number };
    sheet: { w: number; h: number };
    classes: string[];
    nodes: TreeNode[];
    edges: [number, number][];
}

const props = defineProps<{
    treeUrl: string;
    spriteUrl: string;
    highlightNames: string[];
    ascendancyNodes: string[];
    nodeIds: number[];
    grantedIds: number[];
    ascendancyPathIds: number[];
    className?: string;
    ascendancyKey?: string | null;
    ascendancyName?: string;
}>();

const tree = ref<TreeData | null>(null);
const failed = ref(false);

onMounted(async () => {
    try {
        const response = await fetch(props.treeUrl);

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        tree.value = await response.json();
        resetView();
    } catch {
        failed.value = true;
    }
});

// The main tree, plus only the chosen ascendancy's cluster.
const visibleNodes = computed(() =>
    (tree.value?.nodes ?? []).filter(
        (node) => !node.a || node.a === props.ascendancyKey,
    ),
);

const visibleIds = computed(
    () => new Set(visibleNodes.value.map((node) => node.id)),
);

const visibleEdges = computed(() =>
    (tree.value?.edges ?? []).filter(
        ([a, b]) => visibleIds.value.has(a) && visibleIds.value.has(b),
    ),
);

const highlightedIds = computed(() => {
    if (!tree.value) {
        return new Set<number>();
    }

    const ids = new Set<number>([...props.nodeIds, ...props.ascendancyPathIds]);
    const names = new Set(
        props.highlightNames.map((name) => name.toLowerCase()),
    );
    const ascNames = new Set(
        props.ascendancyNodes.map((name) => name.toLowerCase()),
    );

    for (const node of visibleNodes.value) {
        if (node.n && !node.a && names.has(node.n.toLowerCase())) {
            ids.add(node.id);
        }

        if (node.n && node.a && ascNames.has(node.n.toLowerCase())) {
            ids.add(node.id);
        }

        if (node.k === 'ascstart' && node.a === props.ascendancyKey) {
            ids.add(node.id);
        }

        if (
            node.k === 'start' &&
            props.className &&
            (node.ci ?? []).some(
                (i) => tree.value!.classes[i] === props.className,
            )
        ) {
            ids.add(node.id);
        }
    }

    return ids;
});

const grantedSet = computed(() => new Set(props.grantedIds));

const nodeById = computed(() => {
    const map = new Map<number, TreeNode>();

    for (const node of tree.value?.nodes ?? []) {
        map.set(node.id, node);
    }

    return map;
});

// In-game, the chosen ascendancy's tree sits in the center of the ring of
// class starts; the export stores clusters far outside the tree. Translate the
// visible cluster so its start lands on the class-start centroid.
const ascendancyOffset = computed(() => {
    if (!tree.value || !props.ascendancyKey) {
        return null;
    }

    const starts = tree.value.nodes.filter((node) => node.k === 'start');

    if (!starts.length) {
        return null;
    }

    const center = {
        x: starts.reduce((sum, node) => sum + node.x, 0) / starts.length,
        y: starts.reduce((sum, node) => sum + node.y, 0) / starts.length,
    };

    const cluster = tree.value.nodes.filter(
        (node) => node.a === props.ascendancyKey,
    );

    if (!cluster.length) {
        return null;
    }

    const anchor = cluster.find((node) => node.k === 'ascstart') ?? cluster[0];

    return { dx: center.x - anchor.x, dy: center.y - anchor.y };
});

function displayX(node: TreeNode | undefined): number {
    if (!node) {
        return 0;
    }

    return node.a && ascendancyOffset.value
        ? node.x + ascendancyOffset.value.dx
        : node.x;
}

function displayY(node: TreeNode | undefined): number {
    if (!node) {
        return 0;
    }

    return node.a && ascendancyOffset.value
        ? node.y + ascendancyOffset.value.dy
        : node.y;
}

// Edges between two allocated nodes are highlighted (visible pathing when a
// full node_ids allocation was saved).
const highlightedEdges = computed(() =>
    visibleEdges.value.filter(
        ([a, b]) => highlightedIds.value.has(a) && highlightedIds.value.has(b),
    ),
);

// Nodes one edge away from the allocated tree: they read as "reachable" and
// take the dimmer teal stroke. Presentation only — nothing is clickable here.
const connectedIds = computed(() => {
    const ids = new Set<number>();

    for (const [a, b] of visibleEdges.value) {
        if (highlightedIds.value.has(a) && !highlightedIds.value.has(b)) {
            ids.add(b);
        }

        if (highlightedIds.value.has(b) && !highlightedIds.value.has(a)) {
            ids.add(a);
        }
    }

    return ids;
});

const padding = 1500;

const viewBox = ref({ x: 0, y: 0, w: 0, h: 0 });
const initialViewBox = ref({ x: 0, y: 0, w: 0, h: 0 });

const viewBoxAttr = computed(
    () =>
        `${viewBox.value.x} ${viewBox.value.y} ${viewBox.value.w} ${viewBox.value.h}`,
);

function resetView() {
    if (!tree.value) {
        return;
    }

    const { min_x, min_y, max_x, max_y } = tree.value.bounds;
    initialViewBox.value = {
        x: min_x - padding,
        y: min_y - padding,
        w: max_x - min_x + padding * 2,
        h: max_y - min_y + padding * 2,
    };
    viewBox.value = { ...initialViewBox.value };
}

// Wheel zoom toward the cursor, drag to pan.
const svgEl = ref<SVGSVGElement | null>(null);
const container = ref<HTMLDivElement | null>(null);
let dragging = false;
let last = { x: 0, y: 0 };

function onWheel(event: WheelEvent) {
    if (!svgEl.value) {
        return;
    }

    const factor = event.deltaY > 0 ? 1.15 : 1 / 1.15;
    const rect = svgEl.value.getBoundingClientRect();
    const px = (event.clientX - rect.left) / rect.width;
    const py = (event.clientY - rect.top) / rect.height;
    const { x, y, w, h } = viewBox.value;
    const newW = Math.min(
        Math.max(w * factor, initialViewBox.value.w / 20),
        initialViewBox.value.w * 1.5,
    );
    const newH = (newW / w) * h;
    viewBox.value = {
        x: x + (w - newW) * px,
        y: y + (h - newH) * py,
        w: newW,
        h: newH,
    };
}

function onPointerDown(event: PointerEvent) {
    dragging = true;
    hoveredNode.value = null;
    last = { x: event.clientX, y: event.clientY };
    (event.target as Element).setPointerCapture?.(event.pointerId);
}

function onPointerMove(event: PointerEvent) {
    if (!dragging || !svgEl.value) {
        return;
    }

    const rect = svgEl.value.getBoundingClientRect();
    const scale = viewBox.value.w / rect.width;
    viewBox.value.x -= (event.clientX - last.x) * scale;
    viewBox.value.y -= (event.clientY - last.y) * scale;
    last = { x: event.clientX, y: event.clientY };
}

function onPointerUp() {
    dragging = false;
}

// Node hover popup: delegation on the svg via data-node ids.
const hoveredNode = ref<TreeNode | null>(null);
const popupStyle = ref<Record<string, string>>({});

function onOver(event: MouseEvent) {
    const target = (event.target as Element).closest<SVGElement>('[data-node]');

    if (!target || !container.value) {
        hoveredNode.value = null;

        return;
    }

    const node = nodeById.value.get(Number(target.dataset.node)) ?? null;

    if (!node || (!node.n && !node.st?.length)) {
        hoveredNode.value = null;

        return;
    }

    const rect = container.value.getBoundingClientRect();
    const x = Math.min(
        Math.max(8, event.clientX - rect.left + 14),
        rect.width - 288,
    );
    const y = Math.min(event.clientY - rect.top + 14, rect.height - 120);

    popupStyle.value = { left: `${x}px`, top: `${y}px` };
    hoveredNode.value = node;
}

function onLeave() {
    hoveredNode.value = null;
}

/**
 * SVG presentation attributes cannot read CSS custom properties, so the design
 * system tokens are resolved to their hex values once here. Keep in step with
 * `resources/css/byb/colors.css`.
 */
const TREE_COLORS = {
    /** --ink-700: base edge hairline. */
    edge: '#1C232D',
    /** --teal-400: the allocated path and allocated nodes. */
    allocated: '#2DE1C2',
    /** --teal-500: small allocated nodes sit one step down. */
    allocatedSmall: '#12BFA2',
    /** --teal-600: connected but not allocated. */
    connected: '#0A8E79',
    /** --ink-800: unallocated fill. */
    unallocated: '#131820',
    /** --ink-500: unallocated stroke. */
    unallocatedStroke: '#2F3A49',
    /** --fg-1: selected / hovered node stroke. */
    selected: '#EDF2F7',
    /** --violet-400: nodes granted by a jewel or an instilled amulet. */
    granted: '#9C7BFF',
} as const;

function nodeFill(node: TreeNode): string {
    if (grantedSet.value.has(node.id)) {
        return TREE_COLORS.granted;
    }

    if (highlightedIds.value.has(node.id)) {
        return node.k === 'small'
            ? TREE_COLORS.allocatedSmall
            : TREE_COLORS.allocated;
    }

    return TREE_COLORS.unallocated;
}

function nodeStroke(node: TreeNode): string {
    if (hoveredNode.value?.id === node.id) {
        return TREE_COLORS.selected;
    }

    if (grantedSet.value.has(node.id)) {
        return TREE_COLORS.granted;
    }

    if (highlightedIds.value.has(node.id)) {
        return TREE_COLORS.allocated;
    }

    return connectedIds.value.has(node.id)
        ? TREE_COLORS.connected
        : TREE_COLORS.unallocatedStroke;
}

const kindLabels: Record<TreeNode['k'], string> = {
    small: 'Passive',
    notable: 'Notable',
    keystone: 'Keystone',
    jewel: 'Jewel socket',
    start: 'Class start',
    ascstart: 'Ascendancy start',
};

const radii: Record<TreeNode['k'], number> = {
    small: 45,
    notable: 85,
    keystone: 120,
    jewel: 80,
    start: 130,
    ascstart: 150,
};

// Icon box = the node circle's diameter (tracking highlight scale), clipped
// to circle(50%) so the art fills the circle exactly.
function iconSize(node: TreeNode): number {
    const lit =
        highlightedIds.value.has(node.id) || grantedSet.value.has(node.id);

    return radii[node.k] * 2 * (lit ? 1.35 : 1);
}
</script>

<template>
    <div
        ref="container"
        class="relative overflow-hidden rounded-[var(--radius-md)] border border-[var(--border-hairline)] bg-[var(--ink-950)]"
    >
        <p v-if="failed" class="p-6 text-[13px] text-[var(--fg-3)]">
            Passive tree data is unavailable.
        </p>
        <p v-else-if="!tree" class="p-6 text-[13px] text-[var(--fg-3)]">
            Loading passive tree…
        </p>
        <template v-else>
            <svg
                ref="svgEl"
                :viewBox="viewBoxAttr"
                class="block h-[420px] w-full cursor-grab touch-none select-none active:cursor-grabbing sm:h-[520px]"
                @wheel.prevent="onWheel"
                @pointerdown="onPointerDown"
                @pointermove="onPointerMove"
                @pointerup="onPointerUp"
                @pointerleave="onPointerUp"
                @mouseover="onOver"
                @mouseleave="onLeave"
            >
                <!-- Base edges -->
                <line
                    v-for="([a, b], index) in visibleEdges"
                    :key="`e${index}`"
                    :x1="displayX(nodeById.get(a))"
                    :y1="displayY(nodeById.get(a))"
                    :x2="displayX(nodeById.get(b))"
                    :y2="displayY(nodeById.get(b))"
                    :stroke="TREE_COLORS.edge"
                    stroke-width="18"
                />
                <!-- Allocated path edges -->
                <line
                    v-for="([a, b], index) in highlightedEdges"
                    :key="`h${index}`"
                    :x1="displayX(nodeById.get(a))"
                    :y1="displayY(nodeById.get(a))"
                    :x2="displayX(nodeById.get(b))"
                    :y2="displayY(nodeById.get(b))"
                    :stroke="TREE_COLORS.allocated"
                    stroke-width="34"
                    stroke-linecap="round"
                />
                <!-- Nodes -->
                <g
                    v-for="node in visibleNodes"
                    :key="node.id"
                    :data-node="node.id"
                    :class="node.n || node.st ? 'cursor-help' : ''"
                >
                    <circle
                        :cx="displayX(node)"
                        :cy="displayY(node)"
                        :r="
                            radii[node.k] *
                            (highlightedIds.has(node.id) ||
                            grantedSet.has(node.id)
                                ? 1.35
                                : 1)
                        "
                        :fill="nodeFill(node)"
                        :stroke="nodeStroke(node)"
                        :stroke-width="
                            grantedSet.has(node.id) ||
                            highlightedIds.has(node.id)
                                ? 30
                                : 12
                        "
                    />
                    <!-- Sprite icon, clipped to the node circle -->
                    <svg
                        v-if="node.s && tree.sheet.w"
                        :x="displayX(node) - iconSize(node) / 2"
                        :y="displayY(node) - iconSize(node) / 2"
                        :width="iconSize(node)"
                        :height="iconSize(node)"
                        :viewBox="`${node.s[0]} ${node.s[1]} ${node.s[2]} ${node.s[3]}`"
                        style="pointer-events: none; clip-path: circle(50%)"
                        :opacity="
                            highlightedIds.has(node.id) ||
                            grantedSet.has(node.id)
                                ? 1
                                : 0.55
                        "
                    >
                        <image
                            :href="spriteUrl"
                            :width="tree.sheet.w"
                            :height="tree.sheet.h"
                        />
                    </svg>
                </g>
            </svg>
            <button
                type="button"
                class="absolute right-3 bottom-3 rounded-[var(--radius-sm)] border border-[var(--border-subtle)] bg-[var(--surface-card)] px-2.5 py-1.5 font-mono text-[11px] leading-none font-bold tracking-[0.14em] text-[var(--fg-2)] uppercase outline-none [transition:var(--transition-control)] hover:border-[var(--border-strong)] hover:text-[var(--fg-1)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--focus-ring)]"
                @click="resetView"
            >
                Reset view
            </button>
            <p
                class="absolute bottom-3 left-3 font-mono text-[12px] text-[var(--fg-3)]"
            >
                Scroll to zoom · drag to pan · hover a node<span
                    v-if="grantedIds.length"
                >
                    ·
                    <span class="text-[var(--violet-400)]">violet</span> =
                    granted by a jewel or an instill</span
                >
            </p>

            <!-- Node popup -->
            <div
                v-if="hoveredNode"
                class="pointer-events-none absolute z-10 w-[280px] rounded-[var(--radius-md)] border border-[var(--border-subtle)] bg-[var(--surface-raised)] p-3 [box-shadow:var(--shadow-2)]"
                :style="popupStyle"
            >
                <p
                    class="font-mono text-[11px] leading-[1.4] font-bold tracking-[0.14em] uppercase"
                    :class="
                        highlightedIds.has(hoveredNode.id)
                            ? 'text-[var(--teal-300)]'
                            : 'text-[var(--fg-3)]'
                    "
                >
                    {{ hoveredNode.n ?? kindLabels[hoveredNode.k] }}
                </p>
                <p class="mt-1 font-mono text-[12px] text-[var(--fg-3)]">
                    {{ kindLabels[hoveredNode.k]
                    }}<template v-if="hoveredNode.a">
                        · {{ ascendancyName ?? 'Ascendancy' }}</template
                    >
                </p>
                <ul
                    v-if="hoveredNode.st?.length"
                    class="mt-2 space-y-0.5 text-[13px] text-[var(--fg-2)]"
                >
                    <li
                        v-for="stat in hoveredNode.st"
                        :key="stat"
                        class="whitespace-pre-line"
                    >
                        {{ stat }}
                    </li>
                </ul>
            </div>
        </template>
    </div>
</template>
