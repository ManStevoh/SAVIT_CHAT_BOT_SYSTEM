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
