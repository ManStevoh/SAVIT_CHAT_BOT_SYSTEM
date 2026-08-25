<?php

namespace App\Services\Workflow;

use App\DTOs\ConversationState;
use LogicException;

final class StatePreservationGuard
{
    public static function hash(ConversationState $state): string
    {
        return md5(json_encode([
            'step' => $state->step->value,
            'cartItems' => $state->cartItems,
            'pendingOrderId' => $state->pendingOrderId,
            'deliveryAddress' => $state->deliveryAddress,
            'customerPhone' => $state->customerPhone,
            'pendingDraftData' => $state->pendingDraftData,
        ]));
    }

    public static function assertUnchanged(string $beforeHash, ConversationState $afterState): void
    {
        $afterHash = self::hash($afterState);
        if ($beforeHash !== $afterHash) {
            throw new LogicException('SECURITY VIOLATION: State mutation attempted during Read-Only pipeline execution!');
        }
    }
}
