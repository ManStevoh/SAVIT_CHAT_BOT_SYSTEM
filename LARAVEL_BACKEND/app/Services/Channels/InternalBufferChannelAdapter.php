<?php

namespace App\Services\Channels;

use App\Contracts\ChannelAdapterInterface;
use App\DTOs\InboundEnvelope;
use App\DTOs\OutboundMessage;

final class InternalBufferChannelAdapter implements ChannelAdapterInterface
{
    /** @var list<OutboundMessage> */
    public array $sentMessages = [];

    public function channelName(): string
    {
        return 'internal';
    }

    public function normalizeInbound(mixed $payload, int $companyId): InboundEnvelope
    {
        if ($payload instanceof InboundEnvelope) {
            return $payload;
        }

        if (is_array($payload)) {
            return new InboundEnvelope(
                channelType: 'internal',
                externalSenderId: (string) ($payload['customer_phone'] ?? $payload['from'] ?? ''),
                companyId: $companyId,
                messageText: (string) ($payload['message_text'] ?? $payload['text'] ?? ''),
                senderName: isset($payload['customer_name']) ? (string) $payload['customer_name'] : null,
                metadata: $payload,
                whatsappMessageId: isset($payload['whatsapp_message_id']) ? (string) $payload['whatsapp_message_id'] : null
            );
        }

        return new InboundEnvelope(
            channelType: 'internal',
            externalSenderId: '',
            companyId: $companyId,
            messageText: (string) $payload
        );
    }

    public function sendOutbound(OutboundMessage $message): bool
    {
        $this->sentMessages[] = $message;

        return true;
    }

    public function getLastMessageContent(): ?string
    {
        if (empty($this->sentMessages)) {
            return null;
        }

        $last = end($this->sentMessages);

        return $last->content;
    }
}
