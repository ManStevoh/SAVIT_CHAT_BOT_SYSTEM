<?php

namespace App\DTOs;

use App\Enums\CheckoutStep;

final readonly class ConversationState
{
    /**
     * @param array<int, array{product_id: int, variant_id: ?int, name: string, price: float, quantity: int, fulfillment_data?: mixed}> $cartItems
     */
    public function __construct(
        public int $chatId,
        public int $companyId,
        public string $customerPhone,
        public ?string $customerName,
        public CheckoutStep $step,
        public array $cartItems = [],
        public ?string $deliveryAddress = null,
        public string $fulfillmentType = 'delivery',
        public ?int $pendingOrderId = null,
        public ?string $selectedPaymentMethod = null,
        public ?string $scheduledFor = null,
        public array $pendingDraftData = [],
        public int $version = 1,
    ) {}

    public function with(array $changes): self
    {
        return new self(
            chatId: $changes['chatId'] ?? $this->chatId,
            companyId: $changes['companyId'] ?? $this->companyId,
            customerPhone: $changes['customerPhone'] ?? $this->customerPhone,
            customerName: array_key_exists('customerName', $changes) ? $changes['customerName'] : $this->customerName,
            step: $changes['step'] ?? $this->step,
            cartItems: $changes['cartItems'] ?? $this->cartItems,
            deliveryAddress: array_key_exists('deliveryAddress', $changes) ? $changes['deliveryAddress'] : $this->deliveryAddress,
            fulfillmentType: $changes['fulfillmentType'] ?? $this->fulfillmentType,
            pendingOrderId: array_key_exists('pendingOrderId', $changes) ? $changes['pendingOrderId'] : $this->pendingOrderId,
            selectedPaymentMethod: array_key_exists('selectedPaymentMethod', $changes) ? $changes['selectedPaymentMethod'] : $this->selectedPaymentMethod,
            scheduledFor: array_key_exists('scheduledFor', $changes) ? $changes['scheduledFor'] : $this->scheduledFor,
            pendingDraftData: $changes['pendingDraftData'] ?? $this->pendingDraftData,
            version: $this->version + 1,
        );
    }

    public function hasItems(): bool
    {
        return ! empty($this->cartItems);
    }

    public function calculateCartTotal(): float
    {
        $total = 0.0;
        foreach ($this->cartItems as $item) {
            $price = (float) ($item['price'] ?? 0.0);
            $qty = (int) ($item['quantity'] ?? 1);
            $total += ($price * $qty);
        }

        return $total;
    }
}
