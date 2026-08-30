<?php

namespace App\Support;

/**
 * Official RelayIQ public social profiles (Knowledge Graph / footer).
 */
class BrandSocial
{
    /**
     * @return list<array{label: string, href: string}>
     */
    public static function links(): array
    {
        $social = config('branding.social', []);
        $out = [];

        foreach ([
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
        ] as $key => $label) {
            $href = is_string($social[$key] ?? null) ? trim((string) $social[$key]) : '';
            if ($href !== '') {
                $out[] = ['label' => $label, 'href' => $href];
            }
        }

        return $out;
    }

    /**
     * Stable profile URLs for schema.org sameAs and rel="me".
     *
     * @return list<string>
     */
    public static function urls(): array
    {
        return array_values(array_unique(array_map(
            static fn (array $link): string => rtrim($link['href'], '/'),
            self::links()
        )));
    }
}
