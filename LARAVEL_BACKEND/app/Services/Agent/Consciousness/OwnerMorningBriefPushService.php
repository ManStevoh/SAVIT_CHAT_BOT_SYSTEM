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
