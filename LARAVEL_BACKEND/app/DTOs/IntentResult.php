<?php

namespace App\DTOs;

use App\Enums\CommerceIntent;

final class IntentResult
{
    public function __construct(
        public CommerceIntent $intent,
        public float $confidence,
        public ?string $product = null,
        public ?string $variant = null,
        public int $quantity = 1,
        public ?string $address = null,
        public ?string $paymentMethod = null,
        public ?string $phoneNumber = null,
        public bool $requiresClarification = false,
        public ?string $clarificationQuestion = null,
        public array $rawPayload = [],
        public ?string $messageText = null,
        public ?string $selectedToken = null,
        public ?string $targetProductToken = null,
        public ?string $targetVariantToken = null,
        public string $actionDirective = 'CONTINUE_FLOW',
        public ?int $resolvedProductId = null,
        public ?int $resolvedVariantId = null,
    ) {}

    public static function fromArray(array $data, ?string $messageText = null): self
    {
        $intentValue = $data['intent'] ?? 'unknown';
        if ($intentValue === 'cancel') {
            $intentValue = 'cancel_order';
        }
        $intent = CommerceIntent::tryFrom($intentValue) ?? CommerceIntent::UNKNOWN;
        $entities = is_array($data['entities'] ?? null) ? $data['entities'] : [];

        return new self(
            intent: $intent,
            confidence: (float) ($data['confidence'] ?? 0.0),
            product: isset($entities['product']) && trim((string) $entities['product']) !== '' ? trim((string) $entities['product']) : null,
            variant: isset($entities['variant']) && trim((string) $entities['variant']) !== '' ? trim((string) $entities['variant']) : null,
            quantity: max(1, (int) ($entities['quantity'] ?? $data['quantity_override'] ?? 1)),
            address: isset($entities['address']) && trim((string) $entities['address']) !== '' ? trim((string) $entities['address']) : null,
            paymentMethod: isset($entities['payment_method']) && trim((string) $entities['payment_method']) !== '' ? trim((string) $entities['payment_method']) : null,
            phoneNumber: isset($entities['phone_number']) && trim((string) $entities['phone_number']) !== '' ? trim((string) $entities['phone_number']) : null,
            requiresClarification: (bool) ($data['requires_clarification'] ?? false),
            clarificationQuestion: isset($data['clarification_question']) && trim((string) $data['clarification_question']) !== '' ? (string) $data['clarification_question'] : null,
            rawPayload: array_merge($data, ['incoming_message' => $messageText]),
            messageText: $messageText,
            selectedToken: isset($data['selected_token']) && trim((string) $data['selected_token']) !== '' ? trim((string) $data['selected_token']) : null,
            targetProductToken: isset($data['target_product_token']) && trim((string) $data['target_product_token']) !== '' ? trim((string) $data['target_product_token']) : null,
            targetVariantToken: isset($data['target_variant_token']) && trim((string) $data['target_variant_token']) !== '' ? trim((string) $data['target_variant_token']) : null,
            actionDirective: (string) ($data['action_directive'] ?? 'CONTINUE_FLOW'),
        );
    }

    public function isHighConfidence(?float $threshold = null): bool
    {
        $minConfidence = $threshold ?? (float) config('agent.ai_intent_min_confidence', 0.82);

        return $this->confidence >= $minConfidence;
    }

    public function isExplicitPurchaseIntent(): bool
    {
        $inquiryIntents = [
            CommerceIntent::ASK_PRODUCT_INFO,
            CommerceIntent::ASK_PRICE,
            CommerceIntent::ASK_STORE_LOCATION,
            CommerceIntent::ASK_DELIVERY_FEE,
            CommerceIntent::ASK_ORDER_STATUS,
            CommerceIntent::REQUEST_HUMAN,
            CommerceIntent::GENERAL_CHAT,
            CommerceIntent::UNKNOWN,
        ];

        if (in_array($this->intent, $inquiryIntents, true)) {
            return false;
        }

        return $this->intent === CommerceIntent::ADD_TO_CART
            || $this->intent === CommerceIntent::SELECT_OPTION
            || $this->intent === CommerceIntent::START_CHECKOUT
            || $this->intent === CommerceIntent::CONFIRM_ORDER;
    }
}
