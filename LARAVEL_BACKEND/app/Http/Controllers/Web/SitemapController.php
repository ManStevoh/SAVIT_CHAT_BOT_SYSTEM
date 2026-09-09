<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Cms\CmsSeoService;
use App\Support\SeoLandingCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

class SitemapController extends Controller
{
    public function __invoke(Request $request, CmsSeoService $seo): Response
    {
        try {
            return $this->renderLive($request, $seo);
        } catch (Throwable $e) {
            report($e);

            return $this->fallback();
        }
    }

    public function section(string $section, CmsSeoService $seo): Response
    {
        if (! in_array($section, ['pages', 'blog', 'stores'], true)) {
            abort(404);
        }

        try {
            $xml = view('sitemap', ['entries' => $seo->sitemapEntries($section)])->render();

            return $this->xml($xml);
        } catch (Throwable $e) {
            report($e);

            return $section === 'pages' ? $this->fallback() : $this->xml(view('sitemap', ['entries' => []])->render());
        }
    }

    private function renderLive(Request $request, CmsSeoService $seo): Response
    {
        $host = strtolower(trim((string) $request->getHost()));
        if ($host !== '') {
            $company = Company::query()
                ->where('custom_domain', $host)
                ->whereNotNull('custom_domain_verified_at')
                ->first();

            if ($company) {
                $xml = view('sitemap', ['entries' => $seo->sitemapForTenantDomain($company, $host)])->render();

                return $this->xml($xml);
            }
        }

        if ($seo->shouldUseSitemapIndex()) {
            $xml = view('sitemap-index', ['entries' => $seo->sitemapIndexEntries()])->render();

            return $this->xml($xml);
        }

        $xml = view('sitemap', ['entries' => $seo->sitemapEntries()])->render();

        return $this->xml($xml);
    }

    private function fallback(): Response
    {
        $static = public_path('sitemap.xml');
        if (is_readable($static) && filesize($static) > 32) {
            return response((string) file_get_contents($static), 200)
                ->header('Content-Type', 'application/xml; charset=UTF-8');
        }

        $base = rtrim((string) config('app.url'), '/') ?: 'https://relayiq.app';
        $entries = [
            ['loc' => $base.'/', 'changefreq' => 'weekly', 'priority' => '1.0'],
            ['loc' => $base.'/pricing', 'changefreq' => 'monthly', 'priority' => '0.9'],
        ];
        if (\App\Support\PublicMarketingPages::enabled('solutions')) {
            $entries[] = ['loc' => $base.'/solutions', 'changefreq' => 'monthly', 'priority' => '0.8'];
        }
        if (\App\Support\PublicMarketingPages::enabled('blog')) {
            $entries[] = ['loc' => $base.'/blog', 'changefreq' => 'weekly', 'priority' => '0.7'];
        }
        foreach (SeoLandingCatalog::all() as $landing) {
            $entries[] = [
                'loc' => $base.$landing['path'],
                'changefreq' => 'monthly',
                'priority' => '0.85',
            ];
        }

        return $this->xml(view('sitemap', ['entries' => $entries])->render());
    }

    private function xml(string $xml): Response
    {
        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
