<?php

namespace App\Support;

class PublicMarketingPages
{
    public static function enabled(string $key): bool
    {
        return (bool) config("cms.public_pages.{$key}", true);
    }

    /**
     * @param  mixed  $links
     * @return list<array<string, mixed>>
     */
    public static function filterLinks(mixed $links): array
    {
        if (! is_array($links)) {
            return [];
        }

        $kept = [];
        foreach ($links as $link) {
            if (! is_array($link)) {
                continue;
            }
            $href = trim((string) ($link['href'] ?? ''));
            if (self::isHiddenHref($href)) {
                continue;
            }
            $kept[] = $link;
        }

        return $kept;
    }

    public static function isHiddenHref(string $href): bool
    {
        $path = rtrim((string) parse_url($href, PHP_URL_PATH), '/') ?: '/';

        if (! self::enabled('blog') && ($path === '/blog' || str_starts_with($path, '/blog/'))) {
            return true;
        }

        if (! self::enabled('solutions') && $path === '/solutions') {
            return true;
        }

        return false;
    }
}
