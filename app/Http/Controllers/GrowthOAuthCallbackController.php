<?php

namespace App\Http\Controllers;

use App\Services\Growth\SocialOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GrowthOAuthCallbackController extends Controller
{
    public function callback(Request $request, SocialOAuthService $oauth): RedirectResponse
    {
        if ($request->filled('error')) {
            $platform = (string) $request->query('state_platform', 'unknown');

            return redirect()->away($oauth->frontendRedirectUrl(
                'error',
                $platform,
                (string) $request->query('error_description', $request->query('error'))
            ));
        }

        $code = (string) $request->query('code', '');
        $state = (string) $request->query('state', '');

        if ($code === '' || $state === '') {
            return redirect()->away($oauth->frontendRedirectUrl('error', 'unknown', 'Missing OAuth code or state.'));
        }

        try {
            $oauthState = \App\Models\GrowthOauthState::where('state_token', $state)->first();
            $platform = $oauthState?->platform ?? 'facebook';
            $result = $oauth->handleCallback($platform, $code, $state);
            $account = \App\Models\SocialAccount::find($result['accountId'] ?? 0);
            $redirectStatus = $account && $account->status === 'pending_page_selection'
                ? 'pending_pages'
                : 'success';

            return redirect()->away($oauth->frontendRedirectUrl($redirectStatus, $result['platform']));
        } catch (\Throwable $e) {
            Log::warning('Growth OAuth callback failed', ['error' => $e->getMessage()]);

            return redirect()->away($oauth->frontendRedirectUrl('error', 'unknown', $e->getMessage()));
        }
    }
}
