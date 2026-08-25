<?php

namespace App\Services\Agent\Tools;

use App\Services\Agent\AgentToolContext;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Orders\OrderPaymentDetailsService;

final class SharePaymentDetailsTool implements AgentTool
{
    public function __construct(
        protected OrderPaymentDetailsService $payments,
    ) {}

    public function name(): string
    {
        return 'share_payment_details';
    }

    public function description(): string
    {
        return 'Share real payment options and instructions for the customer\'s unpaid order '
            .'(manual till/bank text, M-Pesa, Paystack, Stripe as configured). '
            .'Use whenever the customer wants to pay, asks how to pay, or wants payment details — any wording. '
            .'Never invent payment methods. Never transfer_to_human just because payment failed or details were requested.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'order_number' => [
                    'type' => 'string',
                    'description' => 'Optional order number. Defaults to latest unpaid order for this customer.',
                ],
            ],
        ];
    }

    public function execute(AgentToolContext $context, array $arguments): array
    {
        $orderNumber = isset($arguments['order_number']) ? trim((string) $arguments['order_number']) : null;

        return $this->payments->shareForCustomer(
            $context->company,
            $context->customerPhone,
            $orderNumber !== '' ? $orderNumber : null,
        );
    }
}
