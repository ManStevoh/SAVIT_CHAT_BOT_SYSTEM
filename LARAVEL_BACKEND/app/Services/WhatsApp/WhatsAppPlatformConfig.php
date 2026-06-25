<?php

namespace App\Services\WhatsApp;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;

class WhatsAppPlatformConfig
{
    public const GRAPH_VERSION = 'v22.0';

    public const CACHE_KEY = 'platform_settings_whatsapp';

    public static function settings(): ?PlatformSetting
    {
        return Cache::remember(self::CACHE_KEY, 300, fn () => PlatformSetting::first());
    }

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function graphUrl(): string
    {
        return 'https://graph.facebook.com/' . self::GRAPH_VERSION;
    }

    public static function webhookVerifyToken(): string
    {
        $settings = self::settings();

        return (string) ($settings?->whatsapp_webhook_verify_token
            ?? config('whatsapp.webhook_verify_token', ''));
    }

    public static function metaAppSecret(): string
    {
        $settings = self::settings();

        return (string) ($settings?->meta_app_secret
            ?? config('whatsapp.app_secret', ''));
    }

    public static function embeddedAppId(): string
    {
        $settings = self::settings();

        return (string) ($settings?->whatsapp_embedded_app_id
            ?? config('whatsapp.embedded_signup_app_id', ''));
    }

    public static function embeddedConfigId(): string
    {
        $settings = self::settings();

        return (string) ($settings?->whatsapp_embedded_config_id
            ?? config('whatsapp.embedded_signup_config_id', ''));
    }

    public static function embeddedAppSecret(): string
    {
        $settings = self::settings();

        return (string) ($settings?->whatsapp_embedded_app_secret
            ?? config('whatsapp.embedded_signup_app_secret', ''));
    }

    public static function embeddedRedirectUri(): string
    {
        $settings = self::settings();
        $uri = (string) ($settings?->whatsapp_embedded_redirect_uri
            ?? config('whatsapp.embedded_signup_redirect_uri', ''));

        if ($uri !== '') {
            return $uri;
        }

        return rtrim((string) config('app.url'), '/') . '/dashboard/settings';
    }

    public static function enableCoexist(): bool
    {
        $settings = self::settings();

        return (bool) ($settings?->whatsapp_enable_coexist ?? false);
    }

    public static function manualConnectEnabled(): bool
    {
        $settings = self::settings();

        return (bool) ($settings?->whatsapp_manual_connect_enabled ?? true);
    }

    public static function isManualConnectEnabled(): bool
    {
        return self::manualConnectEnabled();
    }

    public static function embeddedSignupEnabled(): bool
    {
        $settings = self::settings();

        return (bool) ($settings?->whatsapp_embedded_signup_enabled ?? true);
    }

    public static function hasEmbeddedSignupCredentials(): bool
    {
        return self::embeddedAppId() !== ''
            && self::embeddedConfigId() !== ''
            && self::embeddedAppSecret() !== '';
    }

    public static function isEmbeddedSignupEnabled(): bool
    {
        return self::embeddedSignupEnabled() && self::hasEmbeddedSignupCredentials();
    }

    public static function webhookCallbackUrl(): string
    {
        return rtrim((string) config('app.url'), '/') . '/api/whatsapp/webhook';
    }
}
