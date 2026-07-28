<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Feature 12: when the request Host matches a verified companies.custom_domain,
 * rewrite the path so `/` (and bare paths) serve that company's public storefront.
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

        $path = trim($request->path(), '/');
        if ($path === '' || $path === '/') {
            $request->server->set('REQUEST_URI', '/s/'.$company->store_slug);
        } elseif (! str_starts_with($path, 's/') && ! str_starts_with($path, 'pay/') && ! str_starts_with($path, 'invoice/') && ! str_starts_with($path, 'b/')) {
            // Map bare storefront-relative paths onto /s/{slug}/...
            $request->server->set('REQUEST_URI', '/s/'.$company->store_slug.'/'.$path);
        }

        $request->attributes->set('storefront_company_id', $company->id);

        return $next($request);
    }
}
