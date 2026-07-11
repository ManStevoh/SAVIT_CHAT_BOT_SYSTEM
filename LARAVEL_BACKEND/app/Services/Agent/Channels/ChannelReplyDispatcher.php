<?php

namespace App\Services\Agent\Channels;

use App\Jobs\ProcessIncomingChannelMessage;
use App\Models\Company;
use App\Models\Message;

/**
 * Ingest + optional synchronous agent reply (web widget, webhooks).
 */
final class ChannelReplyDispatcher
{
    public function __construct(
        protected MultiChannelIngestService $ingest,
    ) {}

    /**
     * @return array{chatId: int, messageId: int, reply: ?string, queued: bool}
     */
    public function ingestAndReply(
        Company $company,
        string $channel,
        string $channelUserId,
        string $messageText,
        ?string $customerName = null,
        ?string $customerEmail = null,
        bool $syncReply = false,
    ): array {
        $result = $this->ingest->ingest(
            $company,
            $channel,
            $channelUserId,
            $messageText,
            $customerName,
            $customerEmail,
        );

        $reply = null;
        if ($result['queued'] && $syncReply) {
            ProcessIncomingChannelMessage::dispatchSync(
                $company->id,
                $result['chat']->id,
                $result['message']->id,
                $messageText,
                $customerName,
                $result['chat']->customer_phone,
            );
            $reply = Message::where('chat_id', $result['chat']->id)
                ->where('sender', 'bot')
                ->orderByDesc('id')
                ->value('content');
        }

        return [
            'chatId' => $result['chat']->id,
            'messageId' => $result['message']->id,
            'reply' => $reply,
            'queued' => $result['queued'],
        ];
    }
}
