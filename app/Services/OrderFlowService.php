<?php

namespace App\Services;

use App\Models\Chat;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Services\MpesaService;
use App\Services\StripeService;
use Illuminate\Support\Str;

class OrderFlowService
{
    public const STEP_NONE = null;
    public const STEP_PRODUCT = 'product';
    public const STEP_QUANTITY = 'quantity';
    public const STEP_ADDRESS = 'address';
    public const STEP_CONFIRM = 'confirm';
    public const STEP_PAYMENT_METHOD = 'payment_method';
    public const STEP_MPESA_PHONE = 'mpesa_phone';

    public function __construct(
        protected OrderPaymentService $orderPayment
    ) {}

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
                $order = $this->createOrderFromDraft($company, $chat, $draft, $customerName, $customerPhone);
                $settings = $company->settings;
                $collectEnabled = $settings && $settings->orders_collect_payment_enabled !== false;
                if (! $collectEnabled) {
                    $this->clearState($chat);
                    return "Order confirmed! Your order number is: {$order->order_number}. Total: " . number_format((float) $order->total, 2) . ". We'll prepare it and contact you for delivery.";
                }
                $acceptMpesa = $settings && $settings->orders_accept_mpesa && (MpesaService::isEnabled() || $settings->hasOrderPaymentMpesaConfig());
                $acceptStripe = $settings && $settings->orders_accept_stripe && (StripeService::isEnabled() || $settings->hasOrderPaymentStripeConfig());
                $acceptManual = $settings && $settings->hasOrderPaymentManualInstructions();
                if ($acceptMpesa || $acceptStripe) {
                    $draft['order_id'] = $order->id;
                    $this->setStep($chat, self::STEP_PAYMENT_METHOD, $draft);
                    return $this->formatPaymentMethodPrompt($order, $acceptMpesa, $acceptStripe, $acceptManual);
                }
                if ($acceptManual) {
                    $this->clearState($chat);
                    return $this->formatOrderWithManualPaymentInstructions($order);
                }
                $this->clearState($chat);
                return "Order confirmed! Your order number is: {$order->order_number}. Total: " . number_format((float) $order->total, 2) . ". We'll prepare it and contact you for delivery.";
            }
        }

        if ($step === self::STEP_PAYMENT_METHOD) {
            $order = isset($draft['order_id']) ? Order::find($draft['order_id']) : null;
            if (! $order) {
                $this->clearState($chat);
                return 'Something went wrong. Please start over with "order" or "2".';
            }
            $settings = $company->settings;
            $acceptMpesa = $settings && $settings->orders_accept_mpesa && (MpesaService::isEnabled() || $settings->hasOrderPaymentMpesaConfig());
            $acceptStripe = $settings && $settings->orders_accept_stripe && (StripeService::isEnabled() || $settings->hasOrderPaymentStripeConfig());
            $acceptManual = $settings && $settings->hasOrderPaymentManualInstructions();
            if ($this->wantsManual($lower)) {
                $this->clearState($chat);
                return $this->formatOrderWithManualPaymentInstructions($order);
            }
            if ($this->wantsMpesa($lower)) {
                $draft['payment_method'] = 'mpesa';
                $this->setStep($chat, self::STEP_MPESA_PHONE, $draft);
                $displayPhone = $this->formatPhoneForDisplay($customerPhone);
                return "We'll send an M-Pesa payment request to your phone. Use this number ({$displayPhone}) or reply with a different number to receive the prompt.";
            }
            if ($this->wantsStripe($lower)) {
                $result = $this->orderPayment->createStripePaymentLinkForOrder($order);
                $this->clearState($chat);
                if ($result['success'] && ! empty($result['url'])) {
                    return "Order #{$order->order_number} – Pay by card here: {$result['url']}\n\nReply once you've completed payment. Thank you!";
                }
                return "Order confirmed! Your order number is: {$order->order_number}. Total: " . number_format((float) $order->total, 2) . ". We'll prepare it and contact you for delivery. (" . ($result['error'] ?? 'Payment link unavailable.') . ")";
            }
            return $this->formatPaymentMethodPrompt($order, $acceptMpesa, $acceptStripe, $acceptManual, true);
        }

        if ($step === self::STEP_MPESA_PHONE) {
            $order = isset($draft['order_id']) ? Order::find($draft['order_id']) : null;
            if (! $order) {
                $this->clearState($chat);
                return 'Something went wrong. Please start over with "order" or "2".';
            }
            $phone = $this->resolveMpesaPhone(trim($messageText), $customerPhone);
            if (! $phone) {
                return 'Please reply with "yes" to use this chat number, or send the phone number to receive the M-Pesa prompt (e.g. 254712345678).';
            }
            $result = $this->orderPayment->sendStkPushForOrder($order, $phone);
            $this->clearState($chat);
            if ($result['success']) {
                return "We've sent an M-Pesa payment request to your phone. Enter your M-Pesa PIN to complete payment. You'll get a confirmation here once payment is received.";
            }
            return "Order #{$order->order_number} confirmed. Total: " . number_format((float) $order->total, 2) . ". We couldn't send M-Pesa right now (" . ($result['error'] ?? 'please try again later') . "). We'll contact you for payment.";
        }

        return null;
    }

    protected function formatPaymentMethodPrompt(Order $order, bool $acceptMpesa, bool $acceptStripe, bool $acceptManual = false, bool $invalid = false): string
    {
        $line = "Order #{$order->order_number} – Total: " . number_format((float) $order->total, 2) . ".\n\nHow would you like to pay?";
        $opts = [];
        if ($acceptMpesa) {
            $opts[] = '1. M-Pesa (pay on your phone)';
        }
        if ($acceptStripe) {
            $opts[] = '2. Card (pay online)';
        }
        if ($acceptManual) {
            $opts[] = '3. Pay manually (bank / other details)';
        }
        $line .= "\n" . implode("\n", $opts);
        if ($invalid) {
            $line .= "\n\nPlease reply with 1, 2 or 3 (or M-Pesa / Card / Manual).";
        }
        return $line;
    }

    protected function formatOrderWithManualPaymentInstructions(Order $order): string
    {
        $settings = $order->company?->settings;
        $instructions = $settings && $settings->hasOrderPaymentManualInstructions()
            ? trim($settings->order_payment_manual_instructions)
            : '';
        $total = number_format((float) $order->total, 2);
        $line = "Order #{$order->order_number} confirmed. Total: {$total}.\n\nTo complete payment, please use the following details:\n\n{$instructions}\n\nReply once you have made the payment. Thank you!";
        return $line;
    }

    protected function wantsManual(string $lower): bool
    {
        return in_array($lower, ['3', 'manual', 'bank', 'bank transfer', 'pay manually', 'other'], true)
            || str_contains($lower, 'bank') || str_contains($lower, 'manual');
    }

    protected function wantsMpesa(string $lower): bool
    {
        return in_array($lower, ['1', 'mpesa', 'm-pesa', 'mobile', 'phone'], true)
            || str_contains($lower, 'mpesa') || str_contains($lower, 'm-pesa');
    }

    protected function wantsStripe(string $lower): bool
    {
        return in_array($lower, ['2', 'card', 'stripe', 'pay online', 'online', 'link'], true)
            || str_contains($lower, 'card') || str_contains($lower, 'pay online');
    }

    protected function formatPhoneForDisplay(string $customerPhone): string
    {
        $digits = preg_replace('/\D/', '', $customerPhone);
        if (strlen($digits) === 9 && str_starts_with($digits, '7')) {
            return '254' . $digits;
        }
        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            return '254' . substr($digits, 1);
        }
        return $customerPhone ?: '—';
    }

    /**
     * Resolve phone number for M-Pesa: "yes"/"ok"/"same" = customerPhone; else parse digits.
     */
    protected function resolveMpesaPhone(string $message, string $customerPhone): ?string
    {
        $lower = mb_strtolower($message);
        if (in_array($lower, ['yes', 'ok', 'same', 'this one', 'use this', 'current'], true)) {
            return $customerPhone ?: null;
        }
        $digits = preg_replace('/\D/', '', $message);
        if (strlen($digits) >= 9) {
            if (strlen($digits) === 9 && str_starts_with($digits, '7')) {
                return '254' . $digits;
            }
            if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
                return '254' . substr($digits, 1);
            }
            if (strlen($digits) >= 12) {
                return $digits;
            }
            return '254' . $digits;
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

    protected function createOrderFromDraft(Company $company, Chat $chat, array $draft, string $customerName, string $customerPhone): Order
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
            'chat_id' => $chat->id,
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
