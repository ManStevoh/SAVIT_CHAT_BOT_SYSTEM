<?php

namespace App\Services\WhatsApp;

use App\Jobs\ProcessIncomingWhatsAppMessage;
use App\Models\Chat;
use App\Models\Message;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Ensures AI auto-reply actually runs for unanswered customer messages
 * without requiring a manual "Ask AI" click when the chat is in AI mode.
 */
class ChatAutoReplyService
{
    /**
     * If this chat is in AI mode and the latest customer message is unanswered, reply now.
     * Safe to call from message list polling (locked + idempotent).
     */
    public function ensureReplyIfNeeded(Chat $chat): bool
    {
        if ($chat->isAgentHandling(30)) {
            return false;
        }

        $lock = Cache::lock('chat_auto_reply:'.$chat->id, 90);
        if (! $lock->get()) {
            return false;
        }

        try {
            $chat->refresh();
            if ($chat->isAgentHandling(30)) {
                return false;
            }

            $chat->loadMissing(['company.settings', 'company.whatsappAccount']);

            return $this->replyToLatestUnansweredCustomer($chat, force: true);
        } catch (\Throwable $e) {
            Log::warning('ChatAutoReplyService: ensureReplyIfNeeded failed', [
                'chat_id' => $chat->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Run auto-reply for the latest unanswered customer message.
     *
     * @return bool True when a reply job was invoked (not necessarily that WhatsApp delivery succeeded).
     */
    public function replyToLatestUnansweredCustomer(Chat $chat, bool $force = false): bool
    {
        $settings = $chat->company?->settings;
        if ($settings && $settings->auto_reply_enabled === false) {
            return false;
        }

        $account = $chat->company?->whatsappAccount;
        if (! $account || ! $account->isActive()) {
            return false;
        }

        $lastCustomer = Message::query()
            ->where('chat_id', $chat->id)
            ->where('sender', 'customer')
            ->orderByDesc('id')
            ->first();

        if (! $lastCustomer) {
            return false;
        }

        $hasLaterHumanOrBotReply = Message::query()
            ->where('chat_id', $chat->id)
            ->whereIn('sender', ['bot', 'agent'])
            ->where('id', '>', $lastCustomer->id)
            ->exists();

        if ($hasLaterHumanOrBotReply) {
            return false;
        }

        ProcessIncomingWhatsAppMessage::dispatchSyncIncoming(
            (int) $chat->company_id,
            (int) $chat->id,
            (string) $chat->customer_phone,
            (string) $account->phone_number_id,
            (string) ($lastCustomer->content ?? ''),
            $chat->customer_name,
            $lastCustomer->whatsapp_message_id,
            (int) $lastCustomer->id,
            $force,
        );

        return true;
    }
}
