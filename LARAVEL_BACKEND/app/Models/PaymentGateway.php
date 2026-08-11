<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PaymentGateway extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'is_enabled',
        'config',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'config' => 'array',
    ];

    /**
     * Get merged config for a gateway (defaults + env + non-empty DB values). Cached per slug.
     * Empty DB strings do not wipe env credentials — that previously made “Active” gateways unusable.
     */
    public static function getConfig(string $slug): array
    {
        $cacheKey = "payment_gateway_config:{$slug}";

        return Cache::remember($cacheKey, 300, function () use ($slug) {
            $defaults = self::defaultConfig($slug);
            $fromEnv = self::configFromEnv($slug);
            $gateway = self::where('slug', $slug)->first();
            $fromDb = is_array($gateway?->config) ? $gateway->config : [];

            $merged = array_merge($defaults, $fromEnv);
            foreach ($fromDb as $key => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                $merged[$key] = $value;
            }

            return $merged;
        });
    }

    /**
     * Check if gateway is enabled (DB overrides env when set).
     */
    public static function isEnabled(string $slug): bool
    {
        $gateway = self::where('slug', $slug)->first();
        if (! $gateway) {
            return false;
        }

        return (bool) $gateway->is_enabled;
    }

    /**
     * Whether required credentials/details are present for this gateway to accept payments.
     *
     * @return array{ready: bool, missing: list<string>}
     */
    public static function readiness(string $slug): array
    {
        $cfg = self::getConfig($slug);
        $required = match ($slug) {
            'stripe' => ['secret'],
            'paystack' => ['secret_key'],
            'pesapal' => ['consumer_key', 'consumer_secret'],
            'flutterwave' => ['secret_key'],
            'mpesa' => ['shortcode', 'passkey'],
            'manual' => [], // bank_name OR account_number OR instructions
            default => [],
        };

        if ($slug === 'manual') {
            $ready = ! empty($cfg['instructions']) || ! empty($cfg['bank_name']) || ! empty($cfg['account_number']);
            $missing = $ready ? [] : ['bank_name or account_number or instructions'];

            return ['ready' => $ready, 'missing' => $missing];
        }

        $missing = [];
        foreach ($required as $key) {
            if (empty($cfg[$key])) {
                $missing[] = $key;
            }
        }

        return ['ready' => $missing === [], 'missing' => $missing];
    }

    public static function isReady(string $slug): bool
    {
        return self::isEnabled($slug) && self::readiness($slug)['ready'];
    }

    /**
     * Default config keys per gateway (structure only).
     */
    public static function defaultConfig(string $slug): array
    {
        return match ($slug) {
            'stripe' => [
                'key' => '',
                'secret' => '',
                'webhook_secret' => '',
                'trial_days' => 14,
                'currency' => 'kes',
                'env' => 'sandbox',
            ],
            'mpesa' => [
                'consumer_key' => '',
                'consumer_secret' => '',
                'shortcode' => '',
                'passkey' => '',
                'env' => 'sandbox',
                'callback_url' => '',
                'currency' => 'kes',
            ],
            'paystack' => [
                'public_key' => '',
                'secret_key' => '',
                'currency' => 'kes',
                'env' => 'sandbox',
                'callback_url' => '',
            ],
            'pesapal' => [
                'consumer_key' => '',
                'consumer_secret' => '',
                'env' => 'sandbox',
                'currency' => 'kes',
                'ipn_id' => '',
                'callback_url' => '',
            ],
            'flutterwave' => [
                'public_key' => '',
                'secret_key' => '',
                'secret_hash' => '',
                'currency' => 'kes',
                'env' => 'sandbox',
                'callback_url' => '',
            ],
            'manual' => [
                'bank_name' => '',
                'account_name' => '',
                'account_number' => '',
                'instructions' => '',
                'currency' => 'kes',
                'env' => 'sandbox',
            ],
            default => [],
        };
    }

    /**
     * Config from env (fallback when DB not used).
     */
    protected static function configFromEnv(string $slug): array
    {
        return match ($slug) {
            'stripe' => [
                'key' => env('STRIPE_KEY', ''),
                'secret' => env('STRIPE_SECRET', ''),
                'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),
                'trial_days' => (int) env('STRIPE_TRIAL_DAYS', 14),
                'currency' => env('STRIPE_CURRENCY', 'kes'),
                'env' => env('STRIPE_ENV', 'sandbox'),
            ],
            'mpesa' => [
                'consumer_key' => env('MPESA_CONSUMER_KEY', ''),
                'consumer_secret' => env('MPESA_CONSUMER_SECRET', ''),
                'shortcode' => env('MPESA_SHORTCODE', ''),
                'passkey' => env('MPESA_PASSKEY', ''),
                'env' => env('MPESA_ENV', 'sandbox'),
                'callback_url' => env('MPESA_CALLBACK_URL', ''),
                'currency' => env('MPESA_CURRENCY', 'kes'),
            ],
            'paystack' => [
                'public_key' => env('PAYSTACK_PUBLIC_KEY', ''),
                'secret_key' => env('PAYSTACK_SECRET_KEY', ''),
                'currency' => env('PAYSTACK_CURRENCY', 'kes'),
                'env' => env('PAYSTACK_ENV', 'sandbox'),
                'callback_url' => env('PAYSTACK_CALLBACK_URL', ''),
            ],
            'pesapal' => [
                'consumer_key' => env('PESAPAL_CONSUMER_KEY', ''),
                'consumer_secret' => env('PESAPAL_CONSUMER_SECRET', ''),
                'env' => env('PESAPAL_ENV', 'sandbox'),
                'currency' => env('PESAPAL_CURRENCY', 'kes'),
                'ipn_id' => env('PESAPAL_IPN_ID', ''),
                'callback_url' => env('PESAPAL_CALLBACK_URL', ''),
            ],
            'manual' => [
                'bank_name' => env('PLATFORM_BANK_NAME', ''),
                'account_name' => env('PLATFORM_BANK_ACCOUNT_NAME', ''),
                'account_number' => env('PLATFORM_BANK_ACCOUNT_NUMBER', ''),
                'instructions' => env('PLATFORM_BANK_INSTRUCTIONS', ''),
                'currency' => env('PLATFORM_BANK_CURRENCY', 'kes'),
                'env' => env('PLATFORM_BANK_ENV', 'sandbox'),
            ],
            default => [],
        };
    }

    /**
     * Clear config cache (call after update).
     */
    public static function clearConfigCache(string $slug): void
    {
        Cache::forget("payment_gateway_config:{$slug}");
    }
}
