<?php

namespace App\Domain\Seo;

use Inertia\ProvidesInertiaProperties;
use Inertia\RenderContext;

/**
 * The `seo` prop for one page.
 *
 * A public page's title, description and social card belong to its controller,
 * not to its Vue component: that keeps them assertable in tests and keeps the
 * canonical URL in one place. HandleInertiaRequests shares the URL-only bag as
 * the default; passing a PageMeta into Inertia::render() replaces it.
 *
 * resources/js/components/SeoHead.vue renders whatever is here, and a page may
 * still override any single value with an explicit prop.
 */
class PageMeta implements ProvidesInertiaProperties
{
    /**
     * @param  string  $title  Rendered as "{title} — Build Your Build".
     * @param  string|null  $description  Meta description and og:description.
     * @param  'website'|'article'  $ogType
     * @param  string|null  $ogImage  Absolute or root-relative image URL.
     * @param  bool  $noindex  Session or private pages: no canonical, no card.
     */
    public function __construct(
        public string $title,
        public ?string $description = null,
        public string $ogType = 'website',
        public ?string $ogImage = null,
        public bool $noindex = false,
    ) {}

    /**
     * The bag shared with every page when a controller provides no meta of
     * its own. The canonical URL drops the query string, so filtered and
     * UTM-tagged hub URLs all point back at one address.
     *
     * @return array{url: string, title: null, description: null, ogType: 'website', ogImage: null, noindex: false}
     */
    public static function shared(string $url): array
    {
        return [
            'url' => $url,
            'title' => null,
            'description' => null,
            'ogType' => 'website',
            'ogImage' => null,
            'noindex' => false,
        ];
    }

    /**
     * @return array{seo: array<string, mixed>}
     */
    public function toInertiaProperties(RenderContext $context): iterable
    {
        return [
            'seo' => [
                ...self::shared($context->request->url()),
                'title' => $this->title,
                'description' => $this->description,
                'ogType' => $this->ogType,
                'ogImage' => $this->ogImage,
                'noindex' => $this->noindex,
            ],
        ];
    }
}
