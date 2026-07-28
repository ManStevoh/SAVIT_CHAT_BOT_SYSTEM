<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Feature 12: when the request Host matches a verified companies.custom_domain,
 * redirect bare paths onto that company's public storefront (`/s/{slug}/...`).
 *
 * Uses redirects (not REQUEST_URI rewrites) so routing works correctly even when
 * this middleware runs inside the web stack after the router has matched.
 */
class ResolveStorefrontDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());
        $appHost = strtolower(parse_url((string) config('app.url'), PHP_URL_HOST) ?: '');

        if ($host === '' || $host === $appHost || in_array($host, ['localhost', '127.0.0.1'], true)) {
            return $next($request);
        }

        $company = Company::where('custom_domain', $host)
            ->whereNotNull('custom_domain_verified_at')
            ->where('storefront_enabled', true)
            ->whereNotNull('store_slug')
            ->first();

        if (! $company) {
            return $next($request);
        }

        $request->attributes->set('storefront_company_id', $company->id);

        $path = trim($request->path(), '/');

        // Leave platform / already-scoped storefront paths alone.
        if ($path !== '' && $this->shouldPassThrough($path)) {
            return $next($request);
        }

        $target = $path === ''
            ? '/s/'.$company->store_slug
            : '/s/'.$company->store_slug.'/'.$path;

        $query = $request->getQueryString();
        if ($query) {
            $target .= '?'.$query;
        }

        return redirect()->to($target);
    }

    protected function shouldPassThrough(string $path): bool
    {
        foreach ([
            's/', 'pay/', 'invoice/', 'b/', 'api/', 'build/', 'storage/', 'sanctum/',
            'dashboard', 'admin', 'login', 'register', 'blog', 'pricing', 'about', 'contact',
        ] as $prefix) {
            if ($path === rtrim($prefix, '/') || str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return $path === 'up';
    }
}
