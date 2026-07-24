<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Services\MailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

/**
 * Public endpoint for app branding (name, logo, colors).
 * Used by frontend for theme, invoices, and email headers.
 */
class AppBrandingController extends Controller
{
    public function show(): JsonResponse
    {
        $settings = PlatformSetting::first();
        $appName = MailService::applicationName();
        $appLogo = asset('images/branding/relaysiq-favicon.png');
        if ($settings && ! empty($settings->app_logo) && Storage::disk('public')->exists($settings->app_logo)) {
            $appLogo = asset('storage/' . $settings->app_logo);
        }

        return response()->json([
            'applicationName' => $appName,
            'appLogo' => $appLogo,
            'primaryColor' => $settings ? $settings->primary_color : null,
            'secondaryColor' => $settings ? $settings->secondary_color : null,
            'requireEmailVerification' => PlatformSetting::requiresEmailVerification(),
            'cookieBannerEnabled' => (bool) ($settings?->cookie_banner_enabled ?? true),
            'cookieBannerText' => $settings?->cookie_banner_text,
            'cookiePolicyUrl' => $settings?->cookie_policy_url ?: '/privacy',
            'recaptchaEnabled' => app(\App\Services\RecaptchaService::class)->isEnabled(),
            'recaptchaSiteKey' => app(\App\Services\RecaptchaService::class)->siteKey(),
        ]);
    }
}
