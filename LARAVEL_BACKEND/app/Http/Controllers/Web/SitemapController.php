<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Cms\CmsSeoService;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(CmsSeoService $seo): Response
    {
        $entries = $seo->sitemapEntries();

        $xml = view('sitemap', ['entries' => $entries])->render();

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
