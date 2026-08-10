<?php

namespace App\DTOs;

final readonly class InboundEnvelope
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $channelType,
        public string $externalSenderId,
        public int $companyId,
        public string $messageText,
        public ?string $senderName = null,
        public array $metadata = [],
        public ?string $whatsappMessageId = null,
    ) {}
}
