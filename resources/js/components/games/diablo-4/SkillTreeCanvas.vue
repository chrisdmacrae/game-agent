<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { atlasStyle } from '@/components/games/diablo-4/build';
import type {
    D4BuildDefinition,
    D4Entity,
    D4SkillTree,
    D4SkillTreeNode,
} from '@/components/games/diablo-4/types';

/**
 * The class skill tree as the game lays it out — the SkillKit's own node
 * positions and connections on a pannable, zoomable canvas — with the build's
 * picks lit: equipped skills and pointed passives glow, everything else stays
 * a faint constellation, the way Mobalytics-style build pages read.
 *
 * Same engine and visual language as ParagonCanvas: dark plate, gem nodes,
 * lit rails, hover explainer card.
 */
const props = defineProps<{
    tree: D4SkillTree;
    definition: D4BuildDefinition;
    entityFor?: (name: string) => D4Entity | null;
}>();

/**
 * Diablo IV's own UI vocabulary: gold beveled diamond frames on a near-black
 * plate, warm glows for what is taken, bronze-dulled frames for what is not.
 */
const C = {
    plate: '#0a0908',
    node: '#15120d',
    frameDim: '#4a4238',
    frame: '#c79b5a',
    frameBright: '#f0d9a0',
    glow: '#ffd77a',
    passive: '#8fb8d8',
    rail: '#3a332a',
    litRail: '#c79b5a',
    label: '#8a7c66',
    labelBright: '#efe6d5',
} as const;

/** In-game coordinates are huge; shrink into comfortable world units. */
const SCALE = 0.08;

type WorldNode = {
    node: D4SkillTreeNode;
    x: number;
    y: number;
    allocated: boolean;
    /** A modifier whose parent skill is taken: half-lit, never labelled. */
    parentLit: boolean;
    rank: number | null;
    radius: number;
};

const allocatedRanks = computed(() => {
    const ranks = new Map<string, number | null>();

    for (const setup of props.definition.equipped_skills ?? []) {
        if (setup.skill) {
            ranks.set(setup.skill.toLowerCase(), setup.rank ?? null);
        }
    }

    for (const entry of props.definition.skill_points ?? []) {
        if (entry.skill && !ranks.has(entry.skill.toLowerCase())) {
            ranks.set(entry.skill.toLowerCase(), entry.points ?? null);
        }
    }

    return ranks;
});

/** skill name -> the modifier names the build picked under it. */
const chosenModifiers = computed(() => {
    const chosen = new Map<string, Set<string>>();

    for (const setup of props.definition.equipped_skills ?? []) {
        if (setup.skill && setup.modifiers?.length) {
            chosen.set(
                setup.skill.toLowerCase(),
                new Set(setup.modifiers.map((m) => m.toLowerCase())),
            );
        }
    }

    return chosen;
});

const scene = computed(() => {
    const nodes: WorldNode[] = [];
    const byId = new Map<number, WorldNode>();

    for (const node of props.tree.nodes) {
        const nameTaken = node.name
            ? allocatedRanks.value.has(node.name.toLowerCase())
            : false;
        const parentTaken = node.skill
            ? allocatedRanks.value.has(node.skill.toLowerCase())
            : false;
        // A modifier lights when the build explicitly picked it on its
        // parent skill; its siblings only warm up with the parent.
        const modifierChosen =
            node.kind === 'modifier' &&
            parentTaken &&
            Boolean(
                node.name &&
                    chosenModifiers.value
                        .get(node.skill!.toLowerCase())
                        ?.has(node.name.toLowerCase()),
            );
        const allocated =
            ((node.kind === 'skill' || node.kind === 'passive') && nameTaken) ||
            modifierChosen;

        const world: WorldNode = {
            node,
            x: node.x * SCALE,
            y: node.y * SCALE,
            allocated,
            parentLit: node.kind === 'modifier' && parentTaken && !allocated,
            rank:
                allocated && node.kind !== 'modifier'
                    ? (allocatedRanks.value.get(node.name!.toLowerCase()) ??
                      null)
                    : null,
            radius:
                node.kind === 'skill'
                    ? 16
                    : node.kind === 'hub'
                      ? 24
                      : node.kind === 'socket'
                        ? 8
                        : node.kind === 'passive'
                          ? 11
                          : 6.5,
        };

        nodes.push(world);
        byId.set(node.id, world);
    }

    const edges = props.tree.edges
        .map(([a, b]) => ({ a: byId.get(a), b: byId.get(b) }))
        .filter((edge): edge is { a: WorldNode; b: WorldNode } =>
            Boolean(edge.a && edge.b),
        );

    return { nodes, edges };
});

