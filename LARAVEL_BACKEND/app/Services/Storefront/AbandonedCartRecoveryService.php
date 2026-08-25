<?php

namespace App\Services\Storefront;

use App\Models\Company;
use App\Models\Message;
use App\Models\StorefrontSession;
use App\Models\WhatsAppAccount;
use App\Services\WhatsAppMessageSenderService;
use Illuminate\Support\Facades\Log;

class AbandonedCartRecoveryService
{
    public function __construct(
        protected WhatsAppMessageSenderService $whatsapp,
        protected StorefrontWhatsAppBridgeService $bridge,
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

        $composed = $this->bridge->composeAbandonedCartMessage($company, $session);
        $text = $composed['text'];
        $chat = $this->bridge->resolveOrCreateChat($company, $phone, $session->customer_name);

        // Prefer an approved template when configured and there is no recent open chat window.
        $templateName = trim((string) ($company->settings?->abandoned_cart_template_name ?? ''));
        $hasOpenWindow = $chat && $chat->last_message_at && $chat->last_message_at->gt(now()->subHours(23));

        try {
            $result = null;
            if ($templateName !== '' && ! $hasOpenWindow) {
                $result = $this->whatsapp->sendTemplate(
                    $account,
                    $phone,
                    $templateName,
                    'en',
                    [
                        (string) ($session->customer_name ?: 'there'),
                        (string) $company->name,
                        (string) $composed['item_count'],
                    ]
                );
                // Fall back to plain text if template is missing/rejected.
                if (! ($result['success'] ?? false)) {
                    $result = $this->whatsapp->sendText($account, $phone, $text);
                }
            } else {
                $result = $this->whatsapp->sendText($account, $phone, $text);
            }

            if (! ($result['success'] ?? false)) {
                return false;
            }

            if ($chat) {
                Message::create([
                    'chat_id' => $chat->id,
                    'content' => $text,
                    'sender' => 'bot',
                    'status' => 'sent',
                    'whatsapp_message_id' => $result['message_id'] ?? null,
                ]);
                $chat->update([
                    'last_message' => $text,
                    'last_message_at' => now(),
                    'ai_handled' => true,
                ]);
            }

            return true;
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
