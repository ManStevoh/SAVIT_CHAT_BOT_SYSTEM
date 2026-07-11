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