const container = ref<HTMLDivElement | null>(null);
const canvas = ref<HTMLCanvasElement | null>(null);

type HoverCard = { x: number; y: number; title: string; meta: string };

const hover = ref<HoverCard | null>(null);

const view = { x: 0, y: 0, zoom: 1 };
let sized = false;

function fitToScene(): void {
    const el = canvas.value;
    const { nodes } = scene.value;

    if (!el || nodes.length === 0) {
        return;
    }

    const xs = nodes.map((n) => n.x);
    const ys = nodes.map((n) => n.y);
    const minX = Math.min(...xs) - 60;
    const maxX = Math.max(...xs) + 60;
    const minY = Math.min(...ys) - 60;
    const maxY = Math.max(...ys) + 60;

    view.zoom = Math.min(
        1.6,
        Math.min(
            el.clientWidth / (maxX - minX),
            el.clientHeight / (maxY - minY),
        ),
    );
    view.x = el.clientWidth / 2 - ((minX + maxX) / 2) * view.zoom;
    view.y = el.clientHeight / 2 - ((minY + maxY) / 2) * view.zoom;
}

/**
 * Atlas sheets by texture SNO, lazily loaded; a 404 is remembered and the
 * node keeps its plain frame — same fallback philosophy as everywhere else.
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

/** A D4-style node: a beveled diamond — dark gold outer rim, light gold
 * inner rim, dark center. */
function drawFrame(
    context: CanvasRenderingContext2D,
    x: number,
    y: number,
    half: number,
    lit: boolean,
    circle = false,
): void {
    const path = (r: number) => {
        context.beginPath();
        if (circle) {
            context.arc(x, y, r, 0, Math.PI * 2);
        } else {
            context.moveTo(x, y - r);
            context.lineTo(x + r, y);
            context.lineTo(x, y + r);
            context.lineTo(x - r, y);
            context.closePath();
        }
    };

    if (lit) {
        context.shadowColor = C.glow;
        context.shadowBlur = 16;
    }

    path(half);
    context.fillStyle = C.node;
    context.fill();
    context.shadowBlur = 0;
    context.strokeStyle = lit ? C.frame : C.frameDim;
    context.lineWidth = lit ? 3 : 1.6;
    context.stroke();

    path(half - (lit ? 3.5 : 2.5));
    context.strokeStyle = lit ? C.frameBright : C.frameDim + '99';
    context.lineWidth = 1.2;
    context.stroke();
}

