<?php

namespace App\Services\Domain;

use App\DTOs\ConversationState;
use App\Models\Product;

final class CartDomainService
{
    /**
     * Add a product to the conversation cart state.
     */
    public function addItem(ConversationState $state, Product $product, int $quantity = 1, ?int $variantId = null): ConversationState
    {
        $items = $state->cartItems;
        $foundIndex = -1;

        foreach ($items as $idx => $item) {
            $vId = $item['variant_id'] ?? $item['product_variant_id'] ?? null;
            if (($item['product_id'] ?? null) === $product->id && $vId === $variantId) {
                $foundIndex = $idx;
                break;
            }
        }

        if ($foundIndex >= 0) {
            $items[$foundIndex]['quantity'] += $quantity;
        } else {
            $variant = null;
            if ($variantId) {
                $variant = $product->variants->firstWhere('id', $variantId) ?? $product->activeVariants()->find($variantId);
            }

            $price = ($variant && $variant->price !== null) ? (float) $variant->price : (float) $product->price;
            $variantLabel = $variant ? ($variant->label ?? $variant->name ?? '') : '';
            $name = ($variant && $variantLabel !== '') ? "{$product->name} ({$variantLabel})" : $product->name;

            $items[] = [
                'product_id' => $product->id,
                'variant_id' => $variantId,
                'product_variant_id' => $variantId,
                'name' => $name,
                'price' => $price,
                'quantity' => $quantity,
                'fulfillment_data' => $product->fulfillmentSnapshot($variant),
            ];
        }

        return $state->with([
            'cartItems' => $items,
        ]);
    }

    /**
     * Remove an item from the cart state by product name.
     */
    public function removeItem(ConversationState $state, string $productName): ConversationState
    {
        $items = [];
        $lowerTarget = mb_strtolower(trim($productName));

        foreach ($state->cartItems as $item) {
            $itemName = mb_strtolower($item['name'] ?? '');
            if (! str_contains($itemName, $lowerTarget) && ! str_contains($lowerTarget, $itemName)) {
                $items[] = $item;
            }
        }

        return $state->with([
            'cartItems' => $items,
        ]);
    }

    /**
     * Calculate cart grand total.
     */
    public function calculateTotal(ConversationState $state): float
    {
        return $state->calculateCartTotal();
    }
}
