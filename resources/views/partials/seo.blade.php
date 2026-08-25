@php
    /**
     * Server-rendered head tags, from the `seo` page prop that
     * App\Domain\Seo\PageMeta fills in.
     *
     * This is the fallback slot of <x-inertia::head>: it renders only when
     * there is no SSR response, so a crawler that does not run JavaScript --
     * and a plain view-source -- still sees the real title, description and
     * card. The `data-inertia` keys match the head-key values in
     * resources/js/components/SeoHead.vue, so once Vue mounts it replaces
     * these elements instead of appending a second copy of each.
     */
    $seo = $page['props']['seo'] ?? [];
    $siteName = config('app.name');
    $pageTitle = $seo['title'] ?? null;
    $description = $seo['description'] ?? null;
    $canonical = $seo['url'] ?? url()->current();
    $ogImage = $seo['ogImage'] ?? null;
    $noindex = (bool) ($seo['noindex'] ?? false);
@endphp
<title data-inertia="">{{ $pageTitle ? $pageTitle.' — '.$siteName : $siteName }}</title>
@if ($description)
<meta data-inertia="description" name="description" content="{{ $description }}">
@endif
@if ($noindex)
<meta data-inertia="robots" name="robots" content="noindex, nofollow">
@else
<link data-inertia="canonical" rel="canonical" href="{{ $canonical }}">
<meta data-inertia="og:site_name" property="og:site_name" content="{{ $siteName }}">
<meta data-inertia="og:type" property="og:type" content="{{ $seo['ogType'] ?? 'website' }}">
<meta data-inertia="og:title" property="og:title" content="{{ $pageTitle ?? $siteName }}">
@if ($description)
<meta data-inertia="og:description" property="og:description" content="{{ $description }}">
@endif
<meta data-inertia="og:url" property="og:url" content="{{ $canonical }}">
@if ($ogImage)
<meta data-inertia="og:image" property="og:image" content="{{ $ogImage }}">
<meta data-inertia="og:image:width" property="og:image:width" content="1200">
<meta data-inertia="og:image:height" property="og:image:height" content="630">
<meta data-inertia="twitter:image" name="twitter:image" content="{{ $ogImage }}">
@endif
<meta data-inertia="twitter:card" name="twitter:card" content="{{ $ogImage ? 'summary_large_image' : 'summary' }}">
@endif
