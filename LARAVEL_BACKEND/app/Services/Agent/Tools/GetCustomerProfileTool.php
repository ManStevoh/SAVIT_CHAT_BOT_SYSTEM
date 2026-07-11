<?php

namespace App\Services\Agent\Tools;

use App\Models\Order;
use App\Services\Agent\AgentToolContext;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\CustomerMemoryService;
use App\Support\MoneyFormatter;

final class GetCustomerProfileTool implements AgentTool
{
    public function __construct(
        protected CustomerMemoryService $customerMemory,
    ) {}

    public function name(): string
    {
        return 'get_customer_profile';
    }

    public function description(): string
    {
        return 'Get persistent customer profile, preferences, and recent order summary for the current WhatsApp customer.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => (object) [],
        ];
    }

    public function execute(AgentToolContext $context, array $arguments): array
    {
        $companyId = (int) $context->company->id;
        $phone = $context->customerPhone;
        $currency = $context->company->settings?->displayCurrencyCode() ?? 'USD';

        $memories = $this->customerMemory->list($companyId, $phone);

        $recentOrders = Order::query()
            ->where('company_id', $companyId)
            ->where('customer_phone', $phone)
            ->with(['orderProducts:id,order_id,name,quantity,price'])
            ->orderByDesc('created_at')
            ->limit(3)
            ->get(['id', 'order_number', 'status', 'total', 'created_at']);

        return [
            'name' => $context->customerName,
            'phone' => $phone,
            'memories' => $memories,
            'recent_orders' => $recentOrders->map(fn (Order $o) => [
                'order_number' => $o->order_number,
                'status' => $o->status,
                'total' => MoneyFormatter::format((float) $o->total, $currency),
                'date' => $o->created_at?->toDateString(),
                'items' => $o->orderProducts->map(fn ($i) => "{$i->quantity}x {$i->name}")->take(5)->all(),
            ])->all(),
            'chat_language' => $context->chat->detected_language,
        ];
    }
}