function draw(): void {
    const el = canvas.value;
    const context = el?.getContext('2d');

    if (!el || !context) {
        return;
    }

    const dpr = window.devicePixelRatio || 1;

    if (
        el.width !== el.clientWidth * dpr ||
        el.height !== el.clientHeight * dpr
    ) {
        el.width = el.clientWidth * dpr;
        el.height = el.clientHeight * dpr;
    }

    if (!sized) {
        fitToScene();
        sized = true;
    }

    context.setTransform(dpr, 0, 0, dpr, 0, 0);
    context.clearRect(0, 0, el.clientWidth, el.clientHeight);
    context.translate(view.x, view.y);
    context.scale(view.zoom, view.zoom);

    const { nodes, edges } = scene.value;
    const mono = '"JetBrains Mono", ui-monospace, monospace';

    context.lineCap = 'round';

    // The tree's spine: cluster gates chained in unlock order, the way the
    // game draws the path between clusters.
    const gates = nodes
        .filter((n) => n.node.kind === 'hub')
        .sort((a, b) => a.node.level - b.node.level || a.y - b.y);

    for (let i = 0; i + 1 < gates.length; i++) {
        context.beginPath();
        context.moveTo(gates[i].x, gates[i].y);
        context.lineTo(gates[i + 1].x, gates[i + 1].y);
        context.strokeStyle = C.frameDim;
        context.lineWidth = 5;
        context.globalAlpha = 0.7;
        context.stroke();
        context.globalAlpha = 1;
    }

    for (const { a, b } of edges) {
        const on = (n: WorldNode) =>
            n.allocated || n.parentLit || n.node.kind === 'hub';
        const lit = on(a) && on(b) && (a.allocated || b.allocated);

        context.beginPath();
        context.moveTo(a.x, a.y);
        context.lineTo(b.x, b.y);
        context.strokeStyle = lit ? C.litRail : C.rail;
        context.lineWidth = lit ? 3.5 : 1.2;
        context.globalAlpha = lit ? 0.9 : 0.5;
        context.stroke();
        context.globalAlpha = 1;
    }

    for (const world of nodes) {
        const kind = world.node.kind;

        // Actives and modifiers are diamonds; passives and their sockets are
        // circles; hubs are small connective diamonds — the game's shapes.
        drawFrame(
            context,
            world.x,
            world.y,
            world.radius,
            world.allocated,
            kind === 'passive' || kind === 'socket',
        );

        // A taken skill's modifier satellites warm up without full glow.
        if (world.parentLit) {
            context.beginPath();
            context.moveTo(world.x, world.y - world.radius + 2.5);
            context.lineTo(world.x + world.radius - 2.5, world.y);
            context.lineTo(world.x, world.y + world.radius - 2.5);
            context.lineTo(world.x - world.radius + 2.5, world.y);
            context.closePath();
            context.strokeStyle = C.frame + 'aa';
            context.lineWidth = 1.4;
            context.stroke();
        }

        // The node's real art inside the frame, when its sheet exists —
        // skill crops, and the game's own gate / socket chrome.
        let drewIcon = false;
        const icon = world.node.icon;

        if (icon && typeof icon.texture === 'number') {
            const sheet = atlasImage(icon.texture);

            if (sheet) {
                const sx = icon.u0 * sheet.naturalWidth;
                const sy = icon.v0 * sheet.naturalHeight;
                const sw = (icon.u1 - icon.u0) * sheet.naturalWidth;
                const sh = (icon.v1 - icon.v0) * sheet.naturalHeight;

                if (sw > 0 && sh > 0) {
                    const r = world.radius - 3;
                    const circle =
                        kind === 'passive' || kind === 'socket';
                    context.save();
                    context.beginPath();
                    if (kind === 'hub') {
                        context.rect(
                            world.x - world.radius,
                            world.y - world.radius,
                            world.radius * 2,
                            world.radius * 2,
                        );
                    } else if (circle) {
                        context.arc(world.x, world.y, r, 0, Math.PI * 2);
                    } else {
                        context.moveTo(world.x, world.y - r);
                        context.lineTo(world.x + r, world.y);
                        context.lineTo(world.x, world.y + r);
                        context.lineTo(world.x - r, world.y);
                        context.closePath();
                    }
                    context.clip();
                    context.globalAlpha = world.allocated ? 1 : 0.45;
                    context.drawImage(
                        sheet,
                        sx,
                        sy,
                        sw,
                        sh,
                        world.x - world.radius,
                        world.y - world.radius,
                        world.radius * 2,
                        world.radius * 2,
                    );
                    context.restore();
                    drewIcon = true;
                }
            }
        }

        if (world.allocated && kind === 'passive') {
            context.beginPath();
            context.arc(world.x, world.y, world.radius / 2.6, 0, Math.PI * 2);
            context.fillStyle = C.passive;
            context.fill();
        } else if (world.allocated && kind !== 'hub' && !drewIcon) {
            context.beginPath();
            context.arc(world.x, world.y, world.radius / 3.2, 0, Math.PI * 2);
            context.fillStyle = C.frameBright;
            context.fill();
        }

        // Only the skill node itself gets its name and rank written out.
        if (world.allocated && world.node.name && kind === 'skill') {
            context.fillStyle = C.labelBright;
            context.font = `600 11px ${mono}`;
            context.textAlign = 'center';
            context.fillText(
                world.node.name,
                world.x,
                world.y + world.radius + 14,
            );

            if (world.rank) {
                context.fillStyle = C.glow;
                context.font = `700 10px ${mono}`;
                context.fillText(
                    String(world.rank),
                    world.x + world.radius * 0.9,
                    world.y - world.radius * 0.7,
                );
            }
        }
    }
}

