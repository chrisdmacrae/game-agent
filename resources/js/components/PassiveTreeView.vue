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
    className?: string;
    ascendancyKey?: string | null;
    ascendancyName?: string;
}>();

const tree = ref<TreeData | null>(null);
const failed = ref(false);

onMounted(async () => {
    try {
        const response = await fetch(props.treeUrl);
        if (!response.ok) throw new Error(String(response.status));
        tree.value = await response.json();
        resetView();
    } catch {
        failed.value = true;
    }
});

// The main tree, plus only the chosen ascendancy's cluster.
const visibleNodes = computed(() =>
    (tree.value?.nodes ?? []).filter((node) => !node.a || node.a === props.ascendancyKey),
);

const visibleIds = computed(() => new Set(visibleNodes.value.map((node) => node.id)));

const visibleEdges = computed(() =>
    (tree.value?.edges ?? []).filter(([a, b]) => visibleIds.value.has(a) && visibleIds.value.has(b)),
);

const highlightedIds = computed(() => {
    if (!tree.value) return new Set<number>();

    const ids = new Set<number>(props.nodeIds);
    const names = new Set(props.highlightNames.map((name) => name.toLowerCase()));
    const ascNames = new Set(props.ascendancyNodes.map((name) => name.toLowerCase()));

    for (const node of visibleNodes.value) {
        if (node.n && !node.a && names.has(node.n.toLowerCase())) ids.add(node.id);
        if (node.n && node.a && ascNames.has(node.n.toLowerCase())) ids.add(node.id);
        if (node.k === 'ascstart' && node.a === props.ascendancyKey) ids.add(node.id);
        if (node.k === 'start' && props.className && (node.ci ?? []).some((i) => tree.value!.classes[i] === props.className)) {
            ids.add(node.id);
        }
    }

    return ids;
});

const grantedSet = computed(() => new Set(props.grantedIds));

const nodeById = computed(() => {
    const map = new Map<number, TreeNode>();
    for (const node of tree.value?.nodes ?? []) map.set(node.id, node);
    return map;
});

// Edges between two allocated nodes are highlighted (visible pathing when a
// full node_ids allocation was saved).
const highlightedEdges = computed(() =>
    visibleEdges.value.filter(([a, b]) => highlightedIds.value.has(a) && highlightedIds.value.has(b)),
);

const padding = 1500;

const viewBox = ref({ x: 0, y: 0, w: 0, h: 0 });
const initialViewBox = ref({ x: 0, y: 0, w: 0, h: 0 });

const viewBoxAttr = computed(() => `${viewBox.value.x} ${viewBox.value.y} ${viewBox.value.w} ${viewBox.value.h}`);

function resetView() {
    if (!tree.value) return;
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
    if (!svgEl.value) return;
    const factor = event.deltaY > 0 ? 1.15 : 1 / 1.15;
    const rect = svgEl.value.getBoundingClientRect();
    const px = (event.clientX - rect.left) / rect.width;
    const py = (event.clientY - rect.top) / rect.height;
    const { x, y, w, h } = viewBox.value;
    const newW = Math.min(Math.max(w * factor, initialViewBox.value.w / 20), initialViewBox.value.w * 1.5);
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
    if (!dragging || !svgEl.value) return;
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
    const x = Math.min(Math.max(8, event.clientX - rect.left + 14), rect.width - 288);
    const y = Math.min(event.clientY - rect.top + 14, rect.height - 120);

    popupStyle.value = { left: `${x}px`, top: `${y}px` };
    hoveredNode.value = node;
}

