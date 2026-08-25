<script setup lang="ts">
import { computed } from 'vue';
import Poe2BuildEdit from '@/components/games/poe2/BuildEdit.vue';
import type { Poe2BuildEditProps } from '@/components/games/poe2/types';
import SeoHead from '@/components/SeoHead.vue';

/**
 * The build editor route. The form fields follow the game's build anatomy, so
 * this page only resolves the game's editor and owns the page metadata.
 */
const props = defineProps<
    Poe2BuildEditProps & {
        spriteUrl: string;
        treeUrl: string | null;
        ascendancyKey: string | null;
        ascendancyPathIds: number[];
    }
>();

/** PoE 2 is the only game with an editor today. */
const supported = computed(() => props.game.slug === 'poe2');
</script>

<template>
    <div>
        <SeoHead :title="`Edit ${build.name}`" noindex />

        <Poe2BuildEdit v-if="supported" v-bind="props" />
        <p v-else class="py-16 text-[15px] text-[var(--fg-2)]">
            The editor for {{ game.name }} is not live yet.
        </p>
    </div>
</template>
