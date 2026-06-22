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
                'currency' => 'usd',
            ],
            'mpesa' => [
                'consumer_key' => '',
                'consumer_secret' => '',
                'shortcode' => '',
                'passkey' => '',
                'env' => 'sandbox',
                'callback_url' => '',
            ],
            'paystack' => [
                'public_key' => '',
                'secret_key' => '',
                'currency' => 'ngn',
                'callback_url' => '',
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
                'currency' => env('STRIPE_CURRENCY', 'usd'),
            ],
            'mpesa' => [
                'consumer_key' => env('MPESA_CONSUMER_KEY', ''),
                'consumer_secret' => env('MPESA_CONSUMER_SECRET', ''),
                'shortcode' => env('MPESA_SHORTCODE', ''),
                'passkey' => env('MPESA_PASSKEY', ''),
                'env' => env('MPESA_ENV', 'sandbox'),
                'callback_url' => env('MPESA_CALLBACK_URL', ''),
            ],
            'paystack' => [
                'public_key' => env('PAYSTACK_PUBLIC_KEY', ''),
                'secret_key' => env('PAYSTACK_SECRET_KEY', ''),
                'currency' => env('PAYSTACK_CURRENCY', 'ngn'),
                'callback_url' => env('PAYSTACK_CALLBACK_URL', ''),
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
