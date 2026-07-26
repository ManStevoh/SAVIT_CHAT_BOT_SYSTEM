<?php

namespace App\Support;

use App\Models\CompanySetting;

/**
 * Formats monetary amounts for chat, invoices, and APIs.
 * Uses ISO 4217 codes plus optional company display overrides (symbol, separators).
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

    /**
     * @param  array{symbol?: ?string, thousands?: ?string, decimal?: ?string}|null  $options
     */
    public static function format(float $amount, ?string $currencyCode = null, ?array $options = null): string
    {
        $currency = self::normalizeCurrencyCode($currencyCode);
        $symbol = isset($options['symbol']) && is_string($options['symbol']) && trim($options['symbol']) !== ''
            ? trim($options['symbol'])
            : null;
        $thousands = self::normalizeThousands($options['thousands'] ?? null);
        $decimal = self::normalizeDecimal($options['decimal'] ?? null, $thousands);
        $hasCustomDisplay = $symbol !== null
            || (array_key_exists('thousands', $options ?? []) && $thousands !== ',')
            || (array_key_exists('decimal', $options ?? []) && $decimal !== '.');

        if (! $hasCustomDisplay && class_exists(\NumberFormatter::class)) {
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
        $formatted = number_format(abs($amount), $decimals, $decimal, $thousands);
        if ($amount < 0) {
            $formatted = '-'.$formatted;
        }

        $prefix = $symbol ?? $currency;

        return $prefix.' '.$formatted;
    }

    public static function formatFromSettings(float $amount, ?CompanySetting $settings): string
    {
        if (! $settings) {
            return self::format($amount, null);
        }

        return self::format($amount, $settings->displayCurrencyCode(), [
            'symbol' => $settings->currency_symbol,
            'thousands' => $settings->thousands_separator,
            'decimal' => $settings->decimal_separator,
        ]);
    }

    /**
     * @return array{symbol: ?string, thousands: string, decimal: string}
     */
    public static function displayOptionsFromSettings(?CompanySetting $settings): array
    {
        $thousands = self::normalizeThousands($settings?->thousands_separator);
        $decimal = self::normalizeDecimal($settings?->decimal_separator, $thousands);

        return [
            'symbol' => is_string($settings?->currency_symbol) && trim($settings->currency_symbol) !== ''
                ? trim($settings->currency_symbol)
                : null,
            'thousands' => $thousands,
            'decimal' => $decimal,
        ];
    }

    public static function normalizeThousands(?string $separator): string
    {
        $s = (string) $separator;
        if (in_array($s, [',', '.', ' ', "'"], true)) {
            return $s;
        }

        return ',';
    }

    public static function normalizeDecimal(?string $separator, string $thousands): string
    {
        $s = (string) $separator;
        if (! in_array($s, [',', '.'], true)) {
            $s = $thousands === '.' ? ',' : '.';
        }
        if ($s === $thousands) {
            $s = $thousands === ',' ? '.' : ',';
        }

        return $s;
    }

    /**
     * Pair decimal separator when the company picks a thousands style.
     */
    public static function pairedDecimalForThousands(string $thousands): string
    {
        return $thousands === '.' ? ',' : '.';
    }
}
