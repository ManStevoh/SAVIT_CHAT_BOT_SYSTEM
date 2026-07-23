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
        $appLogo = asset('images/branding/relaysiq-mark.png');
        if ($settings && ! empty($settings->app_logo) && Storage::disk('public')->exists($settings->app_logo)) {
            $appLogo = asset('storage/' . $settings->app_logo);
        }

        return response()->json([
            'applicationName' => $appName,
            'appLogo' => $appLogo,
            'primaryColor' => $settings ? $settings->primary_color : null,
            'secondaryColor' => $settings ? $settings->secondary_color : null,
            'requireEmailVerification' => PlatformSetting::requiresEmailVerification(),
        ]);
    }
}
