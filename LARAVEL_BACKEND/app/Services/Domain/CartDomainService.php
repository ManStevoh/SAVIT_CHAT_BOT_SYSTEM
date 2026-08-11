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
        $res = $this->removeOrReduceItem($state, $productName, 9999);

        return $res['state'];
    }

    /**
     * Remove or reduce item quantity from the cart state.
     */
    public function removeOrReduceItem(ConversationState $state, string $productName, int $qtyToRemove = 1): array
    {
        $items = [];
        $lowerTarget = mb_strtolower(trim($productName));
        $removedName = null;
        $newQty = 0;
        $wasReduced = false;

        foreach ($state->cartItems as $item) {
            $itemName = mb_strtolower($item['name'] ?? '');
            $match = ($lowerTarget === '')
                || ($itemName !== '' && (str_contains($itemName, $lowerTarget) || str_contains($lowerTarget, $itemName)));

            if ($removedName === null && $match) {
                $removedName = $item['name'] ?? 'item';
                $currentQty = (int) ($item['quantity'] ?? 1);
                if ($currentQty > $qtyToRemove) {
                    $item['quantity'] = $currentQty - $qtyToRemove;
                    $newQty = $item['quantity'];
                    $wasReduced = true;
                    $items[] = $item;
                } else {
                    $newQty = 0;
                    $wasReduced = false;
                }
            } else {
                $items[] = $item;
            }
        }

        $nextState = $state->with([
            'cartItems' => $items,
        ]);

        return [
            'state' => $nextState,
            'item_name' => $removedName,
            'was_reduced' => $wasReduced,
            'new_qty' => $newQty,
        ];
    }

    /**
     * Update absolute item quantity in the cart state.
     */
    public function updateItemQuantity(ConversationState $state, string $productName, int $newQuantity): array
    {
        $items = [];
        $lowerTarget = mb_strtolower(trim($productName));
        $updatedName = null;

        foreach ($state->cartItems as $item) {
            $itemName = mb_strtolower($item['name'] ?? '');
            $match = ($lowerTarget === '')
                || ($itemName !== '' && (str_contains($itemName, $lowerTarget) || str_contains($lowerTarget, $itemName)));

            if ($updatedName === null && $match) {
                $updatedName = $item['name'] ?? 'item';
                if ($newQuantity > 0) {
                    $item['quantity'] = $newQuantity;
                    $items[] = $item;
                }
            } else {
                $items[] = $item;
            }
        }

        $nextState = $state->with([
            'cartItems' => $items,
        ]);

        return [
            'state' => $nextState,
            'item_name' => $updatedName,
            'new_qty' => max(0, $newQuantity),
        ];
    }

    /**
     * Calculate cart grand total.
     */
    public function calculateTotal(ConversationState $state): float
    {
        return $state->calculateCartTotal();
    }
}
