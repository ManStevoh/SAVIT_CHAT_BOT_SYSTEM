<?php

namespace App\Services\Growth;

use App\Models\Chat;
use App\Models\Company;
use App\Models\Message;
use App\Models\Order;
use App\Models\WhatsAppAccount;
use App\Services\WhatsAppMessageSenderService;
use Illuminate\Support\Facades\Log;

class CrmFollowUpService
{
    public function __construct(
        protected WhatsAppMessageSenderService $waSender
    ) {}

    /**
     * Preview eligible CRM targets without sending messages.
     *
     * @return array{
     *     coldLeads: int,
     *     paymentRecovery: int,
     *     totalEligible: int,
     *     hoursQuiet: int,
     *     paymentRecoveryHours: int,
     *     maxFollowUps: int,
     *     whatsAppActive: bool
     * }
     */
    public function eligibleSummary(int $companyId): array
    {
        $hoursQuiet = (int) config('growth.crm.hours_quiet', 24);
        $maxFollowUps = (int) config('growth.crm.max_follow_ups', 2);
        $paymentRecoveryHours = (int) config('growth.crm.payment_recovery_hours', 48);

        $wa = WhatsAppAccount::where('company_id', $companyId)->where('status', 'active')->exists();

        $coldLeads = 0;
        if ($wa) {
            $cutoff = now()->subHours($hoursQuiet);
            $chats = Chat::where('company_id', $companyId)
                ->where(function ($q) {
                    $q->whereNotNull('social_post_id')->orWhereNotNull('attribution_link_id');
                })
                ->where('crm_follow_up_count', '<', $maxFollowUps)
                ->where(function ($q) {
                    $q->whereNull('crm_last_follow_up_at')
                        ->orWhere('crm_last_follow_up_at', '<', now()->subHours(48));
                })
                ->where('last_message_at', '<=', $cutoff)
                ->whereNull('agent_handling_at')
                ->get();

            foreach ($chats as $chat) {
                if ($this->hasOrder($chat) || ! $this->lastMessageFromCustomer($chat)) {
                    continue;
                }
                $coldLeads++;
            }
        }

        $paymentRecovery = 0;
        if ($wa) {
            $cutoff = now()->subHours($paymentRecoveryHours);
            $paymentRecovery = Order::where('company_id', $companyId)
                ->where('payment_status', 'pending')
                ->where('created_at', '<=', $cutoff)
                ->whereNotNull('chat_id')
                ->whereHas('chat', function ($q) use ($maxFollowUps) {
                    $q->where(function ($q2) {
                        $q2->whereNotNull('social_post_id')->orWhereNotNull('attribution_link_id');
                    })->where('crm_follow_up_count', '<', $maxFollowUps);
                })
                ->count();
        }

        return [
            'coldLeads' => $coldLeads,
            'paymentRecovery' => $paymentRecovery,
            'totalEligible' => $coldLeads + $paymentRecovery,
            'hoursQuiet' => $hoursQuiet,
            'paymentRecoveryHours' => $paymentRecoveryHours,
            'maxFollowUps' => $maxFollowUps,
            'whatsAppActive' => $wa,
        ];
    }

    /**
     * Cold leads: attributed chat, no order, customer went quiet, under follow-up cap.
     *
     * @return array{processed: int, sent: int, skipped: int}
     */
    public function processCompany(
        int $companyId,
        ?int $hoursQuiet = null,
        ?int $maxFollowUps = null
    ): array {
        $hoursQuiet = $hoursQuiet ?? (int) config('growth.crm.hours_quiet', 24);
        $maxFollowUps = $maxFollowUps ?? (int) config('growth.crm.max_follow_ups', 2);

        $result = $this->runForCompany($companyId, $hoursQuiet, $maxFollowUps);
        $paymentRecovery = $this->runPaymentRecovery($companyId);

        return [
            'processed' => $result['processed'] + $paymentRecovery['processed'],
            'sent' => $result['sent'] + $paymentRecovery['sent'],
            'skipped' => $result['skipped'] + $paymentRecovery['skipped'],
        ];
    }

