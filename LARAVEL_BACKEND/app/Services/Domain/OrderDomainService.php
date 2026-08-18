<?php

namespace App\Services\Domain;

use App\DTOs\ConversationState;
use App\Models\Chat;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderProduct;
use Illuminate\Support\Str;

final class OrderDomainService
{
    public function createOrderFromState(Company $company, ConversationState $state): Order
    {
        $chat = Chat::find($state->chatId);

        $orderNumber = 'ORD-'.strtoupper(Str::random(6));

        $totalAmount = 0.0;
        foreach ($state->cartItems as $item) {
            $totalAmount += ((float) ($item['price'] ?? 0.0) * (int) ($item['quantity'] ?? 1));
        }

        $order = Order::create([
            'company_id' => $company->id,
            'chat_id' => $chat?->id,
            'order_number' => $orderNumber,
            'customer_name' => $state->customerName ?? 'Customer',
            'customer_phone' => $state->customerPhone,
            'delivery_address' => $state->deliveryAddress,
            'fulfillment_type' => $state->fulfillmentType ?? 'delivery',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => $state->selectedPaymentMethod,
            'total' => $totalAmount,
            'subtotal' => $totalAmount,
        ]);

        $groupedItems = [];
        foreach ($state->cartItems as $item) {
            $prodId = $item['product_id'] ?? null;
            $varId = $item['variant_id'] ?? null;
            $name = $item['name'] ?? 'Item';
            $key = ($prodId ?? 0) . '_' . ($varId ?? 0) . '_' . mb_strtolower(trim($name));

            if (! isset($groupedItems[$key])) {
                $groupedItems[$key] = [
                    'product_id' => $prodId,
                    'product_variant_id' => $varId,
                    'name' => $name,
                    'quantity' => 0,
                    'price' => (float) ($item['price'] ?? 0.0),
                    'fulfillment_data' => $item['fulfillment_data'] ?? null,
                ];
            }
            $groupedItems[$key]['quantity'] += (int) ($item['quantity'] ?? 1);
        }

        foreach ($groupedItems as $item) {
            $unitPrice = $item['price'];
            $qty = $item['quantity'];
            OrderProduct::create([
                'order_id' => $order->id,
                'product_id' => $item['product_id'],
                'product_variant_id' => $item['product_variant_id'],
                'name' => $item['name'],
                'quantity' => $qty,
                'price' => $unitPrice,
                'line_subtotal' => $unitPrice * $qty,
                'fulfillment_data' => $item['fulfillment_data'],
            ]);
        }

        return $order;
    }
}
