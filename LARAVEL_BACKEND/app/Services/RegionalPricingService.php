<?php

namespace App\Services;

use App\Models\Plan;
use App\Support\MoneyFormatter;
use Illuminate\Http\Request;

/**
 * Resolve visitor currency (Cloudflare / override) and fixed regional plan prices.
 */
class RegionalPricingService
{
    public const SOURCE_QUERY = 'query';

    public const SOURCE_COOKIE = 'cookie';

    public const SOURCE_FORCED = 'forced';

    public const SOURCE_CLOUDFLARE = 'cloudflare';

    public const SOURCE_DEFAULT = 'default';

    /**
     * @return array{
     *   currency: string,
     *   country: ?string,
     *   source: string,
     *   label: string,
     *   symbol: string,
     *   available: list<array{code: string, label: string, symbol: string}>
     * }
     */
    public function resolveFromRequest(Request $request): array
    {
        $available = $this->availableCurrencies();
        $codes = array_column($available, 'code');

        $forcedCountry = $this->normalizeCountry(config('pricing.force_country'));
        $cfCountry = $this->normalizeCountry($request->header('CF-IPCountry'));
        $country = $forcedCountry ?: $cfCountry;

        $queryCurrency = $this->normalizeCurrency($request->query('currency'));
        if ($queryCurrency && in_array($queryCurrency, $codes, true)) {
            return $this->context($queryCurrency, $country, self::SOURCE_QUERY, $available);
        }

        $cookieName = (string) config('pricing.cookie', 'pricing_currency');
        $cookieCurrency = $this->normalizeCurrency($request->cookie($cookieName));
        if ($cookieCurrency && in_array($cookieCurrency, $codes, true)) {
            return $this->context($cookieCurrency, $country, self::SOURCE_COOKIE, $available);
        }

        if ($forcedCountry) {
            $currency = $this->currencyForCountry($forcedCountry);

            return $this->context($currency, $forcedCountry, self::SOURCE_FORCED, $available);
        }

        if ($cfCountry) {
            $currency = $this->currencyForCountry($cfCountry);

            return $this->context($currency, $cfCountry, self::SOURCE_CLOUDFLARE, $available);
        }

        $default = $this->normalizeCurrency(config('pricing.default_currency')) ?: 'USD';
        if (! in_array($default, $codes, true)) {
            $default = $codes[0] ?? 'USD';
        }

        return $this->context($default, $country, self::SOURCE_DEFAULT, $available);
    }

    public function currencyForCountry(?string $country): string
    {
        $country = $this->normalizeCountry($country);
        $map = config('pricing.country_currency', []);
        $default = $this->normalizeCurrency(config('pricing.default_currency')) ?: 'USD';

        if ($country && isset($map[$country])) {
            $mapped = $this->normalizeCurrency($map[$country]);
            if ($mapped) {
                return $mapped;
            }
        }

        return $default;
    }

    public function amountForPlan(Plan $plan, string $currency): ?float
    {
        $currency = $this->normalizeCurrency($currency) ?: 'USD';

        $overrides = is_array($plan->regional_prices) ? $plan->regional_prices : [];
        if (array_key_exists($currency, $overrides) && $overrides[$currency] !== null && $overrides[$currency] !== '') {
            return round((float) $overrides[$currency], 2);
        }

        $slug = (string) $plan->slug;
        $configured = config("pricing.plans.{$slug}.{$currency}");
        if ($configured !== null && $configured !== '') {
            return round((float) $configured, 2);
        }

        // Fall back to canonical price_amount for the default/base currency.
        $default = $this->normalizeCurrency(config('pricing.default_currency')) ?: 'USD';
        if ($currency === $default && $plan->price_amount !== null) {
            return round((float) $plan->price_amount, 2);
        }

        // Last resort: any known USD/base amount so checkout never silently undercharges.
        if ($plan->price_amount !== null && $currency === 'USD') {
            return round((float) $plan->price_amount, 2);
        }

        return null;
    }

    public function formatAmount(?float $amount, string $currency): string
    {
        $currency = $this->normalizeCurrency($currency) ?: 'USD';
        if ($amount === null) {
            return 'Custom';
        }

        $meta = config("pricing.currencies.{$currency}", []);
        $symbol = is_array($meta) ? ($meta['symbol'] ?? null) : null;
        $decimals = is_array($meta) && isset($meta['decimals']) ? (int) $meta['decimals'] : 2;

        if ($symbol) {
            $formatted = number_format($amount, $decimals, '.', ',');

            return $symbol === '$'
                ? $symbol.$formatted
                : $symbol.' '.$formatted;
        }

        return MoneyFormatter::format($amount, $currency);
    }

    public function displayForPlan(Plan $plan, string $currency): string
    {
        if ($plan->is_free) {
            return $plan->price_display ?: 'Free';
        }

        $amount = $this->amountForPlan($plan, $currency);
        if ($amount === null) {
            $display = trim((string) ($plan->price_display ?? ''));

            return $display !== '' ? $display : 'Custom';
        }

        return $this->formatAmount($amount, $currency);
    }

    /**
     * @return list<array{code: string, label: string, symbol: string}>
     */
    public function availableCurrencies(): array
    {
        $out = [];
        foreach (config('pricing.currencies', []) as $code => $meta) {
            $normalized = $this->normalizeCurrency((string) $code);
            if (! $normalized || ! is_array($meta)) {
                continue;
            }
            $out[] = [
                'code' => $normalized,
                'label' => (string) ($meta['label'] ?? $normalized),
                'symbol' => (string) ($meta['symbol'] ?? $normalized),
            ];
        }

        return $out;
    }

    public function normalizeCurrency(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }
        $raw = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $value) ?? '');
        if (strlen($raw) !== 3) {
            return null;
        }

        return $raw;
    }

    public function normalizeCountry(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $raw = strtoupper(preg_replace('/[^A-Za-z]/', '', $value) ?? '');
        if (strlen($raw) !== 2 || $raw === 'XX' || $raw === 'T1') {
            return null;
        }

        return $raw;
    }

    /**
     * @param  list<array{code: string, label: string, symbol: string}>  $available
     * @return array{
     *   currency: string,
     *   country: ?string,
     *   source: string,
     *   label: string,
     *   symbol: string,
     *   available: list<array{code: string, label: string, symbol: string}>
     * }
     */
    private function context(string $currency, ?string $country, string $source, array $available): array
    {
        $meta = config("pricing.currencies.{$currency}", []);

        return [
            'currency' => $currency,
            'country' => $country,
            'source' => $source,
            'label' => is_array($meta) ? (string) ($meta['label'] ?? $currency) : $currency,
            'symbol' => is_array($meta) ? (string) ($meta['symbol'] ?? $currency) : $currency,
            'available' => $available,
        ];
    }
}
