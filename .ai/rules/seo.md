---
paths:
  - 'app/Domain/Seo/**'
---

# Seo

## Page meta is a controller prop, not a Vue literal
Every page's title/description/card comes from `App\Domain\Seo\PageMeta`, passed unkeyed into `Inertia::render()` (it implements `ProvidesInertiaProperties`, so it replaces the URL-only `seo` bag that HandleInertiaRequests shares). `resources/js/components/SeoHead.vue` renders that bag; props passed to it still override per value.

There is no built SSR bundle, so `resources/views/partials/seo.blade.php` renders the same tags into the fallback slot of `<x-inertia::head>` — that slot only renders when SSR is absent, and each tag carries a `data-inertia` key matching SeoHead's `head-key`, so Vue replaces them on hydration instead of appending duplicates. Change one, change the other.

Titles get " — Build Your Build" appended by the `title` callback in resources/js/app.ts and by the partial; do not bake the site name into a PageMeta title.
