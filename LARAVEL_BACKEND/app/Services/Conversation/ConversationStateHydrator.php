<?php

namespace App\Services\Conversation;

use App\DTOs\ConversationState;
use App\Enums\CheckoutStep;
use App\Models\Chat;

final class ConversationStateHydrator
{
    public function hydrateFromChat(Chat $chat): ConversationState
    {
        $draft = is_array($chat->order_draft) ? $chat->order_draft : [];
        $step = CheckoutStep::fromLegacyStep($chat->conversation_step);

        $cartItems = is_array($draft['items'] ?? null) ? $draft['items'] : [];
        $deliveryAddress = isset($draft['delivery_address']) && is_string($draft['delivery_address']) ? $draft['delivery_address'] : null;
        $fulfillmentType = isset($draft['fulfillment_type']) && is_string($draft['fulfillment_type']) ? $draft['fulfillment_type'] : 'delivery';
        $pendingOrderId = isset($draft['order_id']) ? (int) $draft['order_id'] : null;
        $selectedPaymentMethod = isset($draft['payment_method']) && is_string($draft['payment_method']) ? $draft['payment_method'] : null;
        $scheduledFor = isset($draft['scheduled_for']) && is_string($draft['scheduled_for']) ? $draft['scheduled_for'] : null;

        return new ConversationState(
            chatId: (int) $chat->id,
            companyId: (int) $chat->company_id,
            customerPhone: (string) $chat->customer_phone,
            customerName: $chat->customer_name,
            step: $step,
            cartItems: $cartItems,
            deliveryAddress: $deliveryAddress,
            fulfillmentType: $fulfillmentType,
            pendingOrderId: $pendingOrderId,
            selectedPaymentMethod: $selectedPaymentMethod,
            scheduledFor: $scheduledFor,
            pendingDraftData: $draft,
            version: 1,
        );
    }

    public function dehydrateToChat(ConversationState $state, Chat $chat): void
    {
        $draft = $state->pendingDraftData;
        $draft['items'] = $state->cartItems;
        $draft['fulfillment_type'] = $state->fulfillmentType;

        if ($state->deliveryAddress !== null) {
            $draft['delivery_address'] = $state->deliveryAddress;
        } else {
            unset($draft['delivery_address']);
        }

        if ($state->pendingOrderId !== null) {
            $draft['order_id'] = $state->pendingOrderId;
        } else {
            unset($draft['order_id']);
        }

        if ($state->selectedPaymentMethod !== null) {
            $draft['payment_method'] = $state->selectedPaymentMethod;
        }

        if ($state->scheduledFor !== null) {
            $draft['scheduled_for'] = $state->scheduledFor;
        }

        $chat->update([
            'conversation_step' => $state->step->toLegacyStep(),
            'order_draft' => $draft,
        ]);
    }
}
