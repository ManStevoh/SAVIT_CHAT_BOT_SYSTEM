<?php

namespace App\Services\Domain;

use App\DTOs\ConversationState;
use App\Models\Chat;
use App\Models\Company;
use App\Models\Order;
use App\Services\Conversation\ConversationGreetingService;
use App\Support\MoneyFormatter;
use Illuminate\Support\Collection;

final class OrderTrackingService
{
    public function __construct(
        private ConversationGreetingService $greetingService
    ) {}

    /**
     * Get recent orders (last 30 days) for a customer by phone number or chat ID.
     *
     * @return Collection<int, Order>
     */
    public function getRecentOrders(Company $company, string $customerPhone, ?int $chatId = null): Collection
    {
        $cleanPhone = preg_replace('/\D+/', '', $customerPhone);

        return Order::where('company_id', $company->id)
            ->where(function ($q) use ($chatId, $customerPhone, $cleanPhone) {
                if ($chatId) {
                    $q->where('chat_id', $chatId);
                }
                if ($cleanPhone !== '') {
                    $q->orWhere('customer_phone', $customerPhone)
                      ->orWhere('customer_phone', 'like', '%' . mb_substr($cleanPhone, -9) . '%');
                }
            })
            ->with(['orderProducts'])
            ->orderByDesc('id')
            ->limit(5)
            ->get();
    }

