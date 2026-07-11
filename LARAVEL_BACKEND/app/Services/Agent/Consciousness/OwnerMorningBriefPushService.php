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
        $override = trim((string) ($company->settings?->owner_whatsapp_phone ?? ''));
        if ($override !== '') {
            return $this->normalizePhone($override);
        }

        $ownerPhone = User::query()
            ->where('company_id', $company->id)
            ->where('role', 'company_owner')
            ->whereNotNull('phone')
            ->value('phone');

        if (is_string($ownerPhone) && trim($ownerPhone) !== '') {
            return $this->normalizePhone($ownerPhone);
        }

        $companyPhone = trim((string) ($company->phone ?? ''));

        return $companyPhone !== '' ? $this->normalizePhone($companyPhone) : '';
    }

    private function formatMessage(Company $company, CommerceBrief $brief): string
    {
        $lines = [
            '🌅 Morning brief — '.$company->name,
            '',
            (string) $brief->summary,
        ];

        $recs = is_array($brief->recommendations) ? array_slice($brief->recommendations, 0, 3) : [];
        if ($recs !== []) {
            $lines[] = '';
            $lines[] = 'Top actions:';
            foreach ($recs as $i => $rec) {
                $lines[] = ($i + 1).'. '.(string) $rec;
            }
        }

        $lines[] = '';
        $lines[] = 'Open Mission Control in your dashboard for details.';

        return mb_substr(implode("\n", $lines), 0, (int) config('agent.max_output_chars', 1200));
    }
