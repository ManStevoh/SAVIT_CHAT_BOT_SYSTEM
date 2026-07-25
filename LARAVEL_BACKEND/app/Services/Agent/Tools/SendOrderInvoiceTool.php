<?php

namespace App\Services\Agent\Tools;

use App\Services\Agent\AgentToolContext;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Orders\OrderInvoiceService;

final class SendOrderInvoiceTool implements AgentTool
{
    public function __construct(
        protected OrderInvoiceService $invoices,
    ) {}

    public function name(): string
    {
        return 'send_order_invoice';
    }

    public function description(): string
    {
        return 'Fulfill a customer request to receive their order invoice, receipt, bill, or payment document: generate a PDF and send it on WhatsApp with status and a view/pay link. '
            .'Use whenever intent is to get / see / receive / download / share that document — any wording. '
            .'Do not transfer_to_human just for this. Requires an existing order; if none, guide them through ordering (process_order_message) first.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'order_number' => [
                    'type' => 'string',
                    'description' => 'Optional order number. If omitted, uses the customer\'s latest order.',
                ],
                'caption' => [
                    'type' => 'string',
                    'description' => 'Optional WhatsApp caption shown with the PDF.',
                ],
            ],
        ];
    }

    public function execute(AgentToolContext $context, array $arguments): array
    {
        $orderNumber = isset($arguments['order_number']) ? trim((string) $arguments['order_number']) : null;
        $caption = isset($arguments['caption']) ? trim((string) $arguments['caption']) : null;

        return $this->invoices->sendInvoiceToCustomer(
            $context->company,
            $context->chat,
            $context->customerPhone,
            $orderNumber !== '' ? $orderNumber : null,
            $caption !== '' ? $caption : null,
        );
    }
}