/* Interaction: identical grammar to the paragon canvas. */
let dragging = false;
let moved = 0;
let last = { x: 0, y: 0 };

function hitNode(clientX: number, clientY: number): WorldNode | null {
    const rect = canvas.value!.getBoundingClientRect();
    const x = (clientX - rect.left - view.x) / view.zoom;
    const y = (clientY - rect.top - view.y) / view.zoom;

    for (const world of scene.value.nodes) {
        const reach = world.radius + 4;

        if (Math.abs(x - world.x) <= reach && Math.abs(y - world.y) <= reach) {
            return world;
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
        view.x += event.clientX - last.x;
        view.y += event.clientY - last.y;
        moved +=
            Math.abs(event.clientX - last.x) + Math.abs(event.clientY - last.y);
        last = { x: event.clientX, y: event.clientY };
        draw();
        return;
    }

    const world = hitNode(event.clientX, event.clientY);
    const rect = canvas.value!.getBoundingClientRect();

    hover.value = world
        ? {
              x: Math.min(
                  Math.max(event.clientX - rect.left, 100),
                  rect.width - 100,
              ),
              y: event.clientY - rect.top - 14,
              title:
                  world.node.name ??
                  (world.node.kind === 'socket'
                      ? 'Passive node'
                      : world.node.kind === 'modifier'
                        ? 'Skill modifier'
                        : 'Cluster gate'),
              meta: [
                  world.node.kind === 'modifier' && world.node.skill
                      ? `${world.node.skill} modifier`
                      : world.node.kind,
                  world.allocated
                      ? world.rank
                          ? `rank ${world.rank}`
                          : 'taken'
                      : 'not taken',
                  world.node.level > 1 ? `lvl ${world.node.level}` : null,
              ]
                  .filter(Boolean)
                  .join(' · '),
          }
        : null;
}

function onPointerUp(): void {
    dragging = false;
}

function onWheel(event: WheelEvent): void {
    event.preventDefault();

    const factor = event.deltaY < 0 ? 1.12 : 1 / 1.12;
    const next = Math.min(3, Math.max(0.3, view.zoom * factor));
    const rect = canvas.value!.getBoundingClientRect();
    const px = event.clientX - rect.left;
    const py = event.clientY - rect.top;

    view.x = px - ((px - view.x) / view.zoom) * next;
    view.y = py - ((py - view.y) / view.zoom) * next;
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

/** The hovered entity's icon, when the dictionary carries it. */
const hoverIcon = computed(() =>
    hover.value ? (props.entityFor?.(hover.value.title)?.icon ?? null) : null,
);
</script>

<template>
    <div
        ref="container"
        class="relative h-[440px] w-full overflow-hidden rounded-[var(--radius-md)] border border-[var(--border-subtle)]"
        style="background: #07090d"
    >
        <canvas
            ref="canvas"
            class="block h-full w-full touch-none"
            style="cursor: grab"
            @pointerdown="onPointerDown"
            @pointermove="onPointerMove"
            @pointerup="onPointerUp"
            @pointerleave="hover = null"
            @wheel="onWheel"
            @dblclick="resetView"
        />

        <div
            v-if="hover"
            class="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-full rounded-[var(--radius-sm)] border px-2.5 py-1.5"
            :style="{
                left: `${hover.x}px`,
                top: `${hover.y}px`,
                background: '#151a21',
                borderColor: '#2f3a49',
                boxShadow: '0 8px 24px rgba(0,0,0,0.5)',
            }"
        >
            <div class="flex items-center gap-2">
                <span
                    v-if="hoverIcon"
                    class="inline-block shrink-0 rounded-[4px]"
                    :style="atlasStyle(hoverIcon, 22)"
                />
                <p
                    class="text-[13px] leading-tight font-semibold whitespace-nowrap"
                    style="color: #e8ebef"
                >
                    {{ hover.title }}
                </p>
            </div>
            <p
                class="mt-0.5 font-mono text-[11px] uppercase"
                style="color: #6c7c8f; letter-spacing: 0.08em"
            >
                {{ hover.meta }}
            </p>
        </div>

        <p
            class="pointer-events-none absolute bottom-3 left-3 font-mono text-[11px]"
            style="color: #6c7c8f"
        >
            drag to pan · scroll to zoom · double-click to reset
        </p>
    </div>
</template>
