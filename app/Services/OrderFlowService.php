<?php

namespace App\Services;

use App\Models\Chat;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use Illuminate\Support\Str;

class OrderFlowService
{
    public const STEP_NONE = null;
    public const STEP_PRODUCT = 'product';
    public const STEP_QUANTITY = 'quantity';
    public const STEP_ADDRESS = 'address';
    public const STEP_CONFIRM = 'confirm';

    /**
     * Process customer message in order flow context.
     * Returns reply text to send, or null to fall through to normal AI reply.
     */
    public function processMessage(Chat $chat, Company $company, string $messageText, string $customerName, string $customerPhone): ?string
    {
        $step = $chat->conversation_step;
        $draft = $this->getDraft($chat);
        $lower = mb_strtolower(trim($messageText));

        if ($this->wantsCancel($lower)) {
            $this->clearState($chat);
            return 'Order cancelled. Reply with "order" or "2" when you\'re ready to place a new order.';
        }

        if ($this->wantsStartOrder($lower) && ! $step) {
            $productList = $this->formatProductList($company);
            $this->setStep($chat, self::STEP_PRODUCT, []);
            return $productList . "\n\nReply with the product name and quantity (e.g. \"2 x Coffee\"), or \"done\" when you've added all items.";
        }

        if ($step === self::STEP_PRODUCT) {
            if ($this->wantsDone($lower)) {
                if (empty($draft['items'])) {
                    return 'You haven\'t added any items yet. Tell me the product name and quantity (e.g. "2 x Coffee"), or "cancel" to cancel.';
                }
                $this->setStep($chat, self::STEP_ADDRESS, $draft);
                return 'What is your delivery address?';
            }
            $parsed = $this->parseProductLine($company, $messageText);
            if ($parsed) {
                $draft['items'] = $draft['items'] ?? [];
                $draft['items'][] = $parsed;
                $this->setStep($chat, self::STEP_PRODUCT, $draft);
                $summary = $this->formatDraftSummary($draft);
                return "Added: {$parsed['name']} x {$parsed['quantity']}.\n\n{$summary}\n\nAdd more items (e.g. \"1 x Tea\") or reply \"done\" to proceed to delivery address.";
            }
        }

        if ($step === self::STEP_ADDRESS) {
            $address = trim($messageText);
            if (strlen($address) < 3) {
                return 'Please provide a valid delivery address (at least a few characters).';
            }
            $draft['delivery_address'] = $address;
            $this->setStep($chat, self::STEP_CONFIRM, $draft);
            $summary = $this->formatDraftSummary($draft);
            return "Delivery address: {$address}\n\n{$summary}\n\nReply \"confirm\" to place the order, or \"cancel\" to cancel.";
        }

        if ($step === self::STEP_CONFIRM) {
            if ($this->wantsConfirm($lower)) {
                $order = $this->createOrderFromDraft($company, $draft, $customerName, $customerPhone);
                $this->clearState($chat);
                return "Order confirmed! Your order number is: {$order->order_number}. Total: " . number_format((float) $order->total, 2) . ". We'll prepare it and contact you for delivery.";
            }
        }

        return null;
    }

    protected function wantsCancel(string $lower): bool
    {
        return in_array($lower, ['cancel', 'start over', 'nevermind', 'never mind', 'stop'], true)
            || str_contains($lower, 'cancel order');
    }

    protected function wantsStartOrder(string $lower): bool
    {
        return $lower === '2' || $lower === 'order' || $lower === 'place order'
            || str_contains($lower, 'i want to order') || str_contains($lower, 'place an order');
    }

    protected function wantsDone(string $lower): bool
    {
        return in_array($lower, ['done', 'that\'s all', 'thats all', 'finish', 'next'], true);
    }

    protected function wantsConfirm(string $lower): bool
    {
        return in_array($lower, ['confirm', 'yes', 'place order', 'confirm order'], true);
    }

    protected function getDraft(Chat $chat): array
    {
        $raw = $chat->order_draft;
        if (! is_array($raw)) {
            return ['items' => []];
        }
        $raw['items'] = $raw['items'] ?? [];
        return $raw;
    }

