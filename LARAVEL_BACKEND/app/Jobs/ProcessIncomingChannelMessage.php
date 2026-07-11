<?php

namespace App\Jobs;

use App\Models\Chat;
use App\Models\Company;
use App\Models\Message;
use App\Services\Agent\Channels\ChatChannel;
use App\Services\Agent\CommerceAgentReplyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Agent reply for web widget, email, and Instagram DM channels (no WhatsApp send).
 */
class ProcessIncomingChannelMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $companyId,
        public int $chatId,
        public int $incomingMessageId,
        public string $messageText,
        public ?string $customerName = null,
        public ?string $customerPhone = null,
    ) {}

    public function handle(): void
    {
        $company = Company::with(['settings', 'whatsappAccount'])->find($this->companyId);
        $chat = Chat::find($this->chatId);
        if (! $company || ! $chat || ! CommerceAgentReplyService::isEnabledForCompany($company)) {
            return;
        }

        if (! ($company->settings?->auto_reply_enabled ?? false)) {
            return;
