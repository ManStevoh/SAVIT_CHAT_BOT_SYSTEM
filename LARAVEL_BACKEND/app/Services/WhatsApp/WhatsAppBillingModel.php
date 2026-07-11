<?php

namespace App\Services\WhatsApp;

/**
 * Meta WhatsApp billing models per Meta Solution Provider documentation.
 *
 * @see https://developers.facebook.com/docs/whatsapp/solution-providers/
 * @see https://developers.facebook.com/docs/whatsapp/embedded-signup/manage-accounts/share-and-revoke-credit-lines/
 */
final class WhatsAppBillingModel
{
    public const TECH_PROVIDER = 'tech_provider';

    public const SOLUTION_PARTNER = 'solution_partner';

    /** ISO-4217 currencies supported by Meta credit sharing API. */
    public const SUPPORTED_WABA_CURRENCIES = [
        'AUD', 'EUR', 'GBP', 'IDR', 'INR', 'USD',
    ];

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [self::TECH_PROVIDER, self::SOLUTION_PARTNER];
    }

    public static function isValid(?string $model): bool
    {
        return in_array($model, self::all(), true);
    }

    public static function normalize(?string $model): string
    {
        return self::isValid($model) ? $model : self::TECH_PROVIDER;
    }

    public static function label(string $model): string
    {
        return match (self::normalize($model)) {
            self::SOLUTION_PARTNER => 'Solution Partner (shared credit line)',
            default => 'Tech Provider (companies pay Meta directly)',
        };
    }
}
