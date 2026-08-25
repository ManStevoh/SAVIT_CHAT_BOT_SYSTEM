<?php

namespace App\DTOs;

final readonly class OutboundMessage
{
    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public string $channelType,
        public string $recipientId,
        public int $companyId,
        public string $content,
        public ?string $responseSpec = null,
        public array $extra = [],
        public ?string $ctaUrl = null,
        public ?string $ctaButtonText = null,
    ) {}
}
