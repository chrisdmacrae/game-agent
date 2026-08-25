<script setup lang="ts">
import {
    gemColor,
    rarityColor,
    spriteStyle,
} from '@/components/games/poe2/build';
import type { Poe2Entity } from '@/components/games/poe2/types';

/**
 * The floating hover card for a gem, support, passive or unique. One instance
 * lives on the page and is positioned by the `[data-entity]` delegation, so it
 * works inside the tabs and inside the server-rendered guide HTML alike.
 */
defineProps<{
    entity: Poe2Entity;
    spriteUrl: string;
    style: Record<string, string>;
}>();
</script>

<template>
    <div
        class="pointer-events-none fixed z-50 w-[340px] rounded-[var(--radius-md)] border border-[var(--border-subtle)] bg-[var(--surface-raised)] p-4 [box-shadow:var(--shadow-2)]"
        :style="style"
    >
        <!-- Gem / support -->
        <template v-if="entity.kind === 'gem' || entity.kind === 'support'">
            <div class="mb-1 flex items-center gap-2">
                <img
                    v-if="entity.icon"
                    :src="entity.icon"
                    :alt="entity.name"
                    class="size-8 rounded-[var(--radius-xs)] border border-[var(--border-subtle)] object-contain"
                />
                <span
                    v-else
                    class="flex size-6 items-center justify-center rounded-[var(--radius-xs)] border border-[var(--border-subtle)]"
                >
                    <span
                        class="size-2 rounded-[2px]"
                        :style="{ background: gemColor(entity) }"
                    />
                </span>
                <span class="text-[15px] font-semibold text-[var(--fg-1)]">
                    {{ entity.name }}
                </span>
                <span
                    class="font-mono text-[11px] font-bold tracking-[0.14em] text-[var(--fg-3)] uppercase"
                >
                    {{ entity.kind }}
                </span>
            </div>
            <p
                v-if="entity.tags?.length"
                class="mb-2 font-mono text-[12px] text-[var(--fg-3)]"
            >
                {{ entity.tags.join(' · ') }}
            </p>
            <p
                v-if="entity.description"
                class="mb-2 text-[13px] text-[var(--fg-2)]"
            >
                {{ entity.description }}
            </p>
            <p
                v-if="entity.spirit_reservation"
                class="mb-2 font-mono text-[12px] text-[var(--blue-400)]"
            >
                Reserves {{ entity.spirit_reservation }} spirit
            </p>
            <ul
                v-if="entity.stat_text?.length"
                class="space-y-0.5 font-mono text-[12px] text-[var(--fg-2)]"
            >
                <li v-for="line in entity.stat_text" :key="line">{{ line }}</li>
            </ul>
        </template>

        <!-- Passive -->
        <template v-else-if="entity.kind === 'passive'">
            <div class="mb-2 flex items-center gap-2">
                <span
                    v-if="entity.sprite"
                    class="inline-block shrink-0"
                    :style="spriteStyle(entity, spriteUrl)!"
                />
                <div>
                    <p
                        class="font-mono text-[11px] leading-[1.4] font-bold tracking-[0.14em] text-[var(--teal-300)] uppercase"
                    >
                        {{ entity.name }}
                    </p>
                    <p class="font-mono text-[12px] text-[var(--fg-3)]">
                        {{ entity.passive_kind }} passive
                    </p>
                </div>
            </div>
            <ul class="space-y-0.5 font-mono text-[12px] text-[var(--fg-2)]">
                <li
                    v-for="stat in entity.stats"
                    :key="stat"
                    class="whitespace-pre-line"
                >
                    {{ stat }}
                </li>
            </ul>
        </template>

        <!-- Unique -->
        <template v-else-if="entity.kind === 'unique'">
            <div class="mb-2 flex items-center gap-2">
                <img
                    v-if="entity.icon"
                    :src="entity.icon"
                    :alt="entity.name"
                    class="max-h-12 rounded-[var(--radius-xs)] border border-[var(--border-subtle)] bg-[var(--ink-950)] object-contain px-1"
                />
                <div>
                    <p
                        class="text-[15px] font-semibold"
                        :style="{ color: rarityColor('unique') }"
                    >
                        {{ entity.name }}
                    </p>
                    <p class="font-mono text-[12px] text-[var(--fg-3)]">
                        {{ entity.base_name }} · {{ entity.item_class }}
                    </p>
                </div>
            </div>
            <ul class="space-y-0.5 font-mono text-[12px] text-[var(--fg-2)]">
                <li v-for="mod in entity.mods" :key="mod">{{ mod }}</li>
            </ul>
        </template>
    </div>
</template>
