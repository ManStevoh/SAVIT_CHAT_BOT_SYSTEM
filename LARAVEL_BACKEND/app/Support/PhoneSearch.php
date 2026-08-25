<?php

namespace App\Support;

final class PhoneSearch
{
    /**
     * Build LIKE patterns so local formats (07…) also match stored international phones (254…).
     *
     * @return list<string>
     */
    public static function likePatterns(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $patterns = ["%{$raw}%"];
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return array_values(array_unique($patterns));
        }

        $patterns[] = "%{$digits}%";

        if (str_starts_with($digits, '0') && strlen($digits) >= 9) {
            $patterns[] = '%254'.substr($digits, 1).'%';
            $patterns[] = '%'.substr($digits, 1).'%';
        }

        if (str_starts_with($digits, '254') && strlen($digits) >= 12) {
            $patterns[] = '%0'.substr($digits, 3).'%';
            $patterns[] = '%'.substr($digits, 3).'%';
        }

        if (strlen($digits) >= 9) {
            $patterns[] = '%'.substr($digits, -9).'%';
        }

        return array_values(array_unique($patterns));
    }
}
