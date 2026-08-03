<?php

namespace App\Jobs;

use App\Models\Chat;
use App\Models\Company;
use App\Models\ConversationLearningSample;
use App\Models\Message;
use App\Models\Subscription;
use App\Jobs\Agent\ExtractCustomerMemoriesJob;
use App\Jobs\Agent\RunBackgroundThinkingJob;
use App\Jobs\Agent\ReflectOnConversationJob;
use App\Services\Agent\CommerceAgentReplyService;
use App\Services\AIReplyService;
use App\Services\CompanyInAppNotificationService;
use App\Services\MailService;
use App\Services\OrderFlowService;
use App\Services\PlanLimitService;
use App\Services\Platform\UsageMeterService;
use App\Services\AI\AiLearningConfig;
use App\Services\Conversation\MessageLanguageDetector;
use App\Services\ConversationLearningService;
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

    /** Seconds to hold the unique lock (avoids stuck locks when a job never runs). */
    public int $uniqueFor = 120;

    /**
     * Dispatch inbound auto-reply. Default is synchronous so AI auto-reply actually sends
     * without a queue worker. Optional after-response / queue via config.
     */
    public static function dispatchIncoming(
        int $companyId,
        int $chatId,
        string $customerPhone,
        string $phoneNumberId,
        string $messageText,
        ?string $customerName = null,
        ?string $whatsappMessageId = null,
        ?int $incomingMessageId = null,
        bool $forceReply = false,
    ): mixed {
        if (config('whatsapp.auto_reply_via_queue', false)) {
            return static::dispatch(
                $companyId,
                $chatId,
                $customerPhone,
                $phoneNumberId,
                $messageText,
                $customerName,
                $whatsappMessageId,
                $incomingMessageId,
                $forceReply,
            );
        }

        if (config('whatsapp.auto_reply_after_response', false)) {
            return static::dispatchAfterResponse(
                $companyId,
                $chatId,
                $customerPhone,
                $phoneNumberId,
                $messageText,
                $customerName,
                $whatsappMessageId,
                $incomingMessageId,
                $forceReply,
            );
        }

        static::dispatchSyncIncoming(
            $companyId,
            $chatId,
            $customerPhone,
            $phoneNumberId,
            $messageText,
            $customerName,
            $whatsappMessageId,
            $incomingMessageId,
            $forceReply,
        );

        return null;
    }

    /**
     * Run auto-reply immediately in this request (used by Ask AI / hand-back).
     */
    public static function dispatchSyncIncoming(
        int $companyId,
        int $chatId,
        string $customerPhone,
        string $phoneNumberId,
        string $messageText,
        ?string $customerName = null,
        ?string $whatsappMessageId = null,
        ?int $incomingMessageId = null,
        bool $forceReply = false,
    ): void {
        static::dispatchSync(
            $companyId,
            $chatId,
            $customerPhone,
            $phoneNumberId,
            $messageText,
            $customerName,
            $whatsappMessageId,
            $incomingMessageId,
            $forceReply,
        );
    }

    /**
     * Unique key so only one job runs per incoming message (avoids duplicate replies when Meta retries
     * or dashboard poll races the webhook). forceReply shares the same key.
     */
    public function uniqueId(): string
    {
        if ($this->whatsappMessageId) {
            return "wa_incoming:{$this->chatId}:{$this->whatsappMessageId}";
        }

        if ($this->incomingMessageId) {
            return "wa_incoming:{$this->chatId}:msg:{$this->incomingMessageId}";
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
        public ?string $whatsappMessageId = null,
        public ?int $incomingMessageId = null,
        public bool $forceReply = false,
    ) {
        $this->uniqueFor = 120;
    }

    public function handle(AIReplyService $aiReply, WhatsAppMessageSenderService $waSender, MailService $mailService): void
    {
        @set_time_limit(120);
        @ini_set('max_execution_time', '120');

        \App\Services\WhatsApp\WhatsAppDebugLogger::registerShutdownHandler([
            'company_id' => $this->companyId,
            'chat_id' => $this->chatId,
            'whatsapp_message_id' => $this->whatsappMessageId,
        ]);

        $lockKey = 'wa_reply_lock:'.$this->uniqueId();
        $lock = \Illuminate\Support\Facades\Cache::lock($lockKey, 120);
        if (! $lock->get()) {
            \App\Services\WhatsApp\WhatsAppDebugLogger::info('SKIPPED_DUPLICATE_LOCK_HELD', [
                'chat_id' => $this->chatId,
                'whatsapp_message_id' => $this->whatsappMessageId,
            ]);
            Log::info('ProcessIncomingWhatsAppMessage: skipped duplicate (lock held)', [
                'chat_id' => $this->chatId,
                'whatsapp_message_id' => $this->whatsappMessageId,
            ]);

            return;
        }

        try {
            $this->handleLocked($aiReply, $waSender, $mailService);
        } catch (\Throwable $e) {
            \App\Services\WhatsApp\WhatsAppDebugLogger::error('PIPELINE_CRITICAL_UNHANDLED_EXCEPTION', [
                'company_id' => $this->companyId,
                'chat_id' => $this->chatId,
                'error' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine(),
            ], $e);
            Log::error('ProcessIncomingWhatsAppMessage: unhandled exception', [
                'company_id' => $this->companyId,
                'chat_id' => $this->chatId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            $lock->release();
        }
    }

    protected function handleLocked(AIReplyService $aiReply, WhatsAppMessageSenderService $waSender, MailService $mailService): void
    {
        \App\Services\WhatsApp\WhatsAppDebugLogger::info('JOB_EXECUTION_START', [
            'company_id' => $this->companyId,
            'chat_id' => $this->chatId,
            'customer_phone' => $this->customerPhone,
            'whatsapp_message_id' => $this->whatsappMessageId,
            'incoming_message_id' => $this->incomingMessageId,
            'force_reply' => $this->forceReply,
            'message_preview' => mb_substr($this->messageText, 0, 100),
        ]);

        $company = Company::find($this->companyId);
        if (! $company) {
            \App\Services\WhatsApp\WhatsAppDebugLogger::warning('SKIPPED_COMPANY_NOT_FOUND', ['company_id' => $this->companyId]);
            Log::warning('ProcessIncomingWhatsAppMessage: company not found', ['company_id' => $this->companyId]);

            return;
        }

        $chat = Chat::find($this->chatId);
        if (! $chat) {
            \App\Services\WhatsApp\WhatsAppDebugLogger::warning('SKIPPED_CHAT_NOT_FOUND', ['chat_id' => $this->chatId]);

            return;
        }

        $this->updateChatLanguage($company, $chat);

        if ($this->tryHandleCustomerLearningFeedback($chat)) {
            \App\Services\WhatsApp\WhatsAppDebugLogger::info('HANDLED_CUSTOMER_LEARNING_FEEDBACK', ['chat_id' => $chat->id]);

            return;
        }

        // Agent lock: only silence AI when a human teammate has actually replied after the lock.
        // AI-initiated transfer notifies the team but must not strand the customer if nobody took over.
        if ($chat->agent_handling_at !== null) {
            if (! $chat->isAgentHandling(30)) {
                $chat->update(['agent_handling_at' => null]);
                $chat->refresh();
            } elseif (! $this->forceReply) {
                $humanTookOver = Message::query()
                    ->where('chat_id', $chat->id)
                    ->where('sender', 'agent')
                    ->where('created_at', '>=', $chat->agent_handling_at)
                    ->exists();
                if ($humanTookOver) {
                    \App\Services\WhatsApp\WhatsAppDebugLogger::warning('SKIPPED_HUMAN_AGENT_ACTIVE', [
                        'chat_id' => $chat->id,
                        'agent_handling_at' => $chat->agent_handling_at?->toIso8601String(),
                    ]);
                    $this->notifyCompanyNewMessage($company, $mailService, 'agent_active');

                    return;
                }
                // No human reply yet — resume AI frontline.
                $chat->update(['agent_handling_at' => null]);
                $chat->refresh();
            }
        }

        // Keyword handoff is legacy fallback only. Agent commerce always routes to AI
        // (including menu "3") so transfer_to_human can decide from dialogue intent.
        if ($this->wantsHumanEscalation($chat, allowKeywordMatch: ! CommerceAgentReplyService::isEnabledForCompany($company))) {
            \App\Services\WhatsApp\WhatsAppDebugLogger::info('HUMAN_ESCALATION_TRIGGERED', ['chat_id' => $chat->id]);
            $this->notifyCompanyNewMessage($company, $mailService, 'handoff');
            app(OrderFlowService::class)->resetOrderState($chat);
            $chat->refresh();
            $chat->update(['agent_handling_at' => now()]);
            $account = $company->whatsappAccount;
            if ($account && $account->isActive()) {
                $this->sendReplyAndSave($waSender, $company, $chat, $this->humanHandoffCustomerMessage(), 'handoff');
            } else {
                Log::warning('ProcessIncomingWhatsAppMessage: human escalation but no active WhatsApp account', ['company_id' => $this->companyId]);
            }

            return;
        }

        if (! $this->companyHasActiveSubscription($company)) {
            \App\Services\WhatsApp\WhatsAppDebugLogger::warning('SKIPPED_INACTIVE_SUBSCRIPTION', [
                'company_id' => $company->id,
                'chat_id' => $chat->id,
            ]);
            $replyText = 'Our service is temporarily unavailable. Please try again later or contact support.';
            $this->sendReplyAndSave($waSender, $company, $chat, $replyText, 'subscription_unavailable');

            return;
        }

        if (! PlanLimitService::isWithinMessageLimit($company)) {
            \App\Services\WhatsApp\WhatsAppDebugLogger::warning('SKIPPED_PLAN_LIMIT_REACHED', ['company_id' => $company->id]);
            Log::info('ProcessIncomingWhatsAppMessage: message limit reached, skipping auto-reply', ['company_id' => $company->id]);
            $this->notifyCompanyNewMessage($company, $mailService, 'message');

            return;
        }

        app(UsageMeterService::class)->increment($company, 'messages');

        $settings = $company->settings;
        // Default ON: missing settings row must not disable AI auto-reply.
        if ($settings && $settings->auto_reply_enabled === false) {
            \App\Services\WhatsApp\WhatsAppDebugLogger::warning('SKIPPED_AUTO_REPLY_DISABLED', [
                'company_id' => $company->id,
                'auto_reply_enabled' => false,
            ]);
            $this->notifyCompanyNewMessage($company, $mailService, 'message');

            return;
        }

        $account = $company->whatsappAccount;
        $accountActive = $account && $account->isActive();
        $autoReplyEnabled = ($settings?->auto_reply_enabled ?? true) !== false;

        \App\Services\WhatsApp\WhatsAppDebugLogger::log('JOB_PROCESSING_PIPELINE', [
            'company_id' => $company->id,
            'chat_id' => $chat->id,
            'customer_phone' => $this->customerPhone,
            'whatsapp_account_id' => $account?->id,
            'whatsapp_account_status' => $account?->status,
            'whatsapp_account_is_active' => $accountActive,
            'auto_reply_enabled' => $autoReplyEnabled,
            'agent_handling_at' => $chat->agent_handling_at?->toIso8601String(),
            'message_text_preview' => mb_substr($this->messageText, 0, 100),
        ]);

        if (! $accountActive) {
            \App\Services\WhatsApp\WhatsAppDebugLogger::log('SKIPPED_INACTIVE_WHATSAPP_ACCOUNT', [
                'account_id' => $account?->id,
                'status' => $account?->status,
            ]);
            Log::warning('ProcessIncomingWhatsAppMessage: no active WhatsApp account', ['company_id' => $this->companyId]);

            return;
        }

        // Always dedupe — including force/handback — so webhook + dashboard poll cannot double-send.
        if ($this->alreadyRepliedToThisMessage()) {
            \App\Services\WhatsApp\WhatsAppDebugLogger::log('SKIPPED_ALREADY_REPLIED_DUPLICATE', [
                'incoming_message_id' => $this->incomingMessageId,
                'whatsapp_message_id' => $this->whatsappMessageId,
            ]);

            return;
        }

        if (CommerceAgentReplyService::isEnabledForCompany($company)) {
            $agentResult = null;
            try {
                $messageText = $this->enrichIncomingMessage($company, $chat);

                \App\Services\WhatsApp\WhatsAppDebugLogger::log('COMMERCE_AGENT_TRY_GENERATE', [
                    'company_id' => $company->id,
                    'chat_id' => $chat->id,
                    'message_text' => mb_substr($messageText, 0, 150),
                ]);

                $ownerVoice = app(\App\Services\Agent\Voice\OwnerVoiceCommandService::class);
                if ($ownerVoice->isOwnerPhone($company, $this->customerPhone)) {
                    $ownerResult = $ownerVoice->handle($company, $chat, $messageText);
                    if (($ownerResult['handled'] ?? false) && trim((string) ($ownerResult['reply'] ?? '')) !== '') {
                        \App\Services\WhatsApp\WhatsAppDebugLogger::log('OWNER_VOICE_HANDLED', $ownerResult);
                        $this->sendReplyAndSave($waSender, $company, $chat, (string) $ownerResult['reply'], 'owner_voice');

                        return;
                    }
                }

                $agentResult = app(CommerceAgentReplyService::class)->generate(
                    $company,
                    $chat,
                    $this->customerPhone,
                    $this->customerName,
                    $messageText,
                );

                \App\Services\WhatsApp\WhatsAppDebugLogger::log('COMMERCE_AGENT_RESULT', [
                    'has_result' => $agentResult !== null,
                    'reply_length' => strlen($agentResult['reply'] ?? ''),
                    'reply_preview' => mb_substr($agentResult['reply'] ?? '', 0, 150),
                    'route' => $agentResult['route'] ?? null,
                ]);
            } catch (\Throwable $e) {
                \App\Services\WhatsApp\WhatsAppDebugLogger::error('COMMERCE_AGENT_EXCEPTION', [
                    'company_id' => $company->id,
                    'chat_id' => $chat->id,
                    'error' => $e->getMessage(),
                    'file' => basename($e->getFile()),
                    'line' => $e->getLine(),
                ], $e);
            }

            if ($agentResult !== null && trim($agentResult['reply'] ?? '') !== '') {
                if ($agentResult['handoff']) {
                    $this->notifyCompanyNewMessage($company, $mailService, 'handoff');
                }
                $this->sendReplyAndSave(
                    $waSender,
                    $company,
                    $chat,
                    $agentResult['reply'],
                    $agentResult['route'],
                    $agentResult['log_id'] ?? null,
                    $agentResult['pay_url'] ?? null,
                );
                $this->maybeSendVisionProductImage($waSender, $company, $chat);
                $this->schedulePostConversationJobs($company, $chat);

                return;
            }

            // Agent OS unavailable — prefer legacy AI before keyword order-flow / FAQ shortcuts.
            $inOrderStep = filled($chat->conversation_step);
            if (! $inOrderStep) {
                $replyText = $aiReply->getReplyForMessage(
                    $company,
                    $messageText,
                    $this->customerName,
                    $this->chatId,
                    $this->orderFlowContextForAi($chat),
                );
                \App\Services\WhatsApp\WhatsAppDebugLogger::log('LEGACY_AI_FALLBACK_RESULT', [
                    'reply_preview' => mb_substr($replyText, 0, 150),
                    'route' => $aiReply->getLastReplyRoute(),
                ]);
                if (trim($replyText) !== '') {
                    $this->sendReplyAndSave($waSender, $company, $chat, $replyText, $aiReply->getLastReplyRoute());

                    return;
                }
            }
        }

        // Active checkout step: keep state-machine continuity. Otherwise AI already tried above
        // (agent companies) or runs after order-flow miss (legacy).
        $orderFlow = app(OrderFlowService::class);
        $stepBefore = $chat->conversation_step;
        $orderReply = $orderFlow->processMessage($chat, $company, $this->messageText, $this->customerName ?? '', $this->customerPhone);
        \App\Services\WhatsApp\WhatsAppDebugLogger::log('ORDER_FLOW_PROCESS_RESULT', [
            'has_order_reply' => filled($orderReply),
            'order_reply_preview' => mb_substr((string) $orderReply, 0, 150),
        ]);
        if ($orderReply !== null && trim($orderReply) !== '') {
            $chat->refresh();
            $this->maybeSendOrderSelectionImage($waSender, $company, $chat, $orderFlow, $stepBefore);
            $this->sendReplyAndSave($waSender, $company, $chat, $orderReply, 'order_flow');

            return;
        }

        if ($this->isFirstCustomerMessageInChat($this->chatId)) {
            app(OrderFlowService::class)->resetOrderState($chat);
            $chat->refresh();

            $skipOpening = $aiReply->shouldSkipScriptedOpening($company, $this->messageText);
            if (! $skipOpening) {
                $greeting = $aiReply->getGreetingOpening($company, $this->customerName);
                \App\Services\WhatsApp\WhatsAppDebugLogger::log('FIRST_MESSAGE_GREETING_SEND', [
                    'greeting_preview' => mb_substr($greeting, 0, 150),
                ]);
                $this->sendReplyAndSave($waSender, $company, $chat, $greeting, $aiReply->getLastReplyRoute());
                $chat->refresh();
            }

            if ($skipOpening) {
                $replyText = $aiReply->getReplyForMessage($company, $this->messageText, $this->customerName, $this->chatId, $this->orderFlowContextForAi($chat));
                \App\Services\WhatsApp\WhatsAppDebugLogger::log('FIRST_MESSAGE_SKIP_OPENING_REPLY', [
                    'reply_preview' => mb_substr($replyText, 0, 150),
                ]);
                if (trim($replyText) !== '') {
                    $this->sendReplyAndSave($waSender, $company, $chat, $replyText, $aiReply->getLastReplyRoute());
                }

                return;
            }

            $followUp = $aiReply->getReplyAfterOpeningGreeting($company, $this->messageText, $this->customerName, $this->chatId, $this->orderFlowContextForAi($chat));
            if ($followUp !== null && trim($followUp) !== '') {
                $this->sendReplyAndSave($waSender, $company, $chat, $followUp, $aiReply->getLastReplyRoute());
            }

            return;
        }

        $replyText = $aiReply->getReplyForMessage($company, $this->messageText, $this->customerName, $this->chatId, $this->orderFlowContextForAi($chat));
        \App\Services\WhatsApp\WhatsAppDebugLogger::log('DEFAULT_AI_FALLBACK_REPLY', [
            'reply_preview' => mb_substr($replyText, 0, 150),
            'route' => $aiReply->getLastReplyRoute(),
        ]);
        $this->sendReplyAndSave($waSender, $company, $chat, $replyText, $aiReply->getLastReplyRoute());
    }

    /**
     * Legacy keyword / menu handoff. When agent commerce is on, callers pass
     * allowKeywordMatch=false so everything (including "3") goes to the AI layer.
     */
    protected function wantsHumanEscalation(Chat $chat, bool $allowKeywordMatch = true): bool
    {
        if (! $allowKeywordMatch) {
            return false;
        }
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
        // Match EnsureSubscriptionActive / EntitlementService: trial companies must get AI replies.
        return Subscription::where('company_id', $company->id)
            ->whereIn('status', ['active', 'trial'])
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now()->toDateString());
            })
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
        $incoming = null;
        if ($this->whatsappMessageId) {
            $incoming = Message::where('chat_id', $this->chatId)
                ->where('whatsapp_message_id', $this->whatsappMessageId)
                ->where('sender', 'customer')
                ->first();
        } elseif ($this->incomingMessageId) {
            $incoming = Message::where('chat_id', $this->chatId)
                ->where('id', $this->incomingMessageId)
                ->where('sender', 'customer')
                ->first();
        }

        if (! $incoming) {
            return false;
        }

        return Message::where('chat_id', $this->chatId)
            ->whereIn('sender', ['bot', 'agent'])
            ->where('id', '>', $incoming->id)
            ->exists();
    }

    protected function sendReplyAndSave(
        WhatsAppMessageSenderService $waSender,
        Company $company,
        Chat $chat,
        string $replyText,
        ?string $replySource = null,
        ?int $aiRequestLogId = null,
        ?string $ctaUrl = null,
    ): void {
        $account = $company->whatsappAccount;
        if (! $account || ! $account->isActive()) {
            return;
        }

        $inboundWasAudio = false;
        if ($this->incomingMessageId) {
            $incoming = Message::find($this->incomingMessageId);
            $inboundWasAudio = $incoming && (
                $incoming->message_type === 'audio'
                || str_contains((string) ($incoming->attachment_mime ?? ''), 'audio')
                || str_contains((string) $incoming->content, '[audio received]')
            );
        }

        $company->loadMissing('settings');
        $voiceMode = $company->settings?->agent_voice_reply_mode ?? 'dual_text_and_voice';
        $voiceOutbound = app(\App\Services\Agent\Voice\VoiceOutboundService::class);
        $shouldVoice = $voiceOutbound->shouldReplyWithVoice($company, $inboundWasAudio) && $voiceMode !== 'text_only';

        \App\Services\WhatsApp\WhatsAppDebugLogger::log('SEND_REPLY_AND_SAVE_START', [
            'company_id' => $company->id,
            'chat_id' => $chat->id,
            'customer_phone' => $this->customerPhone,
            'reply_source' => $replySource,
            'inbound_was_audio' => $inboundWasAudio,
            'voice_mode' => $voiceMode,
            'should_voice' => $shouldVoice,
            'reply_text_preview' => mb_substr($replyText, 0, 150),
            'cta_url' => $ctaUrl,
        ]);

        if ($shouldVoice && $voiceMode === 'voice_only') {
            $voiceResult = $voiceOutbound->sendVoiceReply($account, $company, $this->customerPhone, $replyText);
            \App\Services\WhatsApp\WhatsAppDebugLogger::log('VOICE_ONLY_SEND_RESULT', $voiceResult);
            if ($voiceResult['success'] ?? false) {
                Message::create([
                    'chat_id' => $this->chatId,
                    'sender' => 'bot',
                    'content' => $replyText,
                    'message_type' => 'audio',
                    'reply_source' => ($replySource ?? 'agent').'_voice',
                    'whatsapp_message_id' => $voiceResult['message_id'] ?? null,
                    'ai_request_log_id' => $aiRequestLogId,
                ]);
                $chat->update([
                    'last_message' => mb_substr($replyText, 0, 500),
                    'last_message_at' => now(),
                    'ai_handled' => true,
                ]);

                return;
            }
        }

        if (($ctaUrl === null || $ctaUrl === '') && preg_match('~(https?://[^\s]+(?:/pay/|/invoice/|/receipt|/orders/receipt|pesapaliframe)[^\s]*)~i', $replyText, $m)) {
            $ctaUrl = trim($m[1], "().,;[]");
        }

        if ($ctaUrl !== null && $ctaUrl !== '') {
            $buttonText = 'Pay Online';
            if (str_contains($ctaUrl, '/invoice/')) {
                $buttonText = 'View Invoice';
            } elseif (str_contains($ctaUrl, '/receipt')) {
                $buttonText = 'View Receipt';
            }

            $result = $waSender->sendInteractiveCtaUrl(
                $account,
                $this->customerPhone,
                $replyText,
                $buttonText,
                $ctaUrl
            );
        } else {
            $result = $waSender->sendText($account, $this->customerPhone, $replyText);
        }
        \App\Services\WhatsApp\WhatsAppDebugLogger::log('TEXT_SEND_META_API_RESULT', [
            'success' => $result['success'] ?? false,
            'message_id' => $result['message_id'] ?? null,
            'error' => $result['error'] ?? null,
        ]);

        $message = Message::create([
            'chat_id' => $this->chatId,
            'content' => $replyText,
            'sender' => 'bot',
            'reply_source' => $replySource,
            'status' => $result['success'] ? 'sent' : 'failed',
            'whatsapp_message_id' => $result['message_id'] ?? null,
            'ai_request_log_id' => $aiRequestLogId,
        ]);

        if ($result['success'] && $this->shouldLinkLearningSample($replySource)) {
            $sampleId = app(ConversationLearningService::class)->linkSampleToMessage(
                (int) $company->id,
                $this->chatId,
                $this->messageText,
                (int) $message->id,
            );
            if ($sampleId !== null) {
                $message->update(['learning_sample_id' => $sampleId]);
            }
        }

        $chat->update([
            'last_message' => $replyText,
            'last_message_at' => now(),
            'ai_handled' => true,
        ]);

        if ($shouldVoice && $voiceMode === 'dual_text_and_voice') {
            try {
                $voiceResult = $voiceOutbound->sendVoiceReply($account, $company, $this->customerPhone, $replyText);
                if ($voiceResult['success'] ?? false) {
                    Message::create([
                        'chat_id' => $this->chatId,
                        'sender' => 'bot',
                        'content' => $replyText,
                        'message_type' => 'audio',
                        'reply_source' => ($replySource ?? 'agent').'_voice',
                        'whatsapp_message_id' => $voiceResult['message_id'] ?? null,
                        'ai_request_log_id' => $aiRequestLogId,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Dual-mode voice reply failed', ['error' => $e->getMessage()]);
            }
        }

        if (! $result['success']) {
            Log::error('ProcessIncomingWhatsAppMessage: send failed', ['error' => $result['error'] ?? 'unknown']);
            throw new \RuntimeException($result['error'] ?? 'WhatsApp send failed');
        }
    }

    protected function shouldLinkLearningSample(?string $replySource): bool
    {
        if ($replySource === null || $replySource === '') {
            return false;
        }

        return $replySource === 'openai'
            || $replySource === 'faq'
            || str_starts_with($replySource, 'faq_');
    }

    protected function tryHandleCustomerLearningFeedback(Chat $chat): bool
    {
        $text = mb_strtolower(trim($this->messageText));
        $feedback = match (true) {
            in_array($text, ['👍', 'thumbs up', 'helpful', 'good'], true) => 1,
            in_array($text, ['👎', 'thumbs down', 'not helpful', 'bad'], true) => -1,
            default => null,
        };
        if ($feedback === null) {
            return false;
        }

        $lastBot = Message::query()
            ->where('chat_id', $chat->id)
            ->where('sender', 'bot')
            ->whereNotNull('learning_sample_id')
            ->orderByDesc('id')
            ->first();

        if (! $lastBot) {
            return false;
        }

        $lastBot->update(['learning_feedback' => $feedback]);
        $sample = ConversationLearningSample::find($lastBot->learning_sample_id);
        if ($sample) {
            app(ConversationLearningService::class)->applyFeedback($sample, $feedback);
        }

        return true;
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

    protected function maybeSendVisionProductImage(
        WhatsAppMessageSenderService $waSender,
        Company $company,
        Chat $chat,
    ): void {
        if (! $this->incomingMessageId) {
            return;
        }

        $analysis = \App\Models\MessageVisionAnalysis::where('message_id', $this->incomingMessageId)->first();
        if (! $analysis) {
            return;
        }

        $preview = app(\App\Services\Agent\Vision\VisionOutboundImageService::class)
            ->resolveProductPreview($company, $analysis);
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
            Log::warning('ProcessIncomingWhatsAppMessage: vision product image send failed', [
                'error' => $result['error'] ?? 'unknown',
                'product_id' => $preview['product_id'] ?? null,
            ]);
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

    private function updateChatLanguage(Company $company, Chat $chat): void
    {
        $config = app(AiLearningConfig::class);
        if (! $config->autoDetectLanguage()) {
            return;
        }

        $company->loadMissing('settings');
        $fallback = $company->settings?->default_reply_language ?? $config->fallbackLanguage();
        $detected = app(MessageLanguageDetector::class)->detect($this->messageText, $fallback);

        if ($detected !== $chat->detected_language) {
            $chat->update(['detected_language' => $detected]);
        }
    }

    private function enrichIncomingMessage(Company $company, Chat $chat): string
    {
        $text = $this->enrichMessageWithAudio($company, $chat);

        return $this->enrichMessageWithVision($company, $chat, $text);
    }

    private function enrichMessageWithAudio(Company $company, Chat $chat): string
    {
        $text = $this->messageText;
        if (! $this->incomingMessageId) {
            return $text;
        }

        $message = Message::find($this->incomingMessageId);
        if (! $message) {
            return $text;
        }

        $isAudio = $message->message_type === 'audio'
            || str_contains((string) ($message->attachment_mime ?? ''), 'audio')
            || str_contains((string) $message->content, '[audio received]');

        if (! $isAudio) {
            return $text;
        }

        try {
            $transcript = app(\App\Services\Agent\Voice\VoiceTranscriptionService::class)
                ->transcribeMessage($message, $company);
            if ($transcript) {
                return 'Owner/customer voice note (transcribed): '.$transcript;
            }
        } catch (\Throwable $e) {
            Log::warning('Audio enrichment failed', [
                'message_id' => $this->incomingMessageId,
                'error' => $e->getMessage(),
            ]);
        }

        return $text;
    }

    private function enrichMessageWithVision(Company $company, Chat $chat, ?string $baseText = null): string
    {
        $text = $baseText ?? $this->messageText;
        if (! $this->incomingMessageId) {
            return $text;
        }

        $message = Message::find($this->incomingMessageId);
        if (! $message || $message->message_type !== 'image' || empty($message->attachment_url)) {
            return $text;
        }

        try {
            $analysis = app(\App\Services\Agent\Vision\VisionPipelineService::class)->analyzeMessage($message);
            if ($analysis) {
                $block = $analysis->toPromptBlock();
                $caption = trim($text);
                if ($caption === '' || str_starts_with($caption, '[')) {
                    return $block;
                }

                return $block."\n\nCustomer caption: ".$caption;
            }
        } catch (\Throwable $e) {
            Log::warning('Vision enrichment failed', [
                'message_id' => $this->incomingMessageId,
                'error' => $e->getMessage(),
            ]);
        }

        return $text;
    }

    private function schedulePostConversationJobs(Company $company, Chat $chat): void
    {
        if (! CommerceAgentReplyService::isEnabledForCompany($company)) {
            return;
        }

        $settings = $company->settings;
        if ($settings?->learn_from_conversations) {
            $memoryDelay = (int) config('agent.proactive.memory_extraction_delay_minutes', 30);
            ExtractCustomerMemoriesJob::dispatch(
                $this->companyId,
                $this->chatId,
                $this->customerPhone,
            )->delay(now()->addMinutes($memoryDelay));
        }

        $reflectionDelay = (int) config('agent.proactive.reflection_delay_minutes', 45);
        ReflectOnConversationJob::dispatch($this->companyId, $this->chatId)
            ->delay(now()->addMinutes($reflectionDelay));

        $bgDelay = (int) config('agent.platform.background_thinking_delay_minutes', 50);
        RunBackgroundThinkingJob::dispatch($this->companyId, $this->chatId)
            ->delay(now()->addMinutes($bgDelay));
    }
}
