<script setup lang="ts">
import { computed } from 'vue';
import Poe2BuildShow from '@/components/games/poe2/BuildShow.vue';
import type { Poe2BuildShowProps } from '@/components/games/poe2/types';
import SeoHead from '@/components/SeoHead.vue';
import { ogImage } from '@/routes/builds';

/**
 * The build page route. Build anatomy is game specific — PoE 2 has gems,
 * support gems, spirit and a passive tree that no other queued game shares —
 * so this page only resolves the game's renderer and owns the page metadata.
 */
const props = defineProps<Poe2BuildShowProps>();

const definition = computed(() => props.build.definition);

const seoIdentity = computed(() =>
    [
        [definition.value.class, definition.value.ascendancy]
            .filter(Boolean)
            .join(' · '),
        definition.value.level ? `level ${definition.value.level}` : '',
    ]
        .filter(Boolean)
        .join(', '),
);

const seoDescription = computed(
    () =>
        props.build.summary ??
        (seoIdentity.value
            ? `${seoIdentity.value} build for ${props.game.name}.`
            : `A build for ${props.game.name}.`),
);

/** PoE 2 is the only game with a build renderer today. */
const supported = computed(() => props.game.slug === 'poe2');
</script>

<template>
    <div>
        <SeoHead
            :title="build.name"
            :description="seoDescription"
            og-type="article"
            :og-image="ogImage.url(build.id)"
        />

        <Poe2BuildShow v-if="supported" v-bind="props" />
        <p v-else class="py-16 text-[15px] text-[var(--fg-2)]">
            Build pages for {{ game.name }} are not live yet.
        </p>
    </div>
</template>
