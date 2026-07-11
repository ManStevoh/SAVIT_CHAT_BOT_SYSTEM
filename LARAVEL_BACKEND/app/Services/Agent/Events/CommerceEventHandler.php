<?php

namespace App\Services\Agent\Events;

use App\Models\Chat;
use App\Models\CommerceAgentEvent;
use App\Models\Message;
use App\Models\WhatsAppAccount;
use App\Services\Agent\AgentProactiveMessageService;
use App\Services\Platform\NotificationDispatcher;
use App\Services\WhatsAppMessageSenderService;
use Illuminate\Support\Facades\Log;

/**
 * Handles detected commerce events — proactive customer outreach + owner logging.
 */
final class CommerceEventHandler
{
    public function __construct(
        protected AgentProactiveMessageService $proactive,
        protected WhatsAppMessageSenderService $waSender,
        protected NotificationDispatcher $notifications,
    ) {}

    public function handleOpenEvents(int $companyId, int $maxPerRun = 15): int
    {
        $wa = WhatsAppAccount::where('company_id', $companyId)->where('status', 'active')->first();
        if (! $wa) {
            return 0;
        }

        $events = CommerceAgentEvent::query()
            ->where('company_id', $companyId)
            ->where('status', 'open')
            ->whereIn('event_type', config('agent.events.customer_outreach_types', [
                'delivery_delay', 'customer_birthday',
            ]))
            ->orderBy('id')
            ->limit($maxPerRun)
            ->get();

        $handled = 0;
        foreach ($events as $event) {
            if ($this->handleEvent($wa, $event)) {
                $handled++;
            }
        }

        return $handled;
    }

    public function handleOwnerAlerts(int $companyId, int $maxPerRun = 10): int
    {
        $alertTypes = config('agent.events.owner_alert_types', ['low_stock', 'sales_drop']);
        if ($alertTypes === []) {
            return 0;
        }

        $company = \App\Models\Company::find($companyId);
        if (! $company) {
            return 0;
        }

        $events = CommerceAgentEvent::query()
            ->where('company_id', $companyId)
            ->where('status', 'open')
            ->whereIn('event_type', $alertTypes)
            ->orderBy('id')
            ->limit($maxPerRun)
            ->get();

        $alerted = 0;
        foreach ($events as $event) {
            app(\App\Services\CompanyInAppNotificationService::class)->recordAgentEventAlert($company, $event);

            $templateKey = match ($event->event_type) {
                'low_stock' => 'alert.low_stock',
                'sales_drop' => 'alert.sales_drop',
                default => 'alert.commerce',
            };
            $payload = $event->payload ?? [];
            $this->notifications->dispatch($company, $templateKey, array_merge($payload, [
                'summary' => (string) ($payload['summary'] ?? $payload['message'] ?? $event->event_type),
                'owner_email' => $company->email,
            ]));

            $event->update(['status' => 'alerted', 'handled_at' => now()]);
            $alerted++;
        }

        return $alerted;
    }

    private function handleEvent(WhatsAppAccount $wa, CommerceAgentEvent $event): bool
    {
        $payload = $event->payload ?? [];
        $phone = (string) ($payload['customer_phone'] ?? '');
        if ($phone === '') {
            $event->update(['status' => 'skipped', 'handled_at' => now()]);

            return false;
        }

        $message = match ($event->event_type) {
            'delivery_delay' => $this->deliveryDelayMessage($payload),
            'customer_birthday' => $this->birthdayMessage($payload),
            default => null,
        };

        if ($message === null) {
            return false;
        }

        $chat = Chat::query()
            ->where('company_id', $event->company_id)
            ->where('customer_phone', $phone)
            ->orderByDesc('last_message_at')
            ->first();

        if (! $chat) {
            $event->update(['status' => 'no_chat', 'handled_at' => now()]);

            return false;
        }

        if ($chat->agent_handling_at !== null) {
            return false;
        }

        $result = $this->waSender->sendText($wa, $phone, $message);
        if (! $result['success']) {
            return false;
        }

        Message::create([
            'chat_id' => $chat->id,
            'content' => $message,
            'sender' => 'bot',
            'status' => 'sent',
            'whatsapp_message_id' => $result['message_id'] ?? null,
        ]);
        $chat->update([
            'last_message' => $message,
            'last_message_at' => now(),
            'ai_handled' => true,
        ]);

        $event->update(['status' => 'handled', 'handled_at' => now()]);
        Log::info('Commerce agent event handled', [
            'company_id' => $event->company_id,
            'event_type' => $event->event_type,
            'event_key' => $event->event_key,
        ]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function deliveryDelayMessage(array $payload): string
    {
        $orderNumber = (string) ($payload['order_number'] ?? 'your order');

        return "Hi! We wanted to update you on {$orderNumber}. It's taking a bit longer than expected. "
            ."We're tracking it closely — reply here if you need help.";
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function birthdayMessage(array $payload): string
    {
        return 'Happy birthday from our team! 🎂 We appreciate you — reply if you would like a special offer today.';
    }
}
