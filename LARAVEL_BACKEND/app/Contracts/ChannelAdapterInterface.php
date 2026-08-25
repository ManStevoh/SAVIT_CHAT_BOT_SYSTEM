<?php

namespace App\Contracts;

use App\DTOs\InboundEnvelope;
use App\DTOs\OutboundMessage;

interface ChannelAdapterInterface
{
    public function channelName(): string;

    public function normalizeInbound(mixed $payload, int $companyId): InboundEnvelope;

    public function sendOutbound(OutboundMessage $message): bool;
}
