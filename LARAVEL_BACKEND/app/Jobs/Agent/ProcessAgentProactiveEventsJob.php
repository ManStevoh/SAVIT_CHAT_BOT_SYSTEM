<?php

namespace App\Jobs\Agent;

use App\Models\Chat;
use App\Models\Company;
use App\Models\Message;
use App\Models\Order;
use App\Models\WhatsAppAccount;
use App\Services\Agent\AgentProactiveMessageService;
use App\Services\Agent\Company\CustomerIntentChainService;
use App\Services\Agent\Events\CommerceEventDetector;
use App\Services\Agent\Events\CommerceEventHandler;
use App\Services\WhatsAppMessageSenderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\Agent\Platform\CommerceExperimentService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Event brain: abandoned carts, predictive reorder outreach.
 */
class ProcessAgentProactiveEventsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ?int $companyId = null) {}

    public function handle(
        AgentProactiveMessageService $proactive,
        WhatsAppMessageSenderService $waSender,
        CustomerIntentChainService $intentChains,
        CommerceEventDetector $eventDetector,
        CommerceEventHandler $eventHandler,
    ): void {
        $query = Company::query()
            ->where('status', 'active')
            ->whereHas('settings', fn ($q) => $q
                ->where('agent_commerce_enabled', true)
                ->where('agent_proactive_enabled', true))
            ->whereHas('whatsappAccount', fn ($q) => $q->where('status', 'active'));

        if ($this->companyId) {
            $query->where('id', $this->companyId);
        }

        foreach ($query->pluck('id') as $companyId) {
            $company = Company::with('settings')->find($companyId);
            if (! $company) {
                continue;
            }
            if (config('agent.events.detection_enabled', true)) {
                $eventDetector->detectForCompany($company);
            }
            $this->processAbandonedCarts((int) $companyId, $proactive, $waSender);
            $this->processReorderPredictions((int) $companyId, $waSender, $intentChains);
            $eventHandler->handleOpenEvents((int) $companyId, (int) config('agent.proactive.max_outreach_per_run', 15));
            $eventHandler->handleOwnerAlerts((int) $companyId, 10);
        }
    }

    private function processAbandonedCarts(
        int $companyId,
        AgentProactiveMessageService $proactive,
        WhatsAppMessageSenderService $waSender,
        CommerceExperimentService $experiments,
    ): void {
        $hours = (int) config('agent.proactive.abandoned_cart_hours', 24);
        $maxPerRun = (int) config('agent.proactive.max_outreach_per_run', 15);
        $cutoff = now()->subHours($hours);

        $wa = WhatsAppAccount::where('company_id', $companyId)->where('status', 'active')->first();
        if (! $wa) {
            return;
        }

        $orders = Order::query()
            ->where('company_id', $companyId)
            ->where('payment_status', 'pending')
            ->where('status', '!=', 'cancelled')
            ->where('created_at', '<=', $cutoff)
            ->whereNotNull('chat_id')
            ->whereNotNull('customer_phone')
            ->whereNull('agent_proactive_follow_up_at')
            ->with(['company.settings', 'chat'])
            ->orderBy('created_at')
            ->limit($maxPerRun)
            ->get();

        foreach ($orders as $order) {
            $chat = $order->chat;
            if (! $chat || $chat->agent_handling_at !== null) {
                continue;
            }

            $fallback = $proactive->abandonedCartMessage($order);
            if ($fallback === null) {
                continue;
            }

            $experiment = $experiments->activePromotionExperiment($companyId);
            $variant = $experiment ? $experiments->assignVariant($experiment) : null;
            $message = $experiments->messageForVariant($variant, $fallback);

            if ($this->sendBotMessage($wa, $waSender, $order->customer_phone, $chat, $message)) {
                if ($experiment && $variant) {
                    Cache::put("exp_assign:order:{$order->id}", [
                        'experiment_id' => $experiment->id,
                        'variant_id' => $variant->id,
                    ], now()->addDays(14));
                }
                $order->update(['agent_proactive_follow_up_at' => now()]);
                Log::info('Agent proactive abandoned cart outreach sent', [
                    'company_id' => $companyId,
                    'order_id' => $order->id,
                ]);
            }
        }
    }

    private function processReorderPredictions(
        int $companyId,
        WhatsAppMessageSenderService $waSender,
        CustomerIntentChainService $intentChains,
    ): void {
        $wa = WhatsAppAccount::where('company_id', $companyId)->where('status', 'active')->first();
        if (! $wa) {
            return;
        }

        $phones = Order::query()
            ->where('company_id', $companyId)
            ->where('payment_status', 'paid')
            ->whereNotNull('customer_phone')
            ->distinct()
            ->limit(20)
            ->pluck('customer_phone');

        foreach ($phones as $phone) {
            $signal = $intentChains->reorderSignal($companyId, (string) $phone);
            if ($signal === null || ! ($signal['due'] ?? false)) {
                continue;
            }

            $chat = Chat::query()
                ->where('company_id', $companyId)
                ->where('customer_phone', $phone)
                ->orderByDesc('last_message_at')
                ->first();
            if (! $chat) {
                continue;
            }

            $message = "Hi! Based on your usual ordering pattern, you may be running low on supplies. "
                .'Would you like me to prepare your usual order? Just reply here and I will help.';

            if ($this->sendBotMessage($wa, $waSender, (string) $phone, $chat, $message)) {
                Log::info('Agent reorder prediction outreach sent', [
                    'company_id' => $companyId,
                    'phone' => $phone,
                ]);
            }
        }
    }

    private function sendBotMessage(
        WhatsAppAccount $wa,
        WhatsAppMessageSenderService $waSender,
        string $to,
        Chat $chat,
        string $message,
    ): bool {
        $result = $waSender->sendText($wa, $to, $message);
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

        return true;
    }
}
