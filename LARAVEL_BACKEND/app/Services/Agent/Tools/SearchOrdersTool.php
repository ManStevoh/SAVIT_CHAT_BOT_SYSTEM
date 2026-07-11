<?php

namespace App\Services\Agent\Tools;

use App\Models\Order;
use App\Services\Agent\AgentToolContext;
use App\Services\Agent\Contracts\AgentTool;
use App\Support\MoneyFormatter;

final class SearchOrdersTool implements AgentTool
{
    public function name(): string
    {
        return 'search_orders';
    }

    public function description(): string
    {
        return 'Look up orders for the current customer by order number or list recent orders.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'order_number' => ['type' => 'string', 'description' => 'Optional order number to find'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10],
            ],
        ];
    }

    public function execute(AgentToolContext $context, array $arguments): array
    {
        $companyId = (int) $context->company->id;
        $phone = $context->customerPhone;
        $currency = $context->company->settings?->displayCurrencyCode() ?? 'USD';
        $orderNumber = trim((string) ($arguments['order_number'] ?? ''));
        $limit = max(1, min(10, (int) ($arguments['limit'] ?? 5)));

        $query = Order::query()
            ->where('company_id', $companyId)
            ->where('customer_phone', $phone)
            ->with(['orderProducts:id,order_id,name,quantity,price']);

        if ($orderNumber !== '') {
            $query->where('order_number', 'like', '%'.$orderNumber.'%');
        }

        $orders = $query->orderByDesc('created_at')->limit($limit)->get([
            'id', 'order_number', 'status', 'payment_status', 'total', 'created_at',
        ]);

        return [
            'orders' => $orders->map(fn (Order $o) => [
                'order_number' => $o->order_number,
                'status' => $o->status,
                'payment_status' => $o->payment_status,
                'total' => MoneyFormatter::format((float) $o->total, $currency),
                'date' => $o->created_at?->toDateString(),
                'items' => $o->orderProducts->map(fn ($i) => "{$i->quantity}x {$i->name}")->all(),
            ])->all(),
        ];
    }
}
