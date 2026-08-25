<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Services\PlanLimitService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Feature 12: when the request Host matches a verified companies.custom_domain,
 * serve that company's public storefront on-host (path rewrite) with matching root URL.
 *
 * Bare paths like `/` and `/cart` become `/s/{slug}` and `/s/{slug}/cart` internally
 * so existing routes keep working without a public 302 redirect.
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

        if (! $company || ! PlanLimitService::companyAllowsStorefront($company)) {
            return $next($request);
        }

        $request->attributes->set('storefront_company_id', $company->id);
        $request->attributes->set('storefront_custom_domain', $company->custom_domain);

        $path = trim($request->path(), '/');

        // Leave platform / already-scoped storefront paths alone.
        if ($path !== '' && $this->shouldPassThrough($path)) {
            URL::forceRootUrl($request->getSchemeAndHttpHost());

            return $next($request);
        }

        $targetPath = $path === ''
            ? '/s/'.$company->store_slug
            : '/s/'.$company->store_slug.'/'.$path;

        $query = $request->getQueryString();
        $targetUri = $query ? $targetPath.'?'.$query : $targetPath;

        $server = $request->server->all();
        $server['REQUEST_URI'] = $targetUri;
        $server['PATH_INFO'] = $targetPath;
        $server['QUERY_STRING'] = $query ?? '';

        $rewritten = $request->duplicate(
            $request->query->all(),
            $request->request->all(),
            $request->attributes->all(),
            $request->cookies->all(),
            $request->files->all(),
            $server
        );
        $rewritten->attributes->set('storefront_company_id', $company->id);
        $rewritten->attributes->set('storefront_custom_domain', $company->custom_domain);

        // Keep absolute URLs / canonicals on the merchant domain for this request.
        URL::forceRootUrl($request->getSchemeAndHttpHost());
        app()->instance('request', $rewritten);

        return $next($rewritten);
    }

    protected function shouldPassThrough(string $path): bool
    {
        foreach ([
            's/', 'pay/', 'invoice/', 'b/', 'api/', 'build/', 'storage/', 'sanctum/',
            'dashboard', 'admin', 'login', 'register', 'blog', 'pricing', 'about', 'contact',
            'solutions', 'sitemap.xml', 'sitemap-pages.xml', 'sitemap-blog.xml', 'sitemap-stores.xml',
            'robots.txt',
        ] as $prefix) {
            if ($path === rtrim($prefix, '/') || str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return $path === 'up';
    }
}
