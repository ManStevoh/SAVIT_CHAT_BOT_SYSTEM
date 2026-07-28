<?php

namespace App\Services\Storefront;

use App\Models\Company;
use App\Models\StorefrontSession;
use App\Models\WhatsAppAccount;
use App\Services\WhatsAppMessageSenderService;
use Illuminate\Support\Facades\Log;

class AbandonedCartRecoveryService
{
    public function __construct(
        protected WhatsAppMessageSenderService $whatsapp,
    ) {}

    /**
     * @return array{scanned: int, sent: int, skipped: int}
     */
    public function processDue(int $inactiveMinutes = 60): array
    {
        $cutoff = now()->subMinutes(max(15, $inactiveMinutes));
        $sent = 0;
        $skipped = 0;

        $sessions = StorefrontSession::query()
            ->whereNotNull('customer_phone')
            ->where('customer_phone', '!=', '')
            ->whereNull('abandoned_notified_at')
            ->where('last_activity_at', '<=', $cutoff)
            ->whereNotNull('cart')
            ->with('company.settings')
            ->limit(200)
            ->get();

        foreach ($sessions as $session) {
            $company = $session->company;
            if (! $company || ! $company->storefront_enabled || ! $company->store_slug) {
                $skipped++;

                continue;
            }

            $company->loadMissing('settings');
            if (! $company->settings?->abandoned_cart_recovery_enabled) {
                $skipped++;

                continue;
            }

            $cart = $session->cart ?? [];
            if ($cart === []) {
                $skipped++;

                continue;
            }

            if ($this->notify($company, $session)) {
                $session->update(['abandoned_notified_at' => now()]);
                $sent++;
            } else {
                $skipped++;
            }
        }

        return ['scanned' => $sessions->count(), 'sent' => $sent, 'skipped' => $skipped];
    }

    public function notify(Company $company, StorefrontSession $session): bool
    {
        $phone = preg_replace('/\D+/', '', (string) $session->customer_phone) ?? '';
        if ($phone === '') {
            return false;
        }

        $account = WhatsAppAccount::where('company_id', $company->id)->where('status', 'active')->first();
        if (! $account) {
            return false;
        }

        $cartUrl = rtrim((string) config('app.url'), '/').'/s/'.$company->store_slug.'/cart';
        $text = 'Hi'.($session->customer_name ? ' '.$session->customer_name : '').
            '! You left items in your cart at '.$company->name.
            '. Complete your order here: '.$cartUrl;

        try {
            $result = $this->whatsapp->sendText($account, $phone, $text);

            return (bool) ($result['success'] ?? false);
        } catch (\Throwable $e) {
            Log::warning('Abandoned cart WhatsApp notify failed', [
                'company_id' => $company->id,
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
