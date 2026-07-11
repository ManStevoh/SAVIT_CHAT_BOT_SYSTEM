<?php

namespace App\Services\Agent\Tools;

use App\Models\Order;
use App\Services\Agent\AgentToolContext;
use App\Services\Agent\Contracts\AgentTool;
use App\Support\MoneyFormatter;

final class IssueOrderRefundTool implements AgentTool
{
    public function name(): string
    {
        return 'issue_order_refund';
    }

    public function description(): string
    {
        return 'Issue a refund for a paid order by order number. Requires owner approval before execution.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'order_number' => ['type' => 'string', 'description' => 'Order number to refund'],
                'reason' => ['type' => 'string', 'description' => 'Refund reason for audit'],
            ],
            'required' => ['order_number'],
        ];
    }

    public function execute(AgentToolContext $context, array $arguments): array
    {
        $orderNumber = trim((string) ($arguments['order_number'] ?? ''));
        if ($orderNumber === '') {
            return ['found' => false, 'message' => 'Order number required.'];
        }

        $order = Order::where('company_id', $context->company->id)
            ->where('order_number', $orderNumber)
            ->first();

        if (! $order) {
            return ['found' => false, 'message' => 'Order not found.'];
        }

        $currency = $context->company->settings?->displayCurrencyCode() ?? 'KES';

        return [
            'found' => true,
            'order_number' => $order->order_number,
            'payment_status' => $order->payment_status,
            'total' => MoneyFormatter::format((float) $order->total, $currency),
            'reason' => trim((string) ($arguments['reason'] ?? '')),
            'note' => 'Queued for owner approval — refund executes after approve.',
        ];
    }
}
