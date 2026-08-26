<script setup lang="ts">
import { computed } from 'vue';
import Diablo4BuildEdit from '@/components/games/diablo-4/BuildEdit.vue';
import type { D4BuildEditProps } from '@/components/games/diablo-4/types';
import Poe2BuildEdit from '@/components/games/poe2/BuildEdit.vue';
import type { Poe2BuildEditProps } from '@/components/games/poe2/types';
import SeoHead from '@/components/SeoHead.vue';

/**
 * The build editor route. The form fields follow the game's build anatomy, so
 * this page only resolves the game's editor and owns the page metadata.
 */
const props = defineProps<
    | (Poe2BuildEditProps & {
          spriteUrl: string;
          treeUrl: string | null;
          ascendancyKey: string | null;
          ascendancyPathIds: number[];
      })
    | D4BuildEditProps
>();

const renderer = computed(() =>
    props.game.slug === 'diablo-4' ? 'diablo-4' : 'poe2',
);

const poe2 = computed(
    () =>
        props as Poe2BuildEditProps & {
            spriteUrl: string;
            treeUrl: string | null;
            ascendancyKey: string | null;
            ascendancyPathIds: number[];
        },
);

const diablo4 = computed(() => props as D4BuildEditProps);
</script>

<template>
    <div>
        <SeoHead />

        <Diablo4BuildEdit
            v-if="renderer === 'diablo-4'"
            :game="diablo4.game"
            :build="diablo4.build"
            :options="diablo4.options"
            :checklist="diablo4.checklist"
            :paragon-boards="diablo4.paragonBoards"
        />
        <Poe2BuildEdit v-else v-bind="poe2" />
    </div>
</template>
