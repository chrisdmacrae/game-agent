<script setup lang="ts">
import { computed } from 'vue';
import Diablo4BuildShow from '@/components/games/diablo-4/BuildShow.vue';
import type { D4BuildShowProps } from '@/components/games/diablo-4/types';
import Poe2BuildShow from '@/components/games/poe2/BuildShow.vue';
import type { Poe2BuildShowProps } from '@/components/games/poe2/types';
import SeoHead from '@/components/SeoHead.vue';

/**
 * The build page route. Build anatomy is game specific — PoE 2 has gems,
 * support gems, spirit and a passive tree that Diablo IV's action bar, paragon
 * boards and keyed gear map share nothing with — so this page only resolves the
 * game's renderer.
 */
const props = defineProps<Poe2BuildShowProps | D4BuildShowProps>();

const renderer = computed(() =>
    props.game.slug === 'diablo-4' ? 'diablo-4' : 'poe2',
);

const poe2 = computed(() => props as Poe2BuildShowProps);
const diablo4 = computed(() => props as D4BuildShowProps);
</script>

<template>
    <div>
        <!-- Title, description and the per-build card come from
             BuildController's PageMeta. -->
        <SeoHead />

        <Diablo4BuildShow
            v-if="renderer === 'diablo-4'"
            :build="diablo4.build"
            :game="diablo4.game"
            :viewer="diablo4.viewer"
            :similar-builds="diablo4.similarBuilds"
            :entities="diablo4.entities"
            :paragon-boards="diablo4.paragonBoards"
            :skill-tree="diablo4.skillTree"
        />
        <Poe2BuildShow v-else v-bind="poe2" />
    </div>
</template>
