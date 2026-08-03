<?php

namespace App\Services\Agent\Tools;

use App\Services\Agent\AgentToolContext;
use App\Services\Agent\CheckoutMessageComposer;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\OrderFlowService;

final class ProcessOrderMessageTool implements AgentTool
{
    public function __construct(
        protected OrderFlowService $orderFlow,
        protected CheckoutMessageComposer $composer,
    ) {}

    public function name(): string
    {
        return 'process_order_message';
    }

    public function description(): string
    {
        return 'Advance checkout whenever the customer specifies a product number (e.g. 1, 2, 3), item name, quantity (e.g. "2 earphones"), address, payment, or confirm. '
            .'Pass their message, OR a normalized checkout command (e.g. "1", "2 earphones", "done", "confirm").';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'message' => [
                    'type' => 'string',
                    'description' => 'Customer message or a normalized checkout command synthesized from the conversation (qty x product, done, confirm, address, payment choice)',
                ],
            ],
            'required' => ['message'],
        ];
    }

    public function execute(AgentToolContext $context, array $arguments): array
    {
        $message = trim((string) ($arguments['message'] ?? $context->incomingMessage));
        $tried = [];
        $lastReply = null;

        $lastReply = $this->runOrderFlow($context, $message, $tried);

        if ($lastReply === null || trim($lastReply) === '') {
            foreach ($this->composer->candidateMessages($context, $message) as $candidate) {
                $context->chat->refresh();
                $lastReply = $this->runOrderFlow($context, $candidate, $tried);
                if ($lastReply !== null && trim($lastReply) !== '') {
                    break;
                }
            }
        }

        // After a line was added (or cart already has items), buy/pay intent should finish checkout.
        $lower = mb_strtolower(trim($message));
        if ($this->composer->looksLikePayIntent($lower) || $this->composer->looksLikeAffirm($lower)) {
            $lastReply = $this->autoAdvanceToPlacedOrder($context, $tried, $lastReply);
        }

        $context->chat->refresh();

        if ($lastReply === null || trim($lastReply) === '') {
            return [
                'order_flow_reply' => null,
                'conversation_step' => $context->chat->conversation_step,
                'has_reply' => false,
                'tried_messages' => $tried,
                'error' => 'order_flow_no_progress',
                'message' => 'Checkout did not advance. '
                    .'YOU must call process_order_message again with a synthesized command from the thread '
                    .'(example shape: "{qty} x {ExactProductNameFromCatalog}", then "done", then "confirm") — '
                    .'do NOT ask the customer to type that phrase. Then call share_payment_details if unpaid. '
                    .'Do not transfer_to_human for this.',
            ];
        }

        $payUrl = null;
        if (preg_match('~(https?://[^\s]+(?:/pay/|/invoice/|/receipt|/orders/receipt|pesapaliframe)[^\s]*)~i', (string) $lastReply, $m)) {
            $payUrl = trim($m[1], "().,;[]");
        }

        return [
            'order_flow_reply' => $lastReply,
            'conversation_step' => $context->chat->conversation_step,
            'has_reply' => true,
            'tried_messages' => $tried,
            'pay_url' => $payUrl,
        ];
    }

    /**
     * @param  list<string>  $tried
     */
    private function runOrderFlow(AgentToolContext $context, string $message, array &$tried): ?string
    {
        $message = trim($message);
        if ($message === '') {
            return null;
        }
        $tried[] = $message;
        $chat = $context->chat->fresh();

        return $this->orderFlow->processMessage(
            $chat,
            $context->company,
            $message,
            $context->customerName ?? '',
            $context->customerPhone,
        );
    }

    /**
     * @param  list<string>  $tried
     */
    private function autoAdvanceToPlacedOrder(AgentToolContext $context, array &$tried, ?string $lastReply): ?string
    {
        for ($i = 0; $i < 3; $i++) {
            $context->chat->refresh();
            $step = $context->chat->conversation_step;
            $draft = is_array($context->chat->order_draft) ? $context->chat->order_draft : [];
            $hasItems = ! empty($draft['items']);

            if ($step === OrderFlowService::STEP_PRODUCT && $hasItems) {
                $reply = $this->runOrderFlow($context, 'done', $tried);
                if ($reply !== null && trim($reply) !== '') {
                    $lastReply = $reply;
                }
                continue;
            }

            if ($step === OrderFlowService::STEP_CONFIRM) {
                $reply = $this->runOrderFlow($context, 'confirm', $tried);
                if ($reply !== null && trim($reply) !== '') {
                    $lastReply = $reply;
                }
                break;
            }

            break;
        }

        return $lastReply;
    }
}
