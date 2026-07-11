<?php

namespace App\Services\Agent\Consciousness;

use App\Models\CommerceBrief;
use App\Models\Company;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Services\WhatsAppMessageSenderService;
use Illuminate\Support\Facades\Log;

/**
 * Phase 9 — push daily commerce brief to owner via WhatsApp.
 */
final class OwnerMorningBriefPushService
{
    public function __construct(
        protected WhatsAppMessageSenderService $waSender,
    ) {}

    public function pushForCompany(Company $company, ?CommerceBrief $brief = null): bool
    {
        $company->loadMissing('settings');
        $settings = $company->settings;

        if (! ($settings?->agent_commerce_enabled ?? false)) {
            return false;
        }

        if (! ($settings?->agent_morning_brief_whatsapp_enabled ?? false)) {
            return false;
        }

        $brief ??= CommerceBrief::query()
            ->where('company_id', $company->id)
            ->whereDate('brief_date', now()->toDateString())
            ->first();

        if (! $brief || $brief->pushed_to_owner_at !== null) {
            return false;
        }

        $phone = $this->resolveOwnerPhone($company);
        if ($phone === '') {
            return false;
        }

        $wa = WhatsAppAccount::where('company_id', $company->id)->where('status', 'active')->first();
        if (! $wa) {
            return false;
        }

        $message = $this->formatMessage($company, $brief);
        $result = $this->waSender->sendText($wa, $phone, $message);

        if (! ($result['success'] ?? false)) {
            Log::warning('Morning brief WhatsApp push failed', [
                'company_id' => $company->id,
                'brief_id' => $brief->id,
            ]);

            return false;
        }

        $brief->update(['pushed_to_owner_at' => now()]);

        return true;
    }

    public function resolveOwnerPhone(Company $company): string
    {
        $company->loadMissing('settings');
