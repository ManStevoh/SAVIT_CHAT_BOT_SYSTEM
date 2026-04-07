<?php

namespace App\Jobs;

use App\Models\Chat;
use App\Models\Company;
use App\Models\Message;
use App\Models\Subscription;
use App\Services\AIReplyService;
use App\Services\CompanyInAppNotificationService;
use App\Services\MailService;
use App\Services\OrderFlowService;
use App\Services\PlanLimitService;
use App\Services\WhatsAppMessageSenderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessIncomingWhatsAppMessage implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    /**
     * Unique key so only one job runs per incoming message (avoids duplicate replies when Meta retries the webhook).
     */
    public function uniqueId(): string
    {
        if ($this->whatsappMessageId) {
            return "wa_incoming:{$this->chatId}:{$this->whatsappMessageId}";
        }

        return "wa_incoming:{$this->chatId}:".md5($this->messageText.':'.$this->customerPhone);
    }

    public function __construct(
        public int $companyId,
        public int $chatId,
        public string $customerPhone,
        public string $phoneNumberId,
        public string $messageText,
        public ?string $customerName = null,
        public ?string $whatsappMessageId = null
    ) {}

    public function handle(AIReplyService $aiReply, WhatsAppMessageSenderService $waSender, MailService $mailService): void
    {
        $company = Company::find($this->companyId);
        if (! $company) {
            Log::warning('ProcessIncomingWhatsAppMessage: company not found', ['company_id' => $this->companyId]);

            return;
        }

        $chat = Chat::find($this->chatId);
        if (! $chat) {
            return;
        }

        if ($chat->isAgentHandling(30)) {
            $this->notifyCompanyNewMessage($company, $mailService, 'agent_active');

            return;
        }

        if ($this->wantsHumanEscalation($chat)) {
            $this->notifyCompanyNewMessage($company, $mailService, 'handoff');
            app(OrderFlowService::class)->resetOrderState($chat);
            $chat->refresh();
            $chat->update(['agent_handling_at' => now()]);
            $account = $company->whatsappAccount;
            if ($account && $account->isActive()) {
                $this->sendReplyAndSave($waSender, $company, $chat, $this->humanHandoffCustomerMessage());
            } else {
                Log::warning('ProcessIncomingWhatsAppMessage: human escalation but no active WhatsApp account', ['company_id' => $this->companyId]);
            }

            return;
        }

        if (! $this->companyHasActiveSubscription($company)) {
            $replyText = 'Our service is temporarily unavailable. Please try again later or contact support.';
            $this->sendReplyAndSave($waSender, $company, $chat, $replyText);

            return;
        }

        if (! PlanLimitService::isWithinMessageLimit($company)) {
            Log::info('ProcessIncomingWhatsAppMessage: message limit reached, skipping auto-reply', ['company_id' => $company->id]);
            $this->notifyCompanyNewMessage($company, $mailService, 'message');

            return;
        }

        $settings = $company->settings;
        if (! $settings || ! $settings->auto_reply_enabled) {
            $this->notifyCompanyNewMessage($company, $mailService, 'message');

            return;
        }

        $account = $company->whatsappAccount;
        if (! $account || ! $account->isActive()) {
            Log::warning('ProcessIncomingWhatsAppMessage: no active WhatsApp account', ['company_id' => $this->companyId]);

            return;
        }

        if ($this->alreadyRepliedToThisMessage()) {
            return;
        }

        if ($this->isFirstCustomerMessageInChat($this->chatId)) {
            app(OrderFlowService::class)->resetOrderState($chat);
            $chat->refresh();
            $greeting = $aiReply->getGreetingOpening($company, $this->customerName);
            $this->sendReplyAndSave($waSender, $company, $chat, $greeting);

            $chat->refresh();
            $orderFlow = app(OrderFlowService::class);
            $stepBefore = $chat->conversation_step;
            $orderReply = $orderFlow->processMessage($chat, $company, $this->messageText, $this->customerName ?? '', $this->customerPhone);
            if ($orderReply !== null && trim($orderReply) !== '') {
                $chat->refresh();
                $this->maybeSendOrderSelectionImage($waSender, $company, $chat, $orderFlow, $stepBefore);
                $this->sendReplyAndSave($waSender, $company, $chat, $orderReply);

                return;
            }

            $followUp = $aiReply->getReplyAfterOpeningGreeting($company, $this->messageText, $this->customerName, $this->chatId, $this->orderFlowContextForAi($chat));
            if ($followUp !== null && trim($followUp) !== '') {
                $this->sendReplyAndSave($waSender, $company, $chat, $followUp);
            }

            return;
        }

        $orderFlow = app(OrderFlowService::class);
        $stepBefore = $chat->conversation_step;
        $orderReply = $orderFlow->processMessage($chat, $company, $this->messageText, $this->customerName ?? '', $this->customerPhone);
        if ($orderReply !== null) {
            $chat->refresh();
            $this->maybeSendOrderSelectionImage($waSender, $company, $chat, $orderFlow, $stepBefore);
            $this->sendReplyAndSave($waSender, $company, $chat, $orderReply);

            return;
        }

        $replyText = $aiReply->getReplyForMessage($company, $this->messageText, $this->customerName, $this->chatId, $this->orderFlowContextForAi($chat));
        $this->sendReplyAndSave($waSender, $company, $chat, $replyText);
    }

    /**
     * Quick menu "3. Talk to agent" (only when not in an order step where "3" means e.g. product or payment option).
     */
    protected function wantsHumanEscalation(Chat $chat): bool
    {
        $lower = mb_strtolower(trim($this->messageText));
        if ($lower === '3') {
            return ! filled($chat->conversation_step);
        }
        $keywords = ['agent', 'human', 'representative', 'talk to someone', 'real person', 'support', 'speak to'];
        foreach ($keywords as $kw) {
            if (str_contains($lower, $kw)) {
                return true;
            }
        }

        return false;
    }

    protected function humanHandoffCustomerMessage(): string
    {
        return "You've been handed over to our team. A human agent will assist you and we'll contact you soon.\n\n"
            .'Thank you for your patience.'
            .AIReplyService::QUICK_MENU_SUFFIX;
    }

    protected function companyHasActiveSubscription(Company $company): bool
    {
        return Subscription::where('company_id', $company->id)
            ->where('status', 'active')
            ->where('end_date', '>=', now()->toDateString())
            ->exists();
    }

    protected function isFirstCustomerMessageInChat(int $chatId): bool
    {
        return Message::where('chat_id', $chatId)
            ->where('sender', 'customer')
            ->count() === 1;
    }

    protected function alreadyRepliedToThisMessage(): bool
    {
        if (! $this->whatsappMessageId) {
            return false;
        }
        $incoming = Message::where('chat_id', $this->chatId)
            ->where('whatsapp_message_id', $this->whatsappMessageId)
            ->where('sender', 'customer')
            ->first();
        if (! $incoming) {
            return false;
        }

        return Message::where('chat_id', $this->chatId)
            ->where('sender', 'bot')
            ->where('created_at', '>=', $incoming->created_at)
            ->exists();
    }

    protected function sendReplyAndSave(
        WhatsAppMessageSenderService $waSender,
        Company $company,
        Chat $chat,
        string $replyText
    ): void {
        $account = $company->whatsappAccount;
        if (! $account || ! $account->isActive()) {
            return;
        }

        $result = $waSender->sendText($account, $this->customerPhone, $replyText);

        Message::create([
            'chat_id' => $this->chatId,
            'content' => $replyText,
            'sender' => 'bot',
            'status' => $result['success'] ? 'sent' : 'failed',
            'whatsapp_message_id' => $result['message_id'] ?? null,
        ]);
        $chat->update([
            'last_message' => $replyText,
            'last_message_at' => now(),
            'ai_handled' => true,
        ]);

        if (! $result['success']) {
            Log::error('ProcessIncomingWhatsAppMessage: send failed', ['error' => $result['error'] ?? 'unknown']);
            throw new \RuntimeException($result['error'] ?? 'WhatsApp send failed');
        }
    }

    protected function maybeSendOrderSelectionImage(
        WhatsAppMessageSenderService $waSender,
        Company $company,
        Chat $chat,
        OrderFlowService $orderFlow,
        ?string $stepBefore
    ): void {
        $stepNow = $chat->conversation_step;
        $shouldSend =
            ($stepBefore === OrderFlowService::STEP_PRODUCT && in_array($stepNow, [OrderFlowService::STEP_VARIANT, OrderFlowService::STEP_PRODUCT_QTY], true))
            || ($stepBefore === OrderFlowService::STEP_VARIANT && $stepNow === OrderFlowService::STEP_PRODUCT_QTY);

        if (! $shouldSend) {
            return;
        }

        $preview = $orderFlow->resolveCurrentSelectionImage($chat, $company);
        if (! $preview || empty($preview['url'])) {
            return;
        }

        $account = $company->whatsappAccount;
        if (! $account || ! $account->isActive()) {
            return;
        }

        $result = $waSender->sendImage($account, $this->customerPhone, $preview['url'], $preview['caption'] ?? null);
        Message::create([
            'chat_id' => $chat->id,
            'content' => $preview['caption'] ?? '',
            'message_type' => 'image',
            'attachment_url' => $preview['url'],
            'attachment_name' => null,
            'attachment_mime' => 'image/jpeg',
            'attachment_size' => null,
            'sender' => 'bot',
            'status' => $result['success'] ? 'sent' : 'failed',
            'whatsapp_message_id' => $result['message_id'] ?? null,
        ]);

        if (! $result['success']) {
            Log::warning('ProcessIncomingWhatsAppMessage: selection image send failed', ['error' => $result['error'] ?? 'unknown']);
        }
    }

    /**
     * Gives OpenAI enough context to sound like the owner without contradicting the WhatsApp order wizard.
     */
    protected function orderFlowContextForAi(Chat $chat): ?string
    {
        $step = $chat->conversation_step;
        if ($step === null || $step === '') {
            return null;
        }

        $lines = [
            OrderFlowService::STEP_PRODUCT => 'The customer is building an order: they are selecting products and quantities.',
            OrderFlowService::STEP_VARIANT => 'The customer is choosing a product option (variant) before quantity.',
            OrderFlowService::STEP_PRODUCT_QTY => 'The customer is entering quantity for a product line.',
            OrderFlowService::STEP_ADDRESS => 'The customer is being asked for a delivery address for their order.',
            OrderFlowService::STEP_CONFIRM => 'The customer is at order confirmation (review totals before placing).',
            OrderFlowService::STEP_PAYMENT_METHOD => 'The customer is choosing how to pay for an order.',
            OrderFlowService::STEP_MPESA_PHONE => 'The customer is confirming or entering a phone number for M-Pesa payment.',
            OrderFlowService::STEP_EXISTING_ORDER_ADDRESS => 'The customer is completing delivery details for an order that already exists.',
            OrderFlowService::STEP_EXISTING_ORDER_PAYMENT_METHOD => 'The customer is choosing payment for an existing order.',
            OrderFlowService::STEP_EXISTING_ORDER_PROMPT => 'The customer is deciding whether to continue with an existing order.',
        ];

        $line = $lines[$step] ?? "The customer is in an active checkout step ({$step}).";
        $draft = $chat->order_draft;
        $extra = [];
        if (is_array($draft) && isset($draft['items']) && is_array($draft['items']) && $draft['items'] !== []) {
            $extra[] = 'They already have items in the draft order; be brief and do not restart the catalog unless they ask.';
        }

        return $line.(empty($extra) ? '' : ' '.implode(' ', $extra));
    }

    /**
     * @param  'handoff'|'agent_active'|'message'  $kind
     */
    protected function notifyCompanyNewMessage(Company $company, MailService $mailService, string $kind): void
    {
        $settings = $company->settings;
        if (! $settings || ! $settings->notifications_enabled) {
            return;
        }

        $customerLabel = $this->customerName ?: $this->customerPhone;
        app(CompanyInAppNotificationService::class)->recordWhatsAppCustomerAlert(
            $company,
            $this->chatId,
            $customerLabel,
            $this->messageText,
            $kind
        );

        $to = $company->email;
        if (! $to) {
            return;
        }
        $appName = MailService::applicationName();
        $chatsUrl = rtrim(config('app.frontend_url', config('app.url')), '/').'/dashboard/chats';
        $isHandoff = $kind === 'handoff';
        $subject = $isHandoff
            ? "[{$appName}] Customer requested human – ".($this->customerName ?: $this->customerPhone)
            : "[{$appName}] New message from ".($this->customerName ?: $this->customerPhone);
        $preview = mb_substr($this->messageText, 0, 200);
        if (mb_strlen($this->messageText) > 200) {
            $preview .= '…';
        }
        try {
            $mailService->sendNewMessageNotification($to, $this->customerName ?: 'Customer', $this->customerPhone, $preview, $chatsUrl);
        } catch (\Throwable $e) {
            Log::warning('Failed to send new message notification', ['company_id' => $company->id, 'error' => $e->getMessage()]);
        }
    }
}
