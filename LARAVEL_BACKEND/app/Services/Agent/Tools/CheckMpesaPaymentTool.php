<?php

namespace App\Services\Agent\Tools;

use App\Models\Order;
use App\Services\Agent\AgentToolContext;
use App\Services\Agent\Contracts\AgentTool;
use App\Support\MoneyFormatter;

final class CheckMpesaPaymentTool implements AgentTool
{
    public function name(): string
    {
        return 'check_mpesa_payment';
    }

    public function description(): string
    {
        return 'Check M-Pesa payment status for a customer order by order number.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'order_number' => ['type' => 'string', 'description' => 'Order number to check'],
            ],
            'required' => ['order_number'],
        ];
    }

    public function execute(AgentToolContext $context, array $arguments): array
    {
        if (! config('agent.external.mpesa_tool_enabled', true)) {
            return ['enabled' => false, 'message' => 'M-Pesa payment lookup is disabled.'];
        }

        $orderNumber = trim((string) ($arguments['order_number'] ?? ''));
        if ($orderNumber === '') {
            return ['found' => false, 'message' => 'Order number required.'];
        }

        $order = Order::query()
            ->where('company_id', $context->company->id)
            ->where('order_number', $orderNumber)
            ->first(['order_number', 'payment_status', 'status', 'total', 'customer_phone', 'created_at']);

        if (! $order) {
            return ['found' => false, 'message' => 'Order not found.'];
        }

        $settings = $context->company->settings;
        $currency = $settings?->displayCurrencyCode() ?? 'KES';
        $mpesaEnabled = (bool) ($settings?->orders_accept_mpesa ?? false);

        return [
            'found' => true,
            'order_number' => $order->order_number,
            'payment_status' => $order->payment_status,
            'order_status' => $order->status,
            'total' => MoneyFormatter::format((float) $order->total, $currency),
            'mpesa_accepted' => $mpesaEnabled,
            'paid' => $order->payment_status === 'paid',
            'placed_at' => $order->created_at?->toDateTimeString(),
            'note' => $order->payment_status === 'paid'
                ? 'Payment confirmed in system.'
                : ($mpesaEnabled ? 'Payment pending — customer can complete M-Pesa STK push via order flow.' : 'M-Pesa not enabled for this business.'),
        ];
    }
}
