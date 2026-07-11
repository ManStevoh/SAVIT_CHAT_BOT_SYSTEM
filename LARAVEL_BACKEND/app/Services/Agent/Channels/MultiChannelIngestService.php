<?php

namespace App\Services\Agent\Channels;

use App\Jobs\ProcessIncomingChannelMessage;
use App\Models\Chat;
use App\Models\Company;
use App\Models\Message;
use Illuminate\Support\Str;

/**
 * Ingest messages from non-WhatsApp channels into the unified Chat + agent brain.
 */
final class MultiChannelIngestService
{
    /**
     * @return array{chat: Chat, message: Message, queued: bool}
     */
    public function ingest(
        Company $company,
        string $channel,
        string $channelUserId,
        string $messageText,
        ?string $customerName = null,
        ?string $customerEmail = null,
    ): array {
        if (! in_array($channel, ChatChannel::all(), true)) {
            throw new \InvalidArgumentException('Unsupported channel: '.$channel);
        }

        $company->loadMissing('settings');
        $phone = $this->resolvePhone($channel, $channelUserId, $customerEmail);
        $name = $customerName ?: $this->defaultName($channel, $channelUserId);

        $chat = Chat::query()
            ->where('company_id', $company->id)
            ->where('channel', $channel)
            ->where('channel_user_id', $channelUserId)
            ->first();

        if (! $chat) {
            $chat = Chat::create([
                'company_id' => $company->id,
                'channel' => $channel,
                'channel_user_id' => $channelUserId,
                'customer_name' => $name,
                'customer_phone' => $phone,
                'status' => 'active',
                'last_message' => mb_substr($messageText, 0, 500),
                'last_message_at' => now(),
                'unread_count' => 1,
            ]);
        } else {
            $chat->update([
                'customer_name' => $name,
                'last_message' => mb_substr($messageText, 0, 500),
                'last_message_at' => now(),
                'unread_count' => (int) $chat->unread_count + 1,
            ]);
        }

        $message = Message::create([
            'chat_id' => $chat->id,
            'sender' => 'customer',
            'content' => $messageText,
            'message_type' => 'text',
        ]);

        $queued = false;
        if ($company->settings?->auto_reply_enabled && $company->settings?->agent_commerce_enabled) {
            ProcessIncomingChannelMessage::dispatch(
                $company->id,
                $chat->id,
                $message->id,
                $messageText,
                $name,
                $phone,
            );
            $queued = true;
        }

        return ['chat' => $chat->fresh(), 'message' => $message, 'queued' => $queued];
    }

    private function resolvePhone(string $channel, string $channelUserId, ?string $email): string
    {
        if ($this->looksLikePhone($channelUserId)) {
            return preg_replace('/\D+/', '', $channelUserId) ?: $channelUserId;
        }

        if ($email && $this->looksLikePhone($email)) {
            return preg_replace('/\D+/', '', $email) ?: $email;
        }

        return $channel.':'.Str::slug($channelUserId);
    }

    private function defaultName(string $channel, string $channelUserId): string
    {
        return match ($channel) {
            ChatChannel::WEB_WIDGET => 'Web visitor',
            ChatChannel::EMAIL => 'Email contact',
            ChatChannel::INSTAGRAM_DM => 'Instagram user',
            default => 'Customer',
        };
    }

    private function looksLikePhone(string $value): bool
    {
        $digits = preg_replace('/\D+/', '', $value) ?: '';

        return strlen($digits) >= 8 && strlen($digits) <= 15;
    }
}
