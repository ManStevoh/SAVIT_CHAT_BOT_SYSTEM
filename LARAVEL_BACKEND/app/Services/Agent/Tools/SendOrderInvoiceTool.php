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
        return 'Generate an invoice/receipt PDF for the customer\'s order and send it on WhatsApp with payment status and a view/pay link. '
            .'Use this when the customer asks for an invoice, receipt, bill, or payment document. '
            .'Do NOT transfer_to_human just to send an invoice — use this tool first. '
            .'Requires an existing order; if none exists, ask them to complete ordering via process_order_message first.';
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
