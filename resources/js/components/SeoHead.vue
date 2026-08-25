<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Every page's head tags.
 *
 * Public pages get their meta from the controller (App\Domain\Seo\PageMeta),
 * shared as the `seo` prop; anything passed here overrides it, which is how
 * private pages declare a title and opt out of indexing.
 */
const props = withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        canonical?: string;
        ogType?: 'website' | 'article';
        ogImage?: string;
        noindex?: boolean;
    }>(),
    {
        title: undefined,
        description: undefined,
        canonical: undefined,
        ogType: undefined,
        ogImage: undefined,
        noindex: undefined,
    },
);

const page = usePage();

const seo = computed(() => page.props.seo);

const title = computed(() => props.title ?? seo.value.title ?? page.props.name);

const description = computed(
    () => props.description ?? seo.value.description ?? undefined,
);

const ogType = computed(() => props.ogType ?? seo.value.ogType ?? 'website');

const noindex = computed(() => props.noindex ?? seo.value.noindex ?? false);

const canonicalUrl = computed(() => props.canonical ?? seo.value.url);

// Crawlers expect absolute og:image URLs; resolve relative paths (e.g. from
// Wayfinder) against the canonical URL's origin.
const ogImageUrl = computed(() => {
    const image = props.ogImage ?? seo.value.ogImage;

    return image ? new URL(image, seo.value.url).href : null;
});
</script>

<template>
    <Head :title="title">
        <meta
            v-if="description"
            head-key="description"
            name="description"
            :content="description"
        />
        <meta
            v-if="noindex"
            head-key="robots"
            name="robots"
            content="noindex, nofollow"
        />
        <link
            v-if="!noindex"
            head-key="canonical"
            rel="canonical"
            :href="canonicalUrl"
        />
        <meta
            v-if="!noindex"
            head-key="og:site_name"
            property="og:site_name"
            :content="page.props.name"
        />
        <meta
            v-if="!noindex"
            head-key="og:type"
            property="og:type"
            :content="ogType"
        />
        <meta
            v-if="!noindex"
            head-key="og:title"
            property="og:title"
            :content="title"
        />
        <meta
            v-if="!noindex && description"
            head-key="og:description"
            property="og:description"
            :content="description"
        />
        <meta
            v-if="!noindex"
            head-key="og:url"
            property="og:url"
            :content="canonicalUrl"
        />
        <meta
            v-if="!noindex && ogImageUrl"
            head-key="og:image"
            property="og:image"
            :content="ogImageUrl"
        />
        <meta
            v-if="!noindex && ogImageUrl"
            head-key="og:image:width"
            property="og:image:width"
            content="1200"
        />
        <meta
            v-if="!noindex && ogImageUrl"
            head-key="og:image:height"
            property="og:image:height"
            content="630"
        />
        <meta
            v-if="!noindex"
            head-key="twitter:card"
            name="twitter:card"
            :content="ogImageUrl ? 'summary_large_image' : 'summary'"
        />
        <meta
            v-if="!noindex && ogImageUrl"
            head-key="twitter:image"
            name="twitter:image"
            :content="ogImageUrl"
        />
    </Head>
</template>
