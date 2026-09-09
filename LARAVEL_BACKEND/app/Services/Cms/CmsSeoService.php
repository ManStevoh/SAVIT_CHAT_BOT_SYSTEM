<?php

namespace App\Services\Cms;

use App\Models\BlogPost;
use App\Models\BookingSetting;
use App\Models\CmsPage;
use App\Models\Company;
use App\Models\LandingFaq;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Support\HomeSeoCopy;
use App\Support\PublicMarketingPages;
use App\Support\SeoLandingCatalog;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CmsSeoService
{
    /**
     * @return array<string, mixed>|null
     */
    public function forSlug(string $slug): ?array
    {
        if (! Schema::hasTable('cms_pages')) {
            return $this->fallback($slug);
        }

        try {
            $page = CmsPage::where('slug', $slug)->where('is_published', true)->first();
        } catch (\Throwable) {
            return $this->fallback($slug);
        }

        if (! $page) {
            return $this->fallback($slug);
        }

        return $this->toPayload($page);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(CmsPage $page): array
    {
        $base = $this->appBaseUrl();
        $path = $this->pathForSlug($page->slug);
        $canonical = $page->canonical_url
            ? $this->absoluteUrl($page->canonical_url)
            : $base.$path;

        $title = trim((string) ($page->meta_title ?: $page->title));
        $description = trim((string) ($page->meta_description ?? ''));
        $ogTitle = trim((string) ($page->og_title ?: $title));
        $ogDescription = trim((string) ($page->og_description ?: $description));
        $ogImage = $this->absoluteUrl($page->og_image) ?: $this->defaultOgImage();
        $siteName = (string) config('app.name', 'RelayIQ');

        $h1 = $title;
        $lede = $description;
        if ($page->slug === 'home') {
            $heroTitle = $this->firstHeroTitle($page);
            $heroLede = $this->firstHeroLede($page);
            if (HomeSeoCopy::shouldReplaceHero($heroTitle)) {
                $h1 = HomeSeoCopy::h1();
                $lede = HomeSeoCopy::lede();
            } elseif ($heroTitle !== '') {
                $h1 = $heroTitle;
                $lede = $heroLede !== '' ? $heroLede : $description;
            }
            if (HomeSeoCopy::shouldReplaceMeta($title, $description)) {
                $title = HomeSeoCopy::title();
                $description = HomeSeoCopy::description();
            }
            if (HomeSeoCopy::shouldReplaceMeta($ogTitle, $ogDescription)) {
                $ogTitle = HomeSeoCopy::title();
                $ogDescription = HomeSeoCopy::description();
            }
        }

        $catalog = SeoLandingCatalog::get($page->slug);
        if (is_array($catalog)) {
            $h1 = $catalog['h1'];
            $lede = $catalog['lede'];
        }

        $breadcrumbs = [
            ['name' => 'Home', 'url' => $base.'/'],
        ];
        if ($page->slug !== 'home') {
            $breadcrumbs[] = ['name' => $page->title ?: Str::title($page->slug), 'url' => $canonical];
        }

        $websiteNode = [
            '@type' => 'WebSite',
            '@id' => $base.'/#website',
            'name' => $siteName,
            'url' => $base,
            'publisher' => ['@id' => $base.'/#organization'],
        ];
        if (PublicMarketingPages::enabled('blog')) {
            $websiteNode['potentialAction'] = [
                '@type' => 'SearchAction',
                'target' => $base.'/blog?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ];
        }

        $pageSpecificNode = null;
        if ($page->slug === 'contact') {
            $contactOrg = $this->organizationNode($base, $siteName, false);
            $contactOrg['contactPoint'] = [
                '@type' => 'ContactPoint',
                'contactType' => 'customer support',
                'url' => $canonical,
            ];
            $pageSpecificNode = [
                '@type' => 'ContactPage',
                '@id' => $canonical.'#contactpage',
                'url' => $canonical,
                'name' => $title,
                'description' => $description ?: null,
                'mainEntity' => $contactOrg,
            ];
        } elseif ($page->slug === 'about') {
            $pageSpecificNode = [
                '@type' => 'AboutPage',
                '@id' => $canonical.'#aboutpage',
                'url' => $canonical,
                'name' => $title,
                'description' => $description ?: null,
                'about' => ['@id' => $base.'/#organization'],
            ];
        }

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@graph' => array_values(array_filter([
                $this->organizationNode($base, $siteName),
                $websiteNode,
                [
                    '@type' => 'WebPage',
                    '@id' => $canonical.'#webpage',
                    'url' => $canonical,
                    'name' => $title,
                    'description' => $description ?: null,
                    'isPartOf' => ['@id' => $base.'/#website'],
                    'about' => ['@id' => $base.'/#organization'],
                ],
                $pageSpecificNode,
                $this->breadcrumbNode($breadcrumbs),
                in_array($page->slug, ['home', 'pricing'], true)
                    ? $this->softwareApplicationNode($base, $siteName, $description)
                    : null,
                $this->faqNode($page),
            ])),
        ];

        return $this->decoratePayload([
            'title' => $title,
            'description' => $description,
            'h1' => $h1,
            'lede' => $lede,
            'canonical' => $canonical,
            'robots' => $page->robots ?: 'index, follow',
            'ogTitle' => $ogTitle,
            'ogDescription' => $ogDescription,
            'ogImage' => $ogImage,
            'ogType' => 'website',
            'ogUrl' => $canonical,
            'siteName' => $siteName,
            'twitterCard' => 'summary_large_image',
            'jsonLd' => $jsonLd,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function noindex(string $title, ?string $description = null): array
    {
        return $this->decoratePayload([
            'title' => $title,
            'description' => $description,
            'robots' => 'noindex, nofollow',
            'ogTitle' => $title,
            'ogDescription' => $description,
            'ogType' => 'website',
            'siteName' => (string) config('app.name', 'RelayIQ'),
            'twitterCard' => 'summary',
            'jsonLd' => null,
        ]);
    }

    /**
     * Catalog / shop SEO for a tenant storefront.
     *
     * @param  array{q?: mixed, sort?: mixed, category?: mixed, in_stock?: mixed, min_price?: mixed, max_price?: mixed, type?: mixed}|null  $filters
     * @return array<string, mixed>
     */
    public function forStorefrontCatalog(Company $company, ?string $forcedBase = null, ?array $filters = null): array
    {
        if ($company->custom_domain && $company->custom_domain_verified_at) {
            $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';
            $canonical = $scheme.'://'.$company->custom_domain;
        } else {
            $canonical = $this->appBaseUrl().'/s/'.$company->store_slug;
        }

        if ($forcedBase) {
            $canonical = rtrim($forcedBase, '/');
        }

        $theme = is_array($company->storefront_theme) ? $company->storefront_theme : [];
        $title = trim((string) ($theme['seo_title'] ?? '')) ?: ($company->name.' — Shop');
        $description = trim((string) ($theme['seo_description'] ?? ''))
            ?: ('Shop '.$company->name.' online. Browse products and order for delivery or pickup.');
        
        $customOg = !empty($theme['og_image']) ? $this->absoluteUrl($theme['og_image']) : null;
        $ogImage = $customOg ?: ($company->logo ? asset('storage/'.$company->logo) : $this->defaultOgImage());
        $siteName = $company->name;
        $isFiltered = $this->storefrontCatalogIsFiltered($filters);
        $googleVerification = ! empty($theme['google_site_verification']) ? trim((string) $theme['google_site_verification']) : null;

        $storeType = $theme['business_type'] ?? 'OnlineStore';
        $storeNode = [
            '@type' => $storeType,
            '@id' => $canonical.'#store',
            'name' => $company->name,
            'url' => $canonical,
            'image' => $ogImage,
            'description' => $description,
        ];

        if (! empty($company->address) || ! empty($theme['street_address'])) {
            $storeNode['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => $theme['street_address'] ?? $company->address,
                'addressLocality' => $theme['city'] ?? null,
                'addressCountry' => $theme['country'] ?? null,
            ];
        }

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@graph' => array_values(array_filter([
                $storeNode,
                $this->breadcrumbNode([
                    ['name' => 'Home', 'url' => $canonical],
                ]),
            ])),
        ];

        return $this->decoratePayload([
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => $isFiltered ? 'noindex, follow' : 'index, follow',
            'ogTitle' => $title,
            'ogDescription' => $description,
            'ogImage' => $ogImage,
            'ogType' => 'website',
            'ogUrl' => $canonical,
            'siteName' => $siteName,
            'twitterCard' => 'summary_large_image',
            'jsonLd' => $isFiltered ? null : $jsonLd,
            'googleSiteVerification' => $googleVerification,
            'skipAppTitleSuffix' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $filters
     */
    private function storefrontCatalogIsFiltered(?array $filters): bool
    {
        if ($filters === null || $filters === []) {
            return false;
        }

        foreach (['q', 'category', 'type', 'min_price', 'max_price'] as $key) {
            $value = $filters[$key] ?? null;
            if (is_string($value) && trim($value) !== '' && strtolower(trim($value)) !== 'all') {
                return true;
            }
            if (is_numeric($value)) {
                return true;
            }
        }

        $inStock = $filters['in_stock'] ?? null;
        if ($inStock === true || $inStock === 1 || $inStock === '1' || $inStock === 'true') {
            return true;
        }

        $sort = $filters['sort'] ?? null;
        if (is_string($sort) && trim($sort) !== '' && ! in_array($sort, ['name_asc', ''], true)) {
            return true;
        }

        return false;
    }

    /**
     * Product detail page SEO + Product/Offer JSON-LD.
     *
     * @param  array<string, mixed>  $serializedProduct
     * @return array<string, mixed>
     */
    public function forStorefrontProduct(Company $company, Product $product, array $serializedProduct): array
    {
        $productPath = $product->slug ?: (string) $product->id;
        if ($company->custom_domain && $company->custom_domain_verified_at) {
            $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';
            $canonical = $scheme.'://'.$company->custom_domain.'/p/'.$productPath;
        } else {
            $canonical = $this->appBaseUrl().'/s/'.$company->store_slug.'/p/'.$productPath;
        }

        $theme = is_array($company->storefront_theme) ? $company->storefront_theme : [];
        $googleVerification = !empty($theme['google_site_verification']) ? trim((string) $theme['google_site_verification']) : null;

        $title = trim((string) ($product->meta_title ?: '')) ?: ($product->name.' — '.$company->name);
        $description = trim((string) ($product->meta_description ?: ''))
            ?: Str::limit(strip_tags((string) $product->description), 160);
        $ogImage = $serializedProduct['image'] ?? null;
        if (is_string($ogImage) && $ogImage !== '' && ! str_starts_with($ogImage, 'http')) {
            $ogImage = url($ogImage);
        }
        $ogImage = $ogImage ?: ($company->logo ? asset('storage/'.$company->logo) : $this->defaultOgImage());

        $currency = 'KES';
        try {
            $company->loadMissing('settings');
            $currency = $company->settings?->displayCurrencyCode() ?? 'KES';
        } catch (\Throwable) {
            // ignore
        }

        $availability = 'https://schema.org/InStock';
        $stock = (int) ($serializedProduct['stock'] ?? $product->stock ?? 0);
        $trackInventory = (bool) ($serializedProduct['trackInventory'] ?? $product->track_inventory ?? true);
        if ($trackInventory && $stock <= 0) {
            $availability = 'https://schema.org/OutOfStock';
        }

        $offer = [
            '@type' => 'Offer',
            'url' => $canonical,
            'priceCurrency' => $currency,
            'price' => (string) ($serializedProduct['price'] ?? $product->price ?? 0),
            'priceValidUntil' => now()->addYear()->toDateString(),
            'availability' => $availability,
            'itemCondition' => 'https://schema.org/NewCondition',
        ];

        $productNode = [
            '@type' => 'Product',
            '@id' => $canonical.'#product',
            'name' => $product->name,
            'description' => $description ?: null,
            'image' => array_values(array_filter([
                $ogImage,
                ...array_map(function ($img) {
                    if (! is_string($img) || $img === '') {
                        return null;
                    }

                    return str_starts_with($img, 'http') ? $img : url($img);
                }, $serializedProduct['images'] ?? []),
            ])),
            'sku' => (string) $product->id,
            'brand' => [
                '@type' => 'Brand',
                'name' => $company->name,
            ],
            'offers' => $offer,
        ];

        $avgRating = $serializedProduct['ratingAvg'] ?? $serializedProduct['averageRating'] ?? null;
        $ratingCount = $serializedProduct['ratingCount'] ?? $serializedProduct['reviewCount'] ?? null;
        if ($avgRating !== null && $ratingCount !== null && (int) $ratingCount > 0) {
            $productNode['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => (string) $avgRating,
                'reviewCount' => (string) (int) $ratingCount,
            ];
        }

        $shopUrl = $company->custom_domain && $company->custom_domain_verified_at
            ? ((parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https').'://'.$company->custom_domain)
            : ($this->appBaseUrl().'/s/'.$company->store_slug);

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@graph' => array_values(array_filter([
                $productNode,
                $this->breadcrumbNode([
                    ['name' => $company->name, 'url' => $shopUrl],
                    ['name' => $product->name, 'url' => $canonical],
                ]),
            ])),
        ];

        return $this->decoratePayload([
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => 'index, follow',
            'ogTitle' => $title,
            'ogDescription' => $description,
            'ogImage' => $ogImage,
            'ogType' => 'product',
            'ogUrl' => $canonical,
            'siteName' => $company->name,
            'twitterCard' => 'summary_large_image',
            'jsonLd' => $jsonLd,
            'googleSiteVerification' => $googleVerification,
            'skipAppTitleSuffix' => true,
        ]);
    }

    /**
     * Booking appointment page SEO.
     *
     * @return array<string, mixed>
     */
    public function forBookingPage(Company $company, BookingSetting $settings): array
    {
        $base = $this->appBaseUrl();
        $canonical = $base.'/book/'.$settings->public_slug;
        $title = 'Book with '.$company->name;
        $description = 'Schedule an appointment with '.$company->name.'. Select a date and time slot for instant confirmation.';
        $ogImage = $company->logo ? asset('storage/'.$company->logo) : $this->defaultOgImage();

        $theme = is_array($company->storefront_theme) ? $company->storefront_theme : [];
        $googleVerification = !empty($theme['google_site_verification']) ? trim((string) $theme['google_site_verification']) : null;

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@graph' => array_values(array_filter([
                [
                    '@type' => 'Service',
                    '@id' => $canonical.'#service',
                    'name' => 'Appointment Booking — '.$company->name,
                    'provider' => [
                        '@type' => 'LocalBusiness',
                        'name' => $company->name,
                        'url' => $canonical,
                    ],
                    'url' => $canonical,
                    'description' => $description,
                ],
                $this->breadcrumbNode([
                    ['name' => 'Home', 'url' => $base.'/'],
                    ['name' => 'Book Appointment', 'url' => $canonical],
                ]),
            ])),
        ];

        return $this->decoratePayload([
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => 'index, follow',
            'ogTitle' => $title,
            'ogDescription' => $description,
            'ogImage' => $ogImage,
            'ogType' => 'website',
            'ogUrl' => $canonical,
            'siteName' => $company->name,
            'twitterCard' => 'summary_large_image',
            'jsonLd' => $jsonLd,
            'googleSiteVerification' => $googleVerification,
            'skipAppTitleSuffix' => true,
        ]);
    }

    /**
     * Public storefront About / Terms (indexable, linked from the shop footer).
     *
     * @return array<string, mixed>
     */
    public function forStorefrontContentPage(Company $company, string $path, string $title, ?string $description = null): array
    {
        $path = trim($path, '/');
        $canonical = $this->storefrontBaseUrl($company).'/'.$path;
        $plain = $this->plainMetaDescription($description);
        $ogImage = $company->logo ? asset('storage/'.$company->logo) : $this->defaultOgImage();
        $theme = is_array($company->storefront_theme) ? $company->storefront_theme : [];
        $googleVerification = ! empty($theme['google_site_verification']) ? trim((string) $theme['google_site_verification']) : null;
        $shopUrl = $this->storefrontBaseUrl($company);
        $crumbLabel = $path === 'about' ? 'About' : 'Terms';
        $pageType = $path === 'about' ? 'AboutPage' : 'WebPage';

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@graph' => array_values(array_filter([
                [
                    '@type' => $pageType,
                    '@id' => $canonical.'#webpage',
                    'url' => $canonical,
                    'name' => $title,
                    'description' => $plain ?: null,
                ],
                $this->breadcrumbNode([
                    ['name' => $company->name, 'url' => $shopUrl],
                    ['name' => $crumbLabel, 'url' => $canonical],
                ]),
            ])),
        ];

        return $this->decoratePayload([
            'title' => $title,
            'description' => $plain ?: null,
            'h1' => $title,
            'lede' => $plain,
            'canonical' => $canonical,
            'robots' => 'index, follow',
            'ogTitle' => $title,
            'ogDescription' => $plain,
            'ogImage' => $ogImage,
            'ogType' => 'website',
            'ogUrl' => $canonical,
            'siteName' => $company->name,
            'twitterCard' => 'summary_large_image',
            'jsonLd' => $jsonLd,
            'googleSiteVerification' => $googleVerification,
            'skipAppTitleSuffix' => true,
        ]);
    }

    /**
     * Dedicated isolated sitemap for a custom domain tenant.
     *
     * @return list<array{loc: string, lastmod?: string, changefreq: string, priority: string, image?: array{loc: string, title?: string}}>
     */
    public function sitemapForTenantDomain(Company $company, string $domain): array
    {
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';
        $storeBase = $scheme.'://'.$domain;
        $entries = [];

        $entries[] = [
            'loc' => $storeBase,
            'lastmod' => optional($company->updated_at)?->toAtomString() ?? now()->toAtomString(),
            'changefreq' => 'daily',
            'priority' => '1.0',
        ];
        $this->appendStorefrontContentSitemapEntries($entries, $storeBase, optional($company->updated_at)?->toAtomString());

        if (Schema::hasTable('products')) {
            try {
                $products = Product::query()
                    ->where('company_id', $company->id)
                    ->where('status', 'active')
                    ->orderBy('id')
                    ->get(['id', 'slug', 'name', 'image', 'updated_at']);

                foreach ($products as $product) {
                    $path = $product->slug ?: (string) $product->id;
                    $imgUrl = $product->image_url;
                    $entry = [
                        'loc' => $storeBase.'/p/'.$path,
                        'lastmod' => optional($product->updated_at)?->toAtomString(),
                        'changefreq' => 'weekly',
                        'priority' => '0.8',
                    ];
                    if ($imgUrl) {
                        $entry['image'] = ['loc' => $imgUrl, 'title' => $product->name];
                    }
                    $entries[] = $entry;
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        return $entries;
    }

    /**
     * @return list<array{loc: string, lastmod?: string, changefreq: string, priority: string}>
     */
    public function sitemapEntries(?string $section = null): array
    {
        return match ($section) {
            'pages' => $this->sitemapMarketingEntries(),
            'blog' => $this->sitemapBlogEntries(),
            'stores' => $this->sitemapStoreEntries(),
            default => array_merge(
                $this->sitemapMarketingEntries(),
                $this->sitemapBlogEntries(),
                $this->sitemapStoreEntries(),
            ),
        };
    }

    /**
     * @return list<array{loc: string, lastmod?: string}>
     */
    public function sitemapIndexEntries(): array
    {
        $base = $this->appBaseUrl();
        $now = now()->toAtomString();

        return [
            ['loc' => $base.'/sitemap-pages.xml', 'lastmod' => $now],
            ['loc' => $base.'/sitemap-blog.xml', 'lastmod' => $now],
            ['loc' => $base.'/sitemap-stores.xml', 'lastmod' => $now],
        ];
    }

    public function shouldUseSitemapIndex(): bool
    {
        try {
            if (! Schema::hasTable('products')) {
                return false;
            }

            return Product::query()->where('status', 'active')->count() > 350;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return list<array{loc: string, lastmod?: string, changefreq: string, priority: string}>
     */
    private function sitemapMarketingEntries(): array
    {
        $base = $this->appBaseUrl();
        $seen = [];
        $entries = [];

        $add = function (string $path, string $changefreq = 'monthly', string $priority = '0.8') use ($base, &$seen, &$entries): void {
            $loc = $base.($path === '/' ? '/' : $path);
            if (isset($seen[$loc])) {
                return;
            }
            $seen[$loc] = true;
            $entries[] = [
                'loc' => $loc,
                'lastmod' => now()->toAtomString(),
                'changefreq' => $changefreq,
                'priority' => $priority,
            ];
        };

        $add('/', 'weekly', '1.0');
        foreach (['/solutions', '/pricing', '/about', '/contact', '/blog'] as $core) {
            if ($core === '/solutions' && ! PublicMarketingPages::enabled('solutions')) {
                continue;
            }
            if ($core === '/blog' && ! PublicMarketingPages::enabled('blog')) {
                continue;
            }
            $add($core, $core === '/blog' ? 'weekly' : 'monthly', $core === '/pricing' ? '0.9' : '0.8');
        }
        foreach (SeoLandingCatalog::all() as $landing) {
            $add($landing['path'], 'monthly', '0.85');
        }

        if (Schema::hasTable('cms_pages')) {
            try {
                $pages = CmsPage::query()
                    ->where('is_published', true)
                    ->where('slug', '!=', 'global')
                    ->orderBy('id')
                    ->get();

                foreach ($pages as $page) {
                    if ($page->slug === 'solutions' && ! PublicMarketingPages::enabled('solutions')) {
                        continue;
                    }
                    if ($page->slug === 'blog' && ! PublicMarketingPages::enabled('blog')) {
                        continue;
                    }
                    if ($this->cmsPageIsNoindex($page)) {
                        continue;
                    }
                    $path = $this->pathForSlug($page->slug);
                    $loc = $base.$path;
                    if (isset($seen[$loc])) {
                        continue;
                    }
                    $seen[$loc] = true;
                    $entries[] = [
                        'loc' => $loc,
                        'lastmod' => optional($page->updated_at)?->toAtomString(),
                        'changefreq' => $page->slug === 'home' ? 'weekly' : 'monthly',
                        'priority' => $page->slug === 'home' ? '1.0' : '0.8',
                    ];
                }
            } catch (\Throwable) {
                // fall through
            }
        }

        return $entries;
    }

    /**
     * @return list<array{loc: string, lastmod?: string, changefreq: string, priority: string}>
     */
    private function sitemapBlogEntries(): array
    {
        if (! PublicMarketingPages::enabled('blog')) {
            return [];
        }

        $base = $this->appBaseUrl();
        $entries = [[
            'loc' => $base.'/blog',
            'lastmod' => now()->toAtomString(),
            'changefreq' => 'weekly',
            'priority' => '0.7',
        ]];

        if (Schema::hasTable('blog_posts')) {
            try {
                $latest = BlogPost::published()->orderByDesc('updated_at')->value('updated_at');
                if ($latest) {
                    $entries[0]['lastmod'] = optional($latest)->toAtomString() ?? now()->toAtomString();
                }

                foreach (BlogPost::published()->orderByDesc('published_at')->get() as $post) {
                    $entries[] = [
                        'loc' => $base.'/blog/'.$post->slug,
                        'lastmod' => optional($post->updated_at)?->toAtomString(),
                        'changefreq' => 'monthly',
                        'priority' => '0.6',
                    ];
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        return $entries;
    }

    /**
     * @return list<array{loc: string, lastmod?: string, changefreq: string, priority: string}>
     */
    private function sitemapStoreEntries(): array
    {
        $base = $this->appBaseUrl();
        $entries = [];

        if (! Schema::hasTable('companies') || ! Schema::hasTable('products')) {
            return $entries;
        }

        try {
            $stores = Company::query()
                ->where('storefront_enabled', true)
                ->whereNotNull('store_slug')
                ->orderBy('id')
                ->get(['id', 'store_slug', 'custom_domain', 'custom_domain_verified_at', 'updated_at']);

            foreach ($stores as $store) {
                if ($store->custom_domain && $store->custom_domain_verified_at) {
                    $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';
                    $storeBase = $scheme.'://'.$store->custom_domain;
                    $entries[] = [
                        'loc' => $storeBase,
                        'lastmod' => optional($store->updated_at)?->toAtomString(),
                        'changefreq' => 'daily',
                        'priority' => '0.8',
                    ];
                    $this->appendStorefrontContentSitemapEntries($entries, $storeBase, optional($store->updated_at)?->toAtomString());
                    $productLocPrefix = $storeBase.'/p/';
                } else {
                    $storeBase = $base.'/s/'.$store->store_slug;
                    $entries[] = [
                        'loc' => $storeBase,
                        'lastmod' => optional($store->updated_at)?->toAtomString(),
                        'changefreq' => 'daily',
                        'priority' => '0.8',
                    ];
                    $this->appendStorefrontContentSitemapEntries($entries, $storeBase, optional($store->updated_at)?->toAtomString());
                    $productLocPrefix = $storeBase.'/p/';
                }

                $products = Product::query()
                    ->where('company_id', $store->id)
                    ->where('status', 'active')
                    ->orderBy('id')
                    ->limit(80)
                    ->get(['id', 'slug', 'name', 'image', 'updated_at']);

                foreach ($products as $product) {
                    $path = $product->slug ?: (string) $product->id;
                    $imgUrl = $product->image_url;
                    $entry = [
                        'loc' => $productLocPrefix.$path,
                        'lastmod' => optional($product->updated_at)?->toAtomString(),
                        'changefreq' => 'weekly',
                        'priority' => '0.6',
                    ];
                    if ($imgUrl) {
                        $entry['image'] = ['loc' => $imgUrl, 'title' => $product->name];
                    }
                    $entries[] = $entry;
                }
            }
        } catch (\Throwable) {
            // ignore
        }

        return $entries;
    }

    /**
     * @return array<string, mixed>
     */
    public function forBlogIndex(): array
    {
        $base = $this->appBaseUrl();
        $canonical = $base.'/blog';
        $siteName = (string) config('app.name', 'RelayIQ');
        $title = 'Blog — '.$siteName;
        $description = 'Guides and updates on WhatsApp commerce, AI sales, M-Pesa checkout, and growing with '.$siteName.'.';

        return $this->decoratePayload([
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => 'index, follow',
            'ogTitle' => $title,
            'ogDescription' => $description,
            'ogImage' => $this->defaultOgImage(),
            'ogType' => 'website',
            'ogUrl' => $canonical,
            'siteName' => $siteName,
            'twitterCard' => 'summary_large_image',
            'jsonLd' => [
                '@context' => 'https://schema.org',
                '@graph' => array_values(array_filter([
                    [
                        '@type' => 'Blog',
                        'name' => $title,
                        'url' => $canonical,
                        'description' => $description,
                        'publisher' => $this->organizationNode($base, $siteName, false),
                    ],
                    $this->breadcrumbNode([
                        ['name' => 'Home', 'url' => $base.'/'],
                        ['name' => 'Blog', 'url' => $canonical],
                    ]),
                ])),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function forBlogPost(string $slug): ?array
    {
        if (! Schema::hasTable('blog_posts')) {
            return null;
        }

        try {
            $post = BlogPost::published()->where('slug', $slug)->first();
        } catch (\Throwable) {
            return null;
        }

        if (! $post) {
            return null;
        }

        $base = $this->appBaseUrl();
        $canonical = $base.'/blog/'.$post->slug;
        $siteName = (string) config('app.name', 'RelayIQ');
        $title = trim((string) ($post->meta_title ?: $post->title));
        $description = trim((string) ($post->meta_description ?: $post->excerpt ?: ''));
        $ogImage = $post->absoluteImage($post->og_image ?: $post->cover_image) ?: $this->defaultOgImage();

        return $this->decoratePayload([
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => 'index, follow',
            'ogTitle' => $title,
            'ogDescription' => $description,
            'ogImage' => $ogImage,
            'ogType' => 'article',
            'ogUrl' => $canonical,
            'siteName' => $siteName,
            'twitterCard' => 'summary_large_image',
            'articlePublishedTime' => $post->published_at?->toAtomString(),
            'articleModifiedTime' => $post->updated_at?->toAtomString(),
            'jsonLd' => [
                '@context' => 'https://schema.org',
                '@graph' => array_values(array_filter([
                    [
                        '@type' => 'BlogPosting',
                        'headline' => $post->title,
                        'description' => $description ?: null,
                        'image' => $ogImage,
                        'datePublished' => $post->published_at?->toAtomString(),
                        'dateModified' => $post->updated_at?->toAtomString(),
                        'mainEntityOfPage' => $canonical,
                        'author' => [
                            '@type' => 'Organization',
                            'name' => $siteName,
                        ],
                        'publisher' => $this->organizationNode($base, $siteName, false),
                    ],
                    $this->breadcrumbNode([
                        ['name' => 'Home', 'url' => $base.'/'],
                        ['name' => 'Blog', 'url' => $base.'/blog'],
                        ['name' => $post->title, 'url' => $canonical],
                    ]),
                ])),
            ],
        ]);
    }

    public function faviconUrl(): ?string
    {
        $settings = PlatformSetting::query()->first();
        $path = $settings?->app_favicon;
        if (is_string($path) && $path !== '' && Storage::disk('public')->exists($path)) {
            return asset('storage/'.$path);
        }

        return asset('images/branding/relaysiq-favicon.png');
    }

    public function pathForSlug(string $slug): string
    {
        $fromCatalog = SeoLandingCatalog::path($slug);
        if (is_string($fromCatalog) && $fromCatalog !== '') {
            return $fromCatalog;
        }

        return match ($slug) {
            'home', 'global' => '/',
            default => '/'.$slug,
        };
    }

    /**
     * @param  list<array{loc: string, lastmod?: string, changefreq: string, priority: string, image?: array{loc: string, title?: string}}>  $entries
     */
    private function appendStorefrontContentSitemapEntries(array &$entries, string $storeBase, ?string $lastmod): void
    {
        $storeBase = rtrim($storeBase, '/');
        foreach ([
            ['path' => '/about', 'changefreq' => 'monthly', 'priority' => '0.5'],
            ['path' => '/terms', 'changefreq' => 'yearly', 'priority' => '0.3'],
        ] as $page) {
            $entries[] = [
                'loc' => $storeBase.$page['path'],
                'lastmod' => $lastmod,
                'changefreq' => $page['changefreq'],
                'priority' => $page['priority'],
            ];
        }
    }

    private function cmsPageIsNoindex(CmsPage $page): bool
    {
        return str_contains(strtolower((string) $page->robots), 'noindex');
    }

    private function plainMetaDescription(?string $text): string
    {
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags((string) $text)) ?: '');

        return Str::limit($plain, 160);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function decoratePayload(array $payload): array
    {
        $ogImage = $payload['ogImage'] ?? null;
        $payload['ogLocale'] = $payload['ogLocale'] ?? str_replace('-', '_', (string) config('app.locale', 'en'));
        $payload['twitterSite'] = $payload['twitterSite'] ?? $this->twitterSiteHandle();
        // Only advertise OG dimensions when the caller already set them (known asset).
        if (! isset($payload['ogImageWidth']) || ! isset($payload['ogImageHeight'])) {
            unset($payload['ogImageWidth'], $payload['ogImageHeight']);
        }
        if ((! is_string($ogImage) || $ogImage === '') && $this->defaultOgImage()) {
            $payload['ogImage'] = $this->defaultOgImage();
        }
        if (empty($payload['h1']) && ! empty($payload['title'])) {
            $payload['h1'] = $payload['title'];
        }
        if (empty($payload['lede']) && ! empty($payload['description'])) {
            $payload['lede'] = $payload['description'];
        }

        return $payload;
    }

    private function firstHeroTitle(CmsPage $page): string
    {
        $content = $this->firstHeroContent($page);

        return trim((string) ($content['title'] ?? $content['headline'] ?? ''));
    }

    private function firstHeroLede(CmsPage $page): string
    {
        $content = $this->firstHeroContent($page);

        return trim((string) ($content['description'] ?? $content['subhead'] ?? ''));
    }

    /**
     * @return array<string, mixed>
     */
    private function firstHeroContent(CmsPage $page): array
    {
        try {
            $page->loadMissing('sections');
        } catch (\Throwable) {
            return [];
        }

        $hero = $page->sections->first(
            fn ($s) => $s->section_key === 'hero' && $s->is_enabled
        );
        $content = $hero?->content;

        return is_array($content) ? $content : [];
    }

    private function twitterSiteHandle(): ?string
    {
        $handle = config('services.twitter.site');
        if (is_string($handle) && trim($handle) !== '') {
            $handle = trim($handle);

            return str_starts_with($handle, '@') ? $handle : '@'.$handle;
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fallback(string $slug): ?array
    {
        $catalog = SeoLandingCatalog::get($slug);
        $defaults = [
            'home' => [
                'title' => HomeSeoCopy::title(),
                'description' => HomeSeoCopy::description(),
            ],
            'pricing' => [
                'title' => 'WhatsApp AI Sales Automation Pricing — RelayIQ',
                'description' => 'RelayIQ pricing: free forever Starter (storefront, bookings, dine-in), Growth at KSh 2,000/month, and Custom via sales.',
            ],
            'about' => [
                'title' => 'About us — RelayIQ',
                'description' => 'Learn how RelayIQ helps businesses sell and support customers on WhatsApp.',
            ],
            'solutions' => [
                'title' => 'Solutions — RelayIQ',
                'description' => 'AI chats, catalog, payments, bookings, dine-in, storefront, and growth — one commerce OS for WhatsApp and the web.',
            ],
            'contact' => [
                'title' => 'Contact — RelayIQ',
                'description' => 'Get in touch with the RelayIQ team.',
            ],
            'privacy' => [
                'title' => 'Privacy Policy — RelayIQ',
                'description' => 'How RelayIQ collects, uses, and protects your data.',
            ],
            'terms' => [
                'title' => 'Terms of Service — RelayIQ',
                'description' => 'Terms governing use of the RelayIQ platform.',
            ],
        ];

        if ($catalog) {
            $meta = [
                'title' => $catalog['title'],
                'description' => $catalog['description'],
                'h1' => $catalog['h1'],
                'lede' => $catalog['lede'],
            ];
        } elseif (isset($defaults[$slug])) {
            $meta = $defaults[$slug];
            if ($slug === 'home') {
                $meta['h1'] = HomeSeoCopy::h1();
                $meta['lede'] = HomeSeoCopy::lede();
            }
        } else {
            return null;
        }

        $base = $this->appBaseUrl();
        $path = $this->pathForSlug($slug);
        $canonical = $base.$path;
        $siteName = (string) config('app.name', 'RelayIQ');

        return $this->decoratePayload([
            'title' => $meta['title'],
            'description' => $meta['description'],
            'h1' => $meta['h1'] ?? $meta['title'],
            'lede' => $meta['lede'] ?? $meta['description'],
            'canonical' => $canonical,
            'robots' => 'index, follow',
            'ogTitle' => $meta['title'],
            'ogDescription' => $meta['description'],
            'ogImage' => $this->defaultOgImage(),
            'ogType' => 'website',
            'ogUrl' => $canonical,
            'siteName' => $siteName,
            'twitterCard' => 'summary_large_image',
            'jsonLd' => $this->catalogFaqJsonLd($catalog),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $catalog
     * @return array<string, mixed>|null
     */
    private function catalogFaqJsonLd(?array $catalog): ?array
    {
        $faqs = is_array($catalog) && is_array($catalog['faqs'] ?? null) ? $catalog['faqs'] : [];
        if ($faqs === []) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_values(array_map(fn (array $faq) => [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['answer'],
                ],
            ], $faqs)),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function softwareApplicationNode(string $base, string $siteName, string $description): ?array
    {
        $offers = $this->planOffers($base);

        $node = [
            '@type' => 'SoftwareApplication',
            'name' => $siteName,
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web',
            'url' => $base,
            'description' => $description ?: null,
            'offers' => $offers,
        ];

        return $node;
    }

    /**
     * @return array<string, mixed>
     */
    private function organizationNode(string $base, string $siteName, bool $withId = true): array
    {
        $node = [
            '@type' => 'Organization',
            'name' => $siteName,
            'alternateName' => array_values(array_filter([
                $siteName !== 'RelayIQ' ? 'RelayIQ' : null,
                'Relay IQ',
                'RelayIQ.app',
            ])),
            'url' => $base,
            'logo' => $this->defaultOgImage(),
            'description' => 'RelayIQ is an AI sales agent for WhatsApp that automates WhatsApp sales, product recommendations, and in-chat payments.',
        ];

        if ($withId) {
            $node['@id'] = $base.'/#organization';
        }

        return $node;
    }

    /**
     * @return list<array<string, mixed>>|array<string, mixed>
     */
    private function planOffers(string $base): array
    {
        if (! Schema::hasTable('plans')) {
            return [[
                '@type' => 'Offer',
                'url' => $base.'/pricing',
                'price' => '0',
                'priceCurrency' => strtoupper((string) config('pricing.default_currency', 'KES')),
                'description' => 'Free trial available',
            ]];
        }

        try {
            $plans = Plan::query()->orderBy('sort_order')->orderBy('id')->get();
        } catch (\Throwable) {
            $plans = collect();
        }

        if ($plans->isEmpty()) {
            return [[
                '@type' => 'Offer',
                'url' => $base.'/pricing',
                'price' => '0',
                'priceCurrency' => strtoupper((string) config('pricing.default_currency', 'KES')),
                'description' => 'Free trial available',
            ]];
        }

        $currency = strtoupper((string) config('pricing.default_currency', 'KES'));

        return $plans->map(function (Plan $plan) use ($base, $currency) {
            $isCustom = ! $plan->is_free && (float) $plan->price_amount <= 0;
            $offer = [
                '@type' => 'Offer',
                'name' => $plan->name,
                'url' => $base.'/pricing',
                'description' => $plan->description ?: ($plan->is_free ? 'Free plan' : null),
                'priceCurrency' => $currency,
            ];
            if ($isCustom) {
                $offer['priceSpecification'] = [
                    '@type' => 'PriceSpecification',
                    'priceCurrency' => 'USD',
                    'description' => 'Custom pricing',
                ];
            } else {
                $offer['price'] = $plan->is_free ? '0' : (string) $plan->price_amount;
            }

            return $offer;
        })->values()->all();
    }

    /**
     * @param  list<array{name: string, url: string}>  $crumbs
     * @return array<string, mixed>|null
     */
    private function breadcrumbNode(array $crumbs): ?array
    {
        if ($crumbs === []) {
            return null;
        }

        $items = [];
        foreach (array_values($crumbs) as $index => $crumb) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url'],
            ];
        }

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function faqNode(CmsPage $page): ?array
    {
        if (! Schema::hasTable('landing_faqs')) {
            return null;
        }

        try {
            $page->loadMissing('sections');
        } catch (\Throwable) {
            return null;
        }

        $faqEnabled = $page->sections->contains(
            fn ($s) => $s->section_key === 'faq' && $s->is_enabled
        );

        if (! $faqEnabled) {
            return null;
        }

        try {
            $faqs = LandingFaq::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        } catch (\Throwable) {
            return null;
        }

        if ($faqs->isEmpty()) {
            return null;
        }

        return [
            '@type' => 'FAQPage',
            'mainEntity' => $faqs->map(fn ($f) => [
                '@type' => 'Question',
                'name' => $f->question,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => strip_tags((string) $f->answer),
                ],
            ])->values()->all(),
        ];
    }

    private function absoluteUrl(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $url = trim($url);
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        if (str_starts_with($url, '/')) {
            return $this->appBaseUrl().$url;
        }

        if (Storage::disk('public')->exists($url)) {
            return asset('storage/'.$url);
        }

        return $this->appBaseUrl().'/'.ltrim($url, '/');
    }

    private function defaultOgImage(): ?string
    {
        if (Schema::hasTable('platform_settings')) {
            try {
                $settings = PlatformSetting::query()->first();
                if ($settings && ! empty($settings->app_logo) && Storage::disk('public')->exists($settings->app_logo)) {
                    return asset('storage/'.$settings->app_logo);
                }
            } catch (\Throwable) {
                // fall through to bundled brand asset
            }
        }

        $bundled = public_path('images/branding/relaysiq-full-logo.png');
        if (is_file($bundled)) {
            return asset('images/branding/relaysiq-full-logo.png');
        }

        return asset('images/branding/relaysiq-app-icon.png');
    }

    private function appBaseUrl(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    private function storefrontBaseUrl(Company $company): string
    {
        if ($company->custom_domain && $company->custom_domain_verified_at) {
            $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';

            return $scheme.'://'.$company->custom_domain;
        }

        return $this->appBaseUrl().'/s/'.$company->store_slug;
    }
}