function onLeave() {
    hoveredNode.value = null;
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

function iconSize(node: TreeNode): number {
    return radii[node.k] * 2.4;
}
</script>

<template>
    <div ref="container" class="relative overflow-hidden rounded-lg border border-zinc-800 bg-zinc-950">
        <p v-if="failed" class="p-6 text-sm text-zinc-500">Passive tree data is unavailable.</p>
        <p v-else-if="!tree" class="p-6 text-sm text-zinc-500">Loading passive tree…</p>
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
                    :x1="nodeById.get(a)?.x"
                    :y1="nodeById.get(a)?.y"
                    :x2="nodeById.get(b)?.x"
                    :y2="nodeById.get(b)?.y"
                    stroke="#27272a"
                    stroke-width="18"
                />
                <!-- Allocated path edges -->
                <line
                    v-for="([a, b], index) in highlightedEdges"
                    :key="`h${index}`"
                    :x1="nodeById.get(a)?.x"
                    :y1="nodeById.get(a)?.y"
                    :x2="nodeById.get(b)?.x"
                    :y2="nodeById.get(b)?.y"
                    stroke="#f59e0b"
                    stroke-width="34"
                    stroke-linecap="round"
                />
                <!-- Nodes -->
                <g v-for="node in visibleNodes" :key="node.id" :data-node="node.id" :class="node.n || node.st ? 'cursor-help' : ''">
                    <circle
                        :cx="node.x"
                        :cy="node.y"
                        :r="radii[node.k] * (highlightedIds.has(node.id) || grantedSet.has(node.id) ? 1.35 : 1)"
                        :fill="grantedSet.has(node.id) ? '#8b5cf6' : highlightedIds.has(node.id) ? '#f59e0b' : node.k === 'small' ? '#3f3f46' : '#18181b'"
                        :stroke="grantedSet.has(node.id) ? '#c4b5fd' : highlightedIds.has(node.id) ? '#fbbf24' : node.k === 'keystone' || node.k === 'start' || node.k === 'ascstart' ? '#71717a' : '#3f3f46'"
                        :stroke-width="grantedSet.has(node.id) || highlightedIds.has(node.id) ? 30 : 12"
                    />
                    <!-- Sprite icon (notables, keystones, jewels, ascendancy nodes) -->
                    <svg
                        v-if="node.s && tree.sheet.w"
                        :x="node.x - iconSize(node) / 2"
                        :y="node.y - iconSize(node) / 2"
                        :width="iconSize(node)"
                        :height="iconSize(node)"
                        :viewBox="`${node.s[0]} ${node.s[1]} ${node.s[2]} ${node.s[3]}`"
                        style="pointer-events: none"
                        :opacity="highlightedIds.has(node.id) || grantedSet.has(node.id) ? 1 : 0.55"
                    >
                        <image :href="spriteUrl" :width="tree.sheet.w" :height="tree.sheet.h" />
                    </svg>
                </g>
            </svg>
            <button
                class="absolute right-3 bottom-3 rounded-md border border-zinc-700 bg-zinc-900/90 px-3 py-1 text-xs text-zinc-300 hover:bg-zinc-800"
                @click="resetView"
            >
                Reset view
            </button>
            <p class="absolute bottom-3 left-3 text-xs text-zinc-600">Scroll to zoom · drag to pan · hover nodes for details<span v-if="grantedIds.length"> · <span class="text-violet-400">purple</span> = granted (jewel/instill)</span></p>

            <!-- Node popup -->
            <div
                v-if="hoveredNode"
                class="pointer-events-none absolute z-10 w-[280px] rounded-lg border border-zinc-700 bg-zinc-900 p-3 shadow-xl shadow-black/50"
                :style="popupStyle"
            >
                <p class="font-semibold text-white">
                    {{ hoveredNode.n ?? kindLabels[hoveredNode.k] }}
                </p>
                <p class="text-xs text-zinc-500">
                    {{ kindLabels[hoveredNode.k] }}<template v-if="hoveredNode.a"> · {{ ascendancyName ?? "Ascendancy" }}</template>
                </p>
                <ul v-if="hoveredNode.st?.length" class="mt-1.5 space-y-0.5 text-sm text-sky-200/80">
                    <li v-for="stat in hoveredNode.st" :key="stat" class="whitespace-pre-line">{{ stat }}</li>
                </ul>
            </div>
        </template>
    </div>
</template>
