<?php

namespace App\Support;

/**
 * Formats monetary amounts using ISO 4217 codes (e.g. USD, KES, EGP) for chat and APIs.
 */
final class MoneyFormatter
{
    public static function normalizeCurrencyCode(?string $code): string
    {
        $raw = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $code) ?? '');
        if (strlen($raw) >= 3) {
            return substr($raw, 0, 3);
        }

        return 'USD';
    }

    public static function format(float $amount, ?string $currencyCode = null): string
    {
        $currency = self::normalizeCurrencyCode($currencyCode);
        if (class_exists(\NumberFormatter::class)) {
            try {
                $fmt = new \NumberFormatter('en', \NumberFormatter::CURRENCY);
                $out = $fmt->formatCurrency($amount, $currency);
                if ($out !== false) {
                    return $out;
                }
            } catch (\Throwable) {
                // fall through
            }
        }

        $decimals = in_array($currency, ['JPY', 'KRW', 'VND', 'CLP'], true) ? 0 : 2;

        return $currency.' '.number_format($amount, $decimals);
    }
}
