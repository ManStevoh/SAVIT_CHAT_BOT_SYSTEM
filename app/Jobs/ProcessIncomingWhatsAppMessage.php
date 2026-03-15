<?php

namespace App\Jobs;

use App\Models\Chat;
use App\Models\Company;
use App\Models\Message;
use App\Services\AIReplyService;
use App\Services\WhatsAppMessageSenderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessIncomingWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $companyId,
        public int $chatId,
        public string $customerPhone,
        public string $phoneNumberId,
        public string $messageText,
        public ?string $customerName = null
    ) {}

    public function handle(AIReplyService $aiReply, WhatsAppMessageSenderService $waSender): void
    {
        $company = Company::find($this->companyId);
        if (! $company) {
            Log::warning('ProcessIncomingWhatsAppMessage: company not found', ['company_id' => $this->companyId]);
            return;
        }

        $settings = $company->settings;
        if (! $settings || ! $settings->auto_reply_enabled) {
            return;
        }

        $account = $company->whatsappAccount;
        if (! $account || ! $account->isActive()) {
            Log::warning('ProcessIncomingWhatsAppMessage: no active WhatsApp account', ['company_id' => $this->companyId]);
            return;
        }

        $replyText = $aiReply->getReplyForMessage($company, $this->messageText, $this->customerName);
        $result = $waSender->sendText($account, $this->customerPhone, $replyText);

        $chat = Chat::find($this->chatId);
        if ($chat) {
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
        }

        if (! $result['success']) {
            Log::error('ProcessIncomingWhatsAppMessage: send failed', ['error' => $result['error'] ?? 'unknown']);
        }
    }
}