    protected function setStep(Chat $chat, ?string $step, array $draft): void
    {
        $chat->update([
            'conversation_step' => $step,
            'order_draft' => $step ? $draft : null,
        ]);
    }

    protected function clearState(Chat $chat): void
    {
        $this->setStep($chat, self::STEP_NONE, []);
    }

    protected function parseProductLine(Company $company, string $text): ?array
    {
        $products = Product::where('company_id', $company->id)->where('status', 'active')->get();
        if ($products->isEmpty()) {
            return null;
        }

        $text = trim($text);
        if (preg_match('/^(\d+)\s*[x×]\s*(.+)$/iu', $text, $m)) {
            $qty = (int) $m[1];
            $namePart = trim($m[2]);
            if ($qty < 1) {
                return null;
            }
            $product = $this->matchProduct($products, $namePart);
            if ($product) {
                return [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'price' => (float) $product->price,
                    'quantity' => $qty,
                ];
            }
        }

        if (preg_match('/^(.+?)\s*[x×]\s*(\d+)$/iu', $text, $m)) {
            $namePart = trim($m[1]);
            $qty = (int) $m[2];
            if ($qty < 1) {
                return null;
            }
            $product = $this->matchProduct($products, $namePart);
            if ($product) {
                return [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'price' => (float) $product->price,
                    'quantity' => $qty,
                ];
            }
        }

        $product = $this->matchProduct($products, $text);
        if ($product) {
            return [
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'quantity' => 1,
            ];
        }

        return null;
    }

    protected function matchProduct($products, string $namePart): ?Product
    {
        $lower = mb_strtolower($namePart);
        foreach ($products as $p) {
            if (mb_strtolower($p->name) === $lower || str_contains(mb_strtolower($p->name), $lower)) {
                return $p;
            }
        }
        foreach ($products as $p) {
            if (str_contains($lower, mb_strtolower($p->name))) {
                return $p;
            }
        }
        return null;
    }

    protected function formatProductList(Company $company): string
    {
        $products = Product::where('company_id', $company->id)->where('status', 'active')->orderBy('name')->get();
        if ($products->isEmpty()) {
            return 'We don\'t have any products in the catalog right now. Please contact us for availability.';
        }
        $lines = ["Here are our products:\n"];
        foreach ($products->take(30) as $p) {
            $price = is_numeric($p->price) ? number_format((float) $p->price, 2) : $p->price;
            $lines[] = "• {$p->name}: {$price}";
        }
        $lines[] = "\nReply with product name and quantity (e.g. \"2 x Coffee\").";
        return implode("\n", $lines);
    }

    protected function formatDraftSummary(array $draft): string
    {
        $items = $draft['items'] ?? [];
        if (empty($items)) {
            return 'No items in cart.';
        }
        $lines = ['Your order:'];
        $total = 0.0;
        foreach ($items as $item) {
            $sub = ($item['price'] ?? 0) * ($item['quantity'] ?? 0);
            $total += $sub;
            $lines[] = "• {$item['name']} x {$item['quantity']} = " . number_format($sub, 2);
        }
        $lines[] = 'Total: ' . number_format($total, 2);
        return implode("\n", $lines);
    }

    protected function createOrderFromDraft(Company $company, array $draft, string $customerName, string $customerPhone): Order
    {
        $items = $draft['items'] ?? [];
        $total = 0.0;
        foreach ($items as $item) {
            $total += ($item['price'] ?? 0) * ($item['quantity'] ?? 0);
        }

        $orderNumber = 'ORD-' . strtoupper(Str::random(8));
        while (Order::where('order_number', $orderNumber)->exists()) {
            $orderNumber = 'ORD-' . strtoupper(Str::random(8));
        }

        $order = Order::create([
            'company_id' => $company->id,
            'order_number' => $orderNumber,
            'customer_name' => $customerName ?: 'Customer',
            'customer_phone' => $customerPhone,
            'total' => round($total, 2),
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        foreach ($items as $item) {
            OrderProduct::create([
                'order_id' => $order->id,
                'name' => $item['name'] ?? 'Item',
                'quantity' => (int) ($item['quantity'] ?? 1),
                'price' => (float) ($item['price'] ?? 0),
            ]);
        }

        return $order;
    }
}