    /**
     * Follow up on attributed leads with unpaid orders (abandoned cart recovery).
     *
     * @return array{processed: int, sent: int, skipped: int}
     */
    public function runPaymentRecovery(int $companyId): array
    {
        $wa = WhatsAppAccount::where('company_id', $companyId)->where('status', 'active')->first();
        if (! $wa) {
            return ['processed' => 0, 'sent' => 0, 'skipped' => 0];
        }

        $hours = (int) config('growth.crm.payment_recovery_hours', 48);
        $cutoff = now()->subHours($hours);

        $orders = Order::where('company_id', $companyId)
            ->where('payment_status', 'pending')
            ->where('created_at', '<=', $cutoff)
            ->whereNotNull('chat_id')
            ->whereHas('chat', function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNotNull('social_post_id')->orWhereNotNull('attribution_link_id');
                });
            })
            ->with('chat')
            ->limit(20)
            ->get();

        $sent = 0;
        $skipped = 0;

        foreach ($orders as $order) {
            $chat = $order->chat;
            if (! $chat || $chat->crm_follow_up_count >= (int) config('growth.crm.max_follow_ups', 2)) {
                $skipped++;

                continue;
            }

            $message = $this->buildPaymentRecoveryMessage($chat, $order);
            $result = $this->waSender->sendText($wa, $chat->customer_phone, $message);

            if ($result['success'] ?? false) {
                Message::create([
                    'chat_id' => $chat->id,
                    'content' => $message,
                    'message_type' => 'text',
                    'sender' => 'bot',
                    'status' => 'sent',
                    'whatsapp_message_id' => $result['message_id'] ?? null,
                ]);
                $chat->update([
                    'crm_last_follow_up_at' => now(),
                    'crm_follow_up_count' => $chat->crm_follow_up_count + 1,
                ]);
                $sent++;
            } else {
                $skipped++;
            }
        }

        return ['processed' => $orders->count(), 'sent' => $sent, 'skipped' => $skipped];
    }

    protected function runForCompany(int $companyId, int $hoursQuiet, int $maxFollowUps): array
    {
        $wa = WhatsAppAccount::where('company_id', $companyId)->where('status', 'active')->first();
        if (! $wa) {
            return ['processed' => 0, 'sent' => 0, 'skipped' => 0];
        }

        $cutoff = now()->subHours($hoursQuiet);
        $chats = Chat::where('company_id', $companyId)
            ->where(function ($q) {
                $q->whereNotNull('social_post_id')->orWhereNotNull('attribution_link_id');
            })
            ->where('crm_follow_up_count', '<', $maxFollowUps)
            ->where(function ($q) {
                $q->whereNull('crm_last_follow_up_at')
                    ->orWhere('crm_last_follow_up_at', '<', now()->subHours(48));
            })
            ->where('last_message_at', '<=', $cutoff)
            ->whereNull('agent_handling_at')
            ->get();

        $sent = 0;
        $skipped = 0;

        foreach ($chats as $chat) {
            if ($this->hasOrder($chat)) {
                $skipped++;

                continue;
            }

            if (! $this->lastMessageFromCustomer($chat)) {
                $skipped++;

                continue;
            }

            $message = $this->buildFollowUpMessage($chat);
            $result = $this->waSender->sendText($wa, $chat->customer_phone, $message);

            if ($result['success'] ?? false) {
                Message::create([
                    'chat_id' => $chat->id,
                    'content' => $message,
                    'message_type' => 'text',
                    'sender' => 'bot',
                    'status' => 'sent',
                    'whatsapp_message_id' => $result['message_id'] ?? null,
                ]);

                $chat->update([
                    'crm_last_follow_up_at' => now(),
                    'crm_follow_up_count' => $chat->crm_follow_up_count + 1,
                    'last_message' => $message,
                    'last_message_at' => now(),
                    'ai_handled' => true,
                ]);
                $sent++;
            } else {
                Log::warning('CRM follow-up send failed', [
                    'chat_id' => $chat->id,
                    'error' => $result['error'] ?? 'unknown',
                ]);
                $skipped++;
            }
        }

        return ['processed' => $chats->count(), 'sent' => $sent, 'skipped' => $skipped];
    }

    protected function hasOrder(Chat $chat): bool
    {
        return Order::where('chat_id', $chat->id)->exists();
    }

    protected function lastMessageFromCustomer(Chat $chat): bool
    {
        $last = Message::where('chat_id', $chat->id)->orderByDesc('id')->first();
        if ($last) {
            return $last->sender === 'customer';
        }

        // Attributed inbound chats may only have chat-level last_message before messages are synced.
        return filled($chat->last_message);
    }

    protected function buildFollowUpMessage(Chat $chat): string
    {
        $name = $chat->customer_name ?: 'there';
        $company = Company::find($chat->company_id);
        $industry = $company?->industry ?? 'default';
        $templates = config('growth.crm.industry_templates', []);
        $template = $templates[$industry] ?? $templates['default'] ?? "Hi {name}! Just checking in — reply anytime and we'll assist you.";

        return str_replace('{name}', $name, $template);
    }

    protected function buildPaymentRecoveryMessage(Chat $chat, Order $order): string
    {
        $name = $chat->customer_name ?: 'there';
        $template = config('growth.crm.payment_recovery_templates.default', 'Hi {name}! Complete payment for order #{order} anytime.');

        return str_replace(['{name}', '{order}'], [$name, $order->order_number], $template);
    }
}
