<?php

namespace App\Services\Workflow;

use App\DTOs\ConversationState;
use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Services\Domain\CartDomainService;
use App\Services\Domain\FulfillmentDomainService;
use App\Services\Domain\OrderDomainService;

final class DomainServiceDispatcher
{
    public function __construct(
        private CartDomainService $cartDomain,
        private OrderDomainService $orderDomain,
        private FulfillmentDomainService $fulfillmentDomain,
    ) {}

    public function findProduct(Company $company, string $nameOrId): ?Product
    {
        $nameOrId = trim($nameOrId);
        if ($nameOrId === '' || (is_numeric($nameOrId) && (int) $nameOrId < 100)) {
            return null;
        }

        // 1. Direct LIKE query on product name
        $product = Product::where('company_id', $company->id)
            ->where('status', 'active')
            ->where('name', 'like', '%'.$nameOrId.'%')
            ->first();
        if ($product) {
            return $product;
        }

        // 2. Substring & word overlap query across all active company products
        $allProducts = Product::where('company_id', $company->id)
            ->where('status', 'active')
            ->get();

        $lowerInput = mb_strtolower($nameOrId);

        foreach ($allProducts as $p) {
            $pName = mb_strtolower($p->name);
            if ($pName !== '' && (str_contains($lowerInput, $pName) || (strlen($lowerInput) >= 3 && str_contains($pName, $lowerInput)))) {
                return $p;
            }
        }

        // 3. Search via Product Variants (e.g. "red earphones" matches variant "red" -> product "earphones")
        $variantMatch = \App\Models\ProductVariant::whereHas('product', function ($q) use ($company) {
                $q->where('company_id', $company->id)->where('status', 'active');
            })
            ->where(function ($q) use ($lowerInput) {
                $q->where('label', 'like', '%'.$lowerInput.'%')
                  ->orWhereRaw('LOWER(?) LIKE CONCAT("%", LOWER(label), "%")', [$lowerInput]);
            })
            ->with('product')
            ->first();

        if ($variantMatch && $variantMatch->product) {
            return $variantMatch->product;
        }

        return null;
    }

    public function addToCart(ConversationState $state, Product $product, int $quantity = 1, ?int $variantId = null): ConversationState
    {
        return $this->cartDomain->addItem($state, $product, $quantity, $variantId);
    }

    public function removeFromCart(ConversationState $state, string $productName): ConversationState
    {
        return $this->cartDomain->removeItem($state, $productName);
    }

    public function removeOrReduceItem(ConversationState $state, string $productName, int $qtyToRemove = 1): array
    {
        return $this->cartDomain->removeOrReduceItem($state, $productName, $qtyToRemove);
    }

    public function updateItemQuantity(ConversationState $state, string $productName, int $newQuantity): array
    {
        return $this->cartDomain->updateItemQuantity($state, $productName, $newQuantity);
    }

    public function createOrder(Company $company, ConversationState $state): Order
    {
        return $this->orderDomain->createOrderFromState($company, $state);
    }

    public function isValidAddress(string $address): bool
    {
        return $this->fulfillmentDomain->isValidAddress($address);
    }
}
