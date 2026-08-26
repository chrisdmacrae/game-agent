<script setup lang="ts">
import { atlasStyle, rarityColor } from '@/components/games/diablo-4/build';
import type { D4Entity } from '@/components/games/diablo-4/types';

/**
 * The floating hover card for a skill, aspect, unique, glyph or paragon node.
 * One instance lives on the page and is positioned by the `[data-entity]`
 * delegation, so it works inside the tabs and inside the server-rendered
 * guide HTML alike. Entities without extracted atlas art show a letter badge.
 */
defineProps<{
    entity: D4Entity;
    style: Record<string, string>;
}>();

const KIND_LABELS: Record<D4Entity['kind'], string> = {
    skill: 'skill',
    aspect: 'aspect',
    unique: 'unique',
    glyph: 'glyph',
    'paragon-node': 'paragon',
};
</script>

<template>
    <div
        class="pointer-events-none fixed z-50 w-[340px] rounded-[var(--radius-md)] border border-[var(--border-subtle)] bg-[var(--surface-raised)] p-4 [box-shadow:var(--shadow-2)]"
        :style="style"
    >
        <div class="mb-2 flex items-center gap-2">
            <span
                v-if="entity.icon"
                class="inline-block shrink-0 rounded-[var(--radius-xs)] border border-[var(--border-subtle)] bg-[var(--ink-950)]"
                :style="atlasStyle(entity.icon, 32)"
            />
            <span
                v-else
                class="flex size-8 shrink-0 items-center justify-center rounded-[var(--radius-xs)] border border-[var(--border-subtle)] bg-[var(--ink-950)] font-mono text-[13px] font-bold text-[var(--fg-3)]"
            >
                {{ entity.name.charAt(0) }}
            </span>
            <div class="min-w-0">
                <p
                    class="truncate text-[15px] leading-tight font-semibold"
                    :style="
                        entity.kind === 'unique'
                            ? {
                                  color: rarityColor(
                                      entity.is_mythic ? 'mythic' : 'unique',
                                  ),
                              }
                            : { color: 'var(--fg-1)' }
                    "
                >
                    {{ entity.name }}
                </p>
                <p class="font-mono text-[11px] text-[var(--fg-3)]">
                    <span class="font-bold tracking-[0.14em] uppercase">
                        {{ KIND_LABELS[entity.kind] }}
                    </span>
                    <template v-if="entity.kind === 'skill'">
                        · {{ entity.category }}
                        <template v-if="entity.rank">
                            · rank {{ entity.rank
                            }}<template v-if="entity.max_rank"
                                >/{{ entity.max_rank }}</template
                            >
                        </template>
                    </template>
                    <template v-else-if="entity.kind === 'unique'">
                        · {{ entity.item_type }}
                    </template>
                    <template v-else-if="entity.kind === 'paragon-node'">
                        · {{ entity.rarity }} · {{ entity.board }}
                    </template>
                    <template v-else-if="entity.class_name">
                        · {{ entity.class_name }}
                    </template>
                </p>
            </div>
        </div>

        <p
            v-if="entity.tags?.length"
            class="mb-2 font-mono text-[12px] text-[var(--fg-3)]"
        >
            {{ entity.tags.join(' · ') }}
        </p>

        <p
            v-if="entity.description"
            class="text-[13px] whitespace-pre-line text-[var(--fg-2)]"
        >
            {{ entity.description }}
        </p>

        <ul
            v-if="entity.effects?.length"
            class="space-y-1 font-mono text-[12px] text-[var(--fg-2)]"
        >
            <li v-for="effect in entity.effects" :key="effect">
                {{ effect }}
            </li>
        </ul>

        <p
            v-if="entity.attributes?.length"
            class="font-mono text-[12px] text-[var(--fg-2)]"
        >
            {{ entity.attributes.join(' · ') }}
        </p>

        <p
            v-if="entity.item_types?.length"
            class="mt-2 font-mono text-[11px] text-[var(--fg-3)]"
        >
            Fits: {{ entity.item_types.join(', ') }}
        </p>
    </div>
</template>
