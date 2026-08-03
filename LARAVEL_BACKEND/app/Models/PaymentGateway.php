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
     * Get merged config for a gateway (DB + env fallback). Cached per slug.
     */
    public static function getConfig(string $slug): array
    {
        $cacheKey = "payment_gateway_config:{$slug}";

        return Cache::remember($cacheKey, 300, function () use ($slug) {
            $gateway = self::where('slug', $slug)->first();
            $defaults = self::defaultConfig($slug);
            if (! $gateway || ! $gateway->config) {
                return array_merge($defaults, self::configFromEnv($slug));
            }

            return array_merge($defaults, $gateway->config);
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