    /**
     * Get the single active pending unpaid order for a customer if one exists.
     */
    public function getPendingUnpaidOrder(Company $company, string $customerPhone, ?int $chatId = null): ?Order
    {
        $cleanPhone = preg_replace('/\D+/', '', $customerPhone);

        return Order::where('company_id', $company->id)
            ->where(function ($q) use ($chatId, $customerPhone, $cleanPhone) {
                if ($chatId) {
                    $q->where('chat_id', $chatId);
                }
                if ($cleanPhone !== '') {
                    $q->orWhere('customer_phone', $customerPhone)
                      ->orWhere('customer_phone', 'like', '%' . mb_substr($cleanPhone, -9) . '%');
                }
            })
            ->where('status', '!=', 'cancelled')
            ->whereIn('payment_status', ['unpaid', 'pending'])
            ->with(['orderProducts'])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Look up a specific order by order number or ID.
     */
    public function findOrderByNumber(Company $company, string $rawQuery): ?Order
    {
        $cleaned = trim($rawQuery);
        $cleaned = ltrim($cleaned, '#');
        $cleaned = preg_replace('/^(?:order|track|ord)\s*#?/iu', '', $cleaned);
        $cleaned = trim($cleaned);

        if ($cleaned === '') {
            return null;
        }

        // 1. Direct match on order_number
        $order = Order::where('company_id', $company->id)
            ->where(function ($q) use ($cleaned) {
                $q->where('order_number', $cleaned)
                  ->orWhere('order_number', 'like', '%' . $cleaned . '%')
                  ->orWhere('order_number', 'ORD-' . strtoupper($cleaned));
            })
            ->with(['orderProducts'])
            ->first();

        if ($order) {
            return $order;
        }

        // 2. Direct ID match if numeric
        if (is_numeric($cleaned)) {
            return Order::where('company_id', $company->id)
                ->where('id', (int) $cleaned)
                ->with(['orderProducts'])
                ->first();
        }

        return null;
    }

    /**
     * Formats a single order into a rich WhatsApp tracking card.
     */
    public function formatOrderTrackingCard(Company $company, Order $order, ?string $customerPhone = null): string
    {
        $statusLabels = [
            'pending' => '⏳ Pending Confirmation',
            'confirmed' => '📋 Order Confirmed & Preparing',
            'processing' => '📦 Processing in Warehouse',
            'shipped' => '🚚 Out for Delivery / Shipped',
            'delivered' => '✅ Delivered',
            'completed' => '✅ Order Completed',
            'cancelled' => '❌ Cancelled',
        ];

        $paymentLabels = [
            'paid' => '💳 Paid',
            'unpaid' => '⏳ Payment Pending',
            'pending' => '⏳ Payment Pending',
            'refunded' => '↩️ Refunded',
            'failed' => '⚠️ Payment Failed',
        ];

        $statusText = $statusLabels[$order->status] ?? ('📦 ' . ucfirst($order->status));
        $paymentText = $paymentLabels[$order->payment_status] ?? ('💳 ' . ucfirst($order->payment_status));
        if ($order->payment_method) {
            $paymentText .= ' (' . strtoupper($order->payment_method) . ')';
        }

        $formattedTotal = MoneyFormatter::format((float) $order->total, $company->currency ?? 'USD');

        $lines = [
            "📦 *Order Tracker — Order #{$order->order_number}*",
            "━━━━━━━━━━━━━━━━━━━━━━",
            "STATUS: *{$statusText}*",
            "PAYMENT: *{$paymentText}*",
            "",
            "📋 *Order Items:*",
        ];

        $order->loadMissing('orderProducts');
        $groupedItems = [];
        foreach ($order->orderProducts as $item) {
            $key = ($item->product_id ?? 0) . '_' . ($item->product_variant_id ?? 0) . '_' . mb_strtolower(trim($item->name));
            if (! isset($groupedItems[$key])) {
                $groupedItems[$key] = [
                    'name' => $item->name,
                    'quantity' => 0,
                    'price' => (float) $item->price,
                ];
            }
            $groupedItems[$key]['quantity'] += (int) $item->quantity;
        }

        foreach ($groupedItems as $g) {
            $qty = $g['quantity'];
            $unitPrice = MoneyFormatter::format($g['price'], $company->currency ?? 'USD');
            $lines[] = "• {$qty}x *{$g['name']}* ({$unitPrice})";
        }

        $lines[] = "Total: *{$formattedTotal}*";

        if ($order->delivery_address) {
            $lines[] = "";
            $lines[] = "📍 *Fulfillment Address:* {$order->delivery_address}";
        }

        if ($order->created_at) {
            $lines[] = "📅 *Placed On:* " . $order->created_at->format('M j, Y \a\t H:i');
        }

        $phone = $customerPhone ?? $order->customer_phone;
        $chat = $order->chat;
        $webUrl = $this->greetingService->publicStorefrontUrl($company, $chat, $phone);

        if ($webUrl) {
            $orderWebUrl = str_contains($webUrl, '?')
                ? str_replace('?', "/order/{$order->id}?", $webUrl)
                : $webUrl . "/order/{$order->id}";
            $lines[] = "";
            $lines[] = "🔗 *Live Web Receipt & Progress:*";
            $lines[] = $orderWebUrl;
        }

        $isUnpaid = in_array($order->payment_status, ['unpaid', 'pending'], true) && $order->status !== 'cancelled';

        $lines[] = "━━━━━━━━━━━━━━━━━━━━━━";

        if ($isUnpaid) {
            $lines[] = "⚡ *Actions for Order #{$order->order_number}:*";
            $lines[] = "1️⃣ - *Pay Now* (Complete Payment)";
            $lines[] = "2️⃣ - *Cancel Order*";
            $lines[] = "3️⃣ - *Change Delivery Address*";
            $lines[] = "4️⃣ - *Talk to Agent*";
            $lines[] = "";
            $lines[] = "Reply with a number (e.g. *1*) to manage this order!";
        } else {
            $lines[] = "Reply 'prices' to browse products, or reply '3' to speak to an agent!";
        }

        return implode("\n", $lines);
    }

    /**
     * Formats a list of recent orders when the customer has 2+ active orders.
     */
    public function formatOrderList(Company $company, Collection $orders): string
    {
        $lines = [
            "📋 *Your Recent Orders:*",
            "",
        ];

        foreach ($orders as $idx => $order) {
            $num = $idx + 1;
            $total = MoneyFormatter::format((float) $order->total, $company->currency ?? 'USD');
            $status = ucfirst($order->status);
            $payStatus = ucfirst($order->payment_status);
            $lines[] = "{$num}. *Order #{$order->order_number}* — {$total} ({$status} | {$payStatus})";
        }

        $lines[] = "";
        $lines[] = "Reply with the *Order Number* (e.g. *#{$orders->first()->order_number}*) for full tracking & receipt details!";

        return implode("\n", $lines);
    }

    /**
     * Get verified order facts formatted specifically for injection into LLM system prompts.
     */
    public function getLlmOrderContext(Company $company, string $customerPhone, ?int $chatId = null, ?string $queriedOrderNum = null): array
    {
        if ($queriedOrderNum) {
            $specificOrder = $this->findOrderByNumber($company, $queriedOrderNum);
            if ($specificOrder) {
                return [$this->serializeOrderForLlm($company, $specificOrder)];
            }
        }

        $orders = $this->getRecentOrders($company, $customerPhone, $chatId);
        $result = [];
        foreach ($orders as $o) {
            $result[] = $this->serializeOrderForLlm($company, $o);
        }

        return $result;
    }

    private function serializeOrderForLlm(Company $company, Order $order): array
    {
        $order->loadMissing('orderProducts');
        $items = [];
        foreach ($order->orderProducts as $item) {
            $items[] = "{$item->quantity}x {$item->name} (" . MoneyFormatter::format((float) $item->price, $company->currency ?? 'USD') . ")";
        }

        return [
            'order_number' => (string) $order->order_number,
            'status' => (string) $order->status,
            'payment_status' => (string) $order->payment_status,
            'payment_method' => (string) ($order->payment_method ?? 'N/A'),
            'total' => MoneyFormatter::format((float) $order->total, $company->currency ?? 'USD'),
            'items' => $items,
            'delivery_address' => (string) ($order->delivery_address ?? 'N/A'),
            'created_at' => $order->created_at?->toIso8601String() ?? '',
            'tracking_url' => url("/s/" . ($company->store_slug ?: 'store-' . $company->id) . "/order/{$order->id}"),
        ];
    }
}
