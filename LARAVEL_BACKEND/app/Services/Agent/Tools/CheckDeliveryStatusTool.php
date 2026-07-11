<?php

namespace App\Services\Agent\Tools;

use App\Models\Order;
use App\Services\Agent\AgentToolContext;
use App\Services\Agent\Contracts\AgentTool;
use App\Support\MoneyFormatter;

final class CheckDeliveryStatusTool implements AgentTool
{
    public function name(): string
    {
        return 'check_delivery_status';
    }

    public function description(): string
    {
        return 'Check order delivery/shipping status for a customer by order number or recent orders for their phone.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'order_number' => ['type' => 'string', 'description' => 'Order number to look up'],
            ],
        ];
    }

    public function execute(AgentToolContext $context, array $arguments): array
    {
        $companyId = (int) $context->company->id;
        $currency = $context->company->settings?->displayCurrencyCode() ?? 'USD';
        $orderNumber = trim((string) ($arguments['order_number'] ?? ''));

        $query = Order::query()
            ->where('company_id', $companyId)
            ->where('customer_phone', $context->customerPhone);

        if ($orderNumber !== '') {
            $query->where('order_number', $orderNumber);
        }

        $orders = $query
            ->orderByDesc('created_at')
            ->limit(3)
            ->get(['order_number', 'status', 'payment_status', 'total', 'created_at']);

        if ($orders->isEmpty()) {
            return ['orders' => [], 'message' => 'No matching orders found for this customer.'];
        }

        $delayDays = (int) config('agent.events.delivery_delay_days', 5);

        return [
            'orders' => $orders->map(fn (Order $o) => [
                'order_number' => $o->order_number,
                'status' => $o->status,
                'payment_status' => $o->payment_status,
                'total' => MoneyFormatter::format((float) $o->total, $currency),
                'placed_at' => $o->created_at?->toDateString(),
                'possibly_delayed' => in_array($o->status, ['confirmed', 'shipped'], true)
                    && $o->created_at && $o->created_at->lte(now()->subDays($delayDays)),
            ])->all(),
        ];
    }
}
