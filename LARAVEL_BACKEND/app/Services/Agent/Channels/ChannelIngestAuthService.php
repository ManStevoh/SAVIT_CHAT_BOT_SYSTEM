<?php

namespace App\Services\Agent\Channels;

use App\Models\Company;
use App\Models\CompanySetting;
use Illuminate\Http\Request;

final class ChannelIngestAuthService
{
    public function companyFromRequest(Request $request, ?int $companyId = null): ?Company
    {
        $companyId ??= (int) $request->route('companyId');
        if ($companyId <= 0) {
            $companyId = (int) $request->input('companyId');
        }
        if ($companyId <= 0) {
            return null;
        }

        $secret = $this->extractSecret($request);
        if ($secret === null || $secret === '') {
            return null;
        }

        $settings = CompanySetting::where('company_id', $companyId)->first();
        if (! $settings || ! $settings->channel_ingest_secret) {
            return null;
        }

        if (! hash_equals((string) $settings->channel_ingest_secret, $secret)) {
            return null;
        }

        return Company::with('settings')->find($companyId);
    }

    public function companyFromWidgetToken(int $companyId, string $widgetToken): ?Company
    {
        $settings = CompanySetting::where('company_id', $companyId)->first();
        if (! $settings || ! $settings->web_widget_token) {
            return null;
