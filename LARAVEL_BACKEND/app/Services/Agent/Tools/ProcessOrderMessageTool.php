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
        return 'Advance checkout when the customer intends to buy, add items, confirm an order, set address, or pay — any natural phrasing. Pass their message verbatim into the order flow.';
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

        if ($reply === null || trim($reply) === '') {
            return [
                'order_flow_reply' => null,
                'conversation_step' => $chat->conversation_step,
                'has_reply' => false,
                'error' => 'order_flow_no_progress',
                'message' => 'Checkout did not advance on that message. '
                    .'If the customer is confirming a purchase that is not in an active checkout step, call process_order_message again with a concrete line such as "order" or "10 x Headphones" (product name from the conversation), then share_payment_details. '
                    .'Do not transfer_to_human for this.',
            ];
        }

        return [
            'order_flow_reply' => $reply,
            'conversation_step' => $chat->conversation_step,
            'has_reply' => true,
        ];
    }
}
