<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $host = strtolower(trim((string) $request->getHost()));
        if ($host !== '') {
            $company = Company::query()
                ->where('custom_domain', $host)
                ->whereNotNull('custom_domain_verified_at')
                ->first();

            if ($company) {
                $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';
                $sitemap = "{$scheme}://{$host}/sitemap.xml";

                $body = implode("\n", [
                    'User-agent: *',
                    'Allow: /',
                    'Disallow: /*/cart',
                    'Disallow: /*/checkout',
                    'Disallow: /*/wishlist',
                    'Disallow: /*/track',
                    'Disallow: /*/order/',
                    'Disallow: /pay/',
                    'Disallow: /invoice/',
                    '',
                    'Sitemap: '.$sitemap,
                    '',
                ]);

                return response($body, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
            }
        }

        $sitemap = rtrim((string) config('app.url'), '/').'/sitemap.xml';

        $body = implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /dashboard',
            'Disallow: /admin',
            'Disallow: /api/',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /forgot-password',
            'Disallow: /reset-password',
            'Disallow: /pay/',
            'Disallow: /invoice/',
            'Disallow: /order-paid',
            'Disallow: /*/cart',
            'Disallow: /*/checkout',
            'Disallow: /*/wishlist',
            'Disallow: /*/track',
            'Disallow: /*/order/',
            '',
            'Sitemap: '.$sitemap,
            '',
        ]);

        return response($body, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
