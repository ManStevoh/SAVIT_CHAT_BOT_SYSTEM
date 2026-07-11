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
