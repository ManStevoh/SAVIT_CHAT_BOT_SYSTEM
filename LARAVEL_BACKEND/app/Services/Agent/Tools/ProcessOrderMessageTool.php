<?php

namespace App\Services\Agent\Tools;

use App\Services\Agent\AgentToolContext;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\OrderFlowService;

final class ProcessOrderMessageTool implements AgentTool
{
    public function __construct(
        protected OrderFlowService $orderFlow,
    ) {}

    public function name(): string
    {
        return 'process_order_message';
    }

    public function description(): string
    {
        return 'Advance the WhatsApp order/checkout state machine. Pass the customer message verbatim when they want to order, select products, confirm, or pay.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'message' => ['type' => 'string', 'description' => 'Exact customer message for order flow'],
            ],
            'required' => ['message'],
        ];
    }

    public function execute(AgentToolContext $context, array $arguments): array
    {
        $message = trim((string) ($arguments['message'] ?? $context->incomingMessage));
        $chat = $context->chat->fresh();

        $reply = $this->orderFlow->processMessage(
            $chat,
            $context->company,
            $message,
            $context->customerName ?? '',
            $context->customerPhone,
        );

        $chat->refresh();

        return [
            'order_flow_reply' => $reply,
            'conversation_step' => $chat->conversation_step,
            'has_reply' => $reply !== null && trim($reply) !== '',
        ];
    }
}
