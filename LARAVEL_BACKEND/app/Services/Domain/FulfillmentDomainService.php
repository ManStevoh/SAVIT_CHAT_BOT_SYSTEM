<?php

namespace App\Services\Domain;

use App\DTOs\ConversationState;

final class FulfillmentDomainService
{
    /**
     * Validates whether a text string represents a legitimate delivery address.
     */
    public function isValidAddress(string $text): bool
    {
        $lower = mb_strtolower(trim($text));
        if (strlen($lower) < 3) {
            return false;
        }

        // Non-address affirmation phrases and questions are rejected
        $prohibited = [
            'yes', 'y', 'yeah', 'yep', 'yup', 'ok', 'okay', 'sure', 'proceed',
            'confirm', 'go ahead', 'done', 'yes proceed', 'yes proceed with the order',
            'i am ready', 'i am ready to proceed with the order', 'ready to proceed',
            'have you finalized?', 'delivery', 'pickup', 'dine in',
        ];

        if (in_array($lower, $prohibited, true)) {
            return false;
        }

        if (preg_match('/^(?:yes|okay|ok|sure|proceed|confirm|go ahead)\b(?:\s+(?:proceed|confirm|with|the|order|placement))*$/iu', $lower)) {
            return false;
        }

        if (preg_match('/(?:^|\b)(?:have you|is it|can you|how do|what is|when will|ready to proceed|finalize)\b/iu', $lower)) {
            return false;
        }

        if (str_contains($lower, '?')) {
            return false;
        }

        return true;
    }

    /**
     * Check if items in cart require a physical delivery address.
     */
    public function requiresDeliveryAddress(ConversationState $state): bool
    {
        if ($state->fulfillmentType === 'pickup' || $state->fulfillmentType === 'dine_in') {
            return false;
        }

        foreach ($state->cartItems as $item) {
            $data = $item['fulfillment_data'] ?? null;
            if (! is_array($data) || ($data['requiresDeliveryAddress'] ?? true) === true) {
                return true;
            }
        }

        return true;
    }
}
