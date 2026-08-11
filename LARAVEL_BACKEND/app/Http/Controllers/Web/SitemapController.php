<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Cms\CmsSeoService;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(CmsSeoService $seo): Response
    {
        if ($seo->shouldUseSitemapIndex()) {
            $xml = view('sitemap-index', ['entries' => $seo->sitemapIndexEntries()])->render();

            return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
        }

        $xml = view('sitemap', ['entries' => $seo->sitemapEntries()])->render();

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function section(string $section, CmsSeoService $seo): Response
    {
        if (! in_array($section, ['pages', 'blog', 'stores'], true)) {
            abort(404);
        }

        $xml = view('sitemap', ['entries' => $seo->sitemapEntries($section)])->render();

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
