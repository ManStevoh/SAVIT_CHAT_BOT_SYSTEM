<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $sitemap = rtrim((string) config('app.url'), '/').'/sitemap.xml';

        $body = implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /dashboard',
            'Disallow: /admin',
            'Disallow: /api/',
            '',
            'Sitemap: '.$sitemap,
            '',
        ]);

        return response($body, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
