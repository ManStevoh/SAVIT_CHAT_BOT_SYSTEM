<?php

namespace App\Services;

use App\Models\Chat;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class OrderFlowService
{
    public const STEP_NONE = null;

    public const STEP_PRODUCT = 'product';

    public const STEP_VARIANT = 'variant';

    public const STEP_PRODUCT_QTY = 'product_qty';

    public const STEP_ADDRESS = 'address';

    public const STEP_CONFIRM = 'confirm';

    public const STEP_PAYMENT_METHOD = 'payment_method';

    public const STEP_MPESA_PHONE = 'mpesa_phone';

    public function __construct(
        protected OrderPaymentService $orderPayment
    ) {}

    /**
     * Numbered catalog + instructions (used by AI keyword replies when not in an active order step).
     */
    public function formatCatalogForDisplay(Company $company): string
    {
        return $this->formatNumberedProductList($company)."\n\n".$this->numberedOrderInstructions();
    }

    /**
     * Clear saved order flow state (e.g. before a fresh greeting so menu options 1/2/3 are not confused with product numbers).
     */
    public function resetOrderState(Chat $chat): void
    {
        $this->clearState($chat);
    }

    /**
     * Process customer message in order flow context.
     * Returns reply text to send, or null to fall through to normal AI reply.
     */
    public function processMessage(Chat $chat, Company $company, string $messageText, string $customerName, string $customerPhone): ?string
    {
        $step = $chat->conversation_step;
        $draft = $this->getDraft($chat);
        $lower = mb_strtolower(trim($messageText));
        $trimmed = trim($messageText);

        // Abandoned carts often leave conversation_step=product; a new "Hi"/"Hello" should restart at menu level.
        if ($step && $this->looksLikeGreetingOnly($lower)) {
            $this->clearState($chat);
            $chat->refresh();
            $step = $chat->conversation_step;
            $draft = $this->getDraft($chat);
        }

        if ($this->wantsCancel($lower)) {
            $this->clearState($chat);

            return 'Order cancelled. Reply with "order" or "2" when you\'re ready to place a new order.';
        }

        if ($this->wantsStartOrder($lower) && ! $step) {
            return $this->beginProductStep($chat, $company, []);
        }

        if (! $step && $this->wantsCatalogOrPrices($lower)) {
            return $this->beginProductStep($chat, $company, []);
        }

        if (! $step) {
            if (! $this->looksLikeGreetingOnly($lower)) {
                $parsed = $this->parseProductLine($company, $messageText);
                if ($parsed) {
                    $draft = ['items' => [$parsed]];
                    $draft = $this->withCatalogIds($company, $draft);
                    $this->setStep($chat, self::STEP_PRODUCT, $draft);
                    $summary = $this->formatDraftSummary($draft);

                    return "Added: {$parsed['name']} x {$parsed['quantity']}.\n\n{$summary}\n\n".$this->afterAddItemInstructions();
                }
            }
        }

        if ($step === self::STEP_VARIANT) {
            return $this->handleVariantStep($chat, $company, $draft, $lower, $trimmed);
        }

        if ($step === self::STEP_PRODUCT_QTY) {
            return $this->handleProductQtyStep($chat, $company, $draft, $lower, $trimmed);
        }

        if ($step === self::STEP_PRODUCT) {
            if ($this->wantsDone($lower)) {
                if (empty($draft['items'])) {
                    return 'You haven\'t added any items yet. Reply with a product number from the list, or type e.g. "2 x Coffee", or "cancel".';
                }
                $this->setStep($chat, self::STEP_ADDRESS, $this->stripPickingDraft($draft));

                return 'What is your delivery address?';
            }

            $picked = $this->tryPickProductByNumber($chat, $company, $draft, $trimmed);
            if ($picked !== null) {
                return $picked;
            }

            if ($this->wantsCatalogRefresh($lower)) {
                return $this->refreshCatalogInProductStep($chat, $company, $draft);
            }

            if ($this->shouldDelegateOrderStepToAssistant($lower, $trimmed)) {
                return null;
            }

            $parsed = null;
            if (! $this->looksLikeGreetingOnly($lower)) {
                $parsed = $this->parseProductLine($company, $messageText);
            }
            if ($parsed) {
                $draft['items'] = $draft['items'] ?? [];
                $draft['items'][] = $parsed;
                $draft = $this->withCatalogIds($company, $draft);
                $this->setStep($chat, self::STEP_PRODUCT, $draft);
                $summary = $this->formatDraftSummary($draft);

                return "Added: {$parsed['name']} x {$parsed['quantity']}.\n\n{$summary}\n\n".$this->afterAddItemInstructions();
            }

            return $this->productStepUnrecognizedReply();
        }

        if ($step === self::STEP_ADDRESS) {
            if ($this->shouldDelegateOrderStepToAssistant($lower, $trimmed)
                || $this->looksLikeLocationOrShopInfoQuestion($lower)) {
                return null;
            }
            $address = trim($messageText);
            if (strlen($address) < 3) {
                return 'Please provide a valid delivery address (at least a few characters).';
            }
            $draft['delivery_address'] = $address;
            $this->setStep($chat, self::STEP_CONFIRM, $draft);
            $summary = $this->formatDraftSummary($draft);

            return "Delivery address: {$address}\n\n{$summary}\n\nReply 1 or \"confirm\" to place the order, or 2 or \"cancel\" to cancel.";
        }

        if ($step === self::STEP_CONFIRM) {
            if ($this->wantsDiscardConfirmOrder($lower)) {
                $this->clearState($chat);

                return 'Order not placed. Reply with "order" or "2" when you want to try again.';
            }
            if ($this->wantsConfirm($lower)) {
                $order = $this->createOrderFromDraft($company, $chat, $draft, $customerName, $customerPhone);
                $settings = $company->settings;
                $collectEnabled = $settings && $settings->orders_collect_payment_enabled !== false;
                if (! $collectEnabled) {
                    $this->clearState($chat);

                    return $this->withReceipt($order, "Order confirmed! Your order number is: {$order->order_number}. Total: ".number_format((float) $order->total, 2).". We'll prepare it and contact you for delivery.");
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

                return $this->withReceipt($order, "Order confirmed! Your order number is: {$order->order_number}. Total: ".number_format((float) $order->total, 2).". We'll prepare it and contact you for delivery.");
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
                    return $this->withReceipt($order, "Order #{$order->order_number} – Pay by card here: {$result['url']}\n\nReply once you've completed payment. Thank you!");
                }

                return $this->withReceipt($order, "Order confirmed! Your order number is: {$order->order_number}. Total: ".number_format((float) $order->total, 2).". We'll prepare it and contact you for delivery. (".($result['error'] ?? 'Payment link unavailable.').')');
            }
            if ($this->shouldDelegateOrderStepToAssistant($lower, $trimmed)) {
                return null;
            }

            return $this->formatPaymentMethodPrompt($order, $acceptMpesa, $acceptStripe, $acceptManual, true);
        }

        if ($step === self::STEP_MPESA_PHONE) {
            $order = isset($draft['order_id']) ? Order::find($draft['order_id']) : null;
            if (! $order) {
                $this->clearState($chat);

                return 'Something went wrong. Please start over with "order" or "2".';
            }
            if (! preg_match('/\d/', $trimmed) && $this->shouldDelegateOrderStepToAssistant($lower, $trimmed)) {
                return null;
            }
            $phone = $this->resolveMpesaPhone(trim($messageText), $customerPhone);
            if (! $phone) {
                return 'Please reply with "yes" to use this chat number, or send the phone number to receive the M-Pesa prompt (e.g. 254712345678).';
            }
            $result = $this->orderPayment->sendStkPushForOrder($order, $phone);
            $this->clearState($chat);
            if ($result['success']) {
                return $this->withReceipt($order, "We've sent an M-Pesa payment request to your phone. Enter your M-Pesa PIN to complete payment. You'll get a confirmation here once payment is received.");
            }

            return $this->withReceipt($order, "Order #{$order->order_number} confirmed. Total: ".number_format((float) $order->total, 2).". We couldn't send M-Pesa right now (".($result['error'] ?? 'please try again later')."). We'll contact you for payment.");
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    protected function tryPickProductByNumber(Chat $chat, Company $company, array $draft, string $trimmed): ?string
    {
        if (! preg_match('/^\d+$/', $trimmed)) {
            return null;
        }
        $ids = $draft['catalog_product_ids'] ?? [];
        if ($ids === []) {
            return null;
        }
        $n = (int) $trimmed;
        if ($n < 1 || $n > count($ids)) {
            return 'Pick a number between 1 and '.count($ids).'.';
        }
        $productId = $ids[$n - 1];
        $product = $this->getCatalogProductById($company, (int) $productId);
        if (! $product) {
            return 'Product not found. Reply with "order" to refresh the list.';
        }
        if ($this->productHasActiveVariants($product)) {
            $variantIds = $product->variants->where('status', 'active')->pluck('id')->values()->all();
            $draft['pending_product_id'] = $product->id;
            $draft['variant_ids'] = $variantIds;
            unset($draft['pending_variant_id']);
            $this->setStep($chat, self::STEP_VARIANT, $draft);

            return $this->formatVariantListMessage($product);
        }
        $draft['pending_product_id'] = $product->id;
        unset($draft['pending_variant_id'], $draft['variant_ids']);
        $this->setStep($chat, self::STEP_PRODUCT_QTY, $draft);

        return 'You selected: '.$product->name.' ('.number_format((float) $product->price, 2).").\nHow many do you want? (reply with a number, e.g. 2)\nReply \"back\" to return to the product list.";
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    protected function handleVariantStep(Chat $chat, Company $company, array $draft, string $lower, string $trimmed): ?string
    {
        if ($this->wantsBackToCatalog($lower)) {
            $productId = $draft['pending_product_id'] ?? null;
            unset($draft['pending_product_id'], $draft['variant_ids'], $draft['pending_variant_id']);
            $draft = $this->withCatalogIds($company, $draft);
            $this->setStep($chat, self::STEP_PRODUCT, $draft);

            return $this->formatNumberedProductList($company)."\n\n".$this->numberedOrderInstructions();
        }

        if ($this->wantsCatalogRefresh($lower)) {
            unset($draft['pending_product_id'], $draft['variant_ids'], $draft['pending_variant_id']);
            $draft = $this->withCatalogIds($company, $draft);
            $this->setStep($chat, self::STEP_PRODUCT, $draft);

            return $this->refreshCatalogInProductStep($chat, $company, $draft);
        }

        if ($this->shouldDelegateOrderStepToAssistant($lower, $trimmed)) {
            return null;
        }

        $productId = $draft['pending_product_id'] ?? null;
        $variantIds = $draft['variant_ids'] ?? [];
        if (! $productId || $variantIds === []) {
            $this->setStep($chat, self::STEP_PRODUCT, $this->withCatalogIds($company, $draft));

            return $this->formatNumberedProductList($company)."\n\n".$this->numberedOrderInstructions();
        }

        $product = $this->getCatalogProductById($company, (int) $productId);
        if (! $product) {
            $this->setStep($chat, self::STEP_PRODUCT, $this->withCatalogIds($company, $draft));

            return $this->formatNumberedProductList($company)."\n\n".$this->numberedOrderInstructions();
        }

        if (! preg_match('/^\d+$/', $trimmed)) {
            return 'Reply with the option number from the list, or "back" for the full product list. You can also ask a question about our shop.';
        }
        $n = (int) $trimmed;
        if ($n < 1 || $n > count($variantIds)) {
            return 'Pick a number between 1 and '.count($variantIds).'.';
        }
        $variantId = (int) $variantIds[$n - 1];
        $variant = $product->variants->firstWhere('id', $variantId);
        if (! $variant || $variant->status !== 'active') {
            return 'That option is unavailable. Pick another number.';
        }
        $draft['pending_variant_id'] = $variant->id;
        $this->setStep($chat, self::STEP_PRODUCT_QTY, $draft);

        return 'Selected: '.$product->name.' — '.$variant->label.' ('.number_format((float) $variant->price, 2).").\nHow many? (reply with a number)\nReply \"back\" to change option.";
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    protected function handleProductQtyStep(Chat $chat, Company $company, array $draft, string $lower, string $trimmed): ?string
    {
        if ($this->wantsBackToCatalog($lower)) {
            $hadVariantChoice = ! empty($draft['pending_variant_id']);
            $productId = $draft['pending_product_id'] ?? null;
            unset($draft['pending_variant_id'], $draft['variant_ids']);
            if ($hadVariantChoice && $productId) {
                $product = $this->getCatalogProductById($company, (int) $productId);
                if ($product && $this->productHasActiveVariants($product)) {
                    $draft['pending_product_id'] = $product->id;
                    $draft['variant_ids'] = $product->variants->where('status', 'active')->pluck('id')->values()->all();
                    $this->setStep($chat, self::STEP_VARIANT, $draft);

                    return $this->formatVariantListMessage($product);
                }
            }
            unset($draft['pending_product_id']);
            $draft = $this->withCatalogIds($company, $draft);
            $this->setStep($chat, self::STEP_PRODUCT, $draft);

            return $this->formatNumberedProductList($company)."\n\n".$this->numberedOrderInstructions();
        }

        if ($this->wantsCatalogRefresh($lower)) {
            unset($draft['pending_product_id'], $draft['pending_variant_id'], $draft['variant_ids']);
            $draft = $this->withCatalogIds($company, $draft);
            $this->setStep($chat, self::STEP_PRODUCT, $draft);

            return $this->refreshCatalogInProductStep($chat, $company, $draft);
        }

        if ($this->shouldDelegateOrderStepToAssistant($lower, $trimmed)) {
            return null;
        }

        if (! preg_match('/^\d+$/', $trimmed)) {
            return 'Reply with how many you want (a whole number), or "back" for the list. You can also ask a question about our shop.';
        }
        $qty = (int) $trimmed;
        if ($qty < 1 || $qty > 999) {
            return 'Enter a quantity between 1 and 999.';
        }

        $productId = $draft['pending_product_id'] ?? null;
        if (! $productId) {
            $this->setStep($chat, self::STEP_PRODUCT, $this->withCatalogIds($company, $draft));

            return $this->formatNumberedProductList($company)."\n\n".$this->numberedOrderInstructions();
        }

        $product = $this->getCatalogProductById($company, (int) $productId);
        if (! $product) {
            $this->setStep($chat, self::STEP_PRODUCT, $this->withCatalogIds($company, $draft));

            return $this->formatNumberedProductList($company)."\n\n".$this->numberedOrderInstructions();
        }

        $variantId = $draft['pending_variant_id'] ?? null;
        if ($variantId) {
            $variant = $product->variants->firstWhere('id', (int) $variantId);
            if (! $variant || $variant->status !== 'active') {
                return 'Option not found. Reply "back".';
            }
            if ($variant->stock < $qty) {
                return "Only {$variant->stock} in stock for this option. Enter a smaller quantity or \"back\".";
            }
            $line = [
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'name' => $product->name.' — '.$variant->label,
                'price' => (float) $variant->price,
                'quantity' => $qty,
            ];
        } else {
            if ($this->productHasActiveVariants($product)) {
                return 'This product requires choosing an option first. Reply "back".';
            }
            if ($product->stock < $qty) {
                return "Only {$product->stock} in stock. Enter a smaller quantity or \"back\".";
            }
            $line = [
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'quantity' => $qty,
            ];
        }

        $draft['items'] = $draft['items'] ?? [];
        $draft['items'][] = $line;
        unset($draft['pending_product_id'], $draft['pending_variant_id'], $draft['variant_ids']);
        $draft = $this->withCatalogIds($company, $draft);
        $this->setStep($chat, self::STEP_PRODUCT, $draft);
        $summary = $this->formatDraftSummary($draft);

        return "Added: {$line['name']} x {$qty}.\n\n{$summary}\n\n".$this->afterAddItemInstructions();
    }

    protected function beginProductStep(Chat $chat, Company $company, array $draft): string
    {
        $draft['items'] = $draft['items'] ?? [];
        $draft = $this->withCatalogIds($company, $draft);
        $this->setStep($chat, self::STEP_PRODUCT, $draft);

        return $this->formatNumberedProductList($company)."\n\n".$this->numberedOrderInstructions();
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    protected function withCatalogIds(Company $company, array $draft): array
    {
        $draft['catalog_product_ids'] = $this->getCatalogProducts($company)->pluck('id')->all();

        return $draft;
    }

    protected function getCatalogProducts(Company $company): Collection
    {
        return Product::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->with(['variants' => fn ($q) => $q->where('status', 'active')->orderBy('sort_order')->orderBy('id')])
            ->orderBy('name')
            ->limit(30)
            ->get();
    }

    protected function getCatalogProductById(Company $company, int $id): ?Product
    {
        $p = Product::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->whereKey($id)
            ->with(['variants' => fn ($q) => $q->where('status', 'active')->orderBy('sort_order')->orderBy('id')])
            ->first();

        return $p;
    }

    protected function productHasActiveVariants(Product $product): bool
    {
        return $product->variants->where('status', 'active')->isNotEmpty();
    }

    protected function formatVariantListMessage(Product $product): string
    {
        $lines = ["Options for {$product->name}:\n"];
        $i = 1;
        foreach ($product->variants->where('status', 'active') as $v) {
            $lines[] = "{$i}. {$v->label} — ".number_format((float) $v->price, 2);
            $i++;
        }
        $lines[] = "\nReply with the option number. Reply \"back\" for the main product list.";

        return implode("\n", $lines);
    }

    protected function formatNumberedProductList(Company $company): string
    {
        $products = $this->getCatalogProducts($company);
        if ($products->isEmpty()) {
            return 'We don\'t have any products in the catalog right now. Please contact us for availability.';
        }
        $lines = ["Our products (reply with a number to add to your order):\n"];
        $i = 1;
        foreach ($products as $p) {
            if ($this->productHasActiveVariants($p)) {
                $min = (float) $p->variants->where('status', 'active')->min('price');
                $priceLabel = 'from '.number_format($min, 2);
            } else {
                $priceLabel = number_format((float) $p->price, 2);
            }
            $lines[] = "{$i}. {$p->name} — {$priceLabel}";
            $i++;
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    protected function stripPickingDraft(array $draft): array
    {
        unset(
            $draft['catalog_product_ids'],
            $draft['pending_product_id'],
            $draft['pending_variant_id'],
            $draft['variant_ids']
        );

        return $draft;
    }

    protected function afterAddItemInstructions(): string
    {
        return 'Add more items (number or "2 x Name"), or reply 0 / "done" to enter your delivery address.';
    }

    protected function formatPaymentMethodPrompt(Order $order, bool $acceptMpesa, bool $acceptStripe, bool $acceptManual = false, bool $invalid = false): string
    {
        $line = 'Order #'.$order->order_number.' – Total: '.number_format((float) $order->total, 2).".\n\nHow would you like to pay?";
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
        $line .= "\n".implode("\n", $opts);
        if ($invalid) {
            $line .= "\n\nPlease reply with 1, 2 or 3 (or M-Pesa / Card / Manual).";
        }

        return $this->withReceipt($order, $line);
    }

    protected function formatOrderWithManualPaymentInstructions(Order $order): string
    {
        $settings = $order->company?->settings;
        $instructions = $settings && $settings->hasOrderPaymentManualInstructions()
            ? trim($settings->order_payment_manual_instructions)
            : '';
        $total = number_format((float) $order->total, 2);
        $line = "Order #{$order->order_number} confirmed. Total: {$total}.\n\nTo complete payment, please use the following details:\n\n{$instructions}\n\nReply once you have made the payment. Thank you!";

        return $this->withReceipt($order, $line);
    }

    protected function withReceipt(Order $order, string $message): string
    {
        return rtrim($message)."\n\nView invoice / receipt:\n".$order->publicReceiptUrl();
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
            return '254'.$digits;
        }
        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            return '254'.substr($digits, 1);
        }

        return $customerPhone ?: '—';
    }

    protected function resolveMpesaPhone(string $message, string $customerPhone): ?string
    {
        $lower = mb_strtolower($message);
        if (in_array($lower, ['yes', 'ok', 'same', 'this one', 'use this', 'current'], true)) {
            return $customerPhone ?: null;
        }
        $digits = preg_replace('/\D/', '', $message);
        if (strlen($digits) >= 9) {
            if (strlen($digits) === 9 && str_starts_with($digits, '7')) {
                return '254'.$digits;
            }
            if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
                return '254'.substr($digits, 1);
            }
            if (strlen($digits) >= 12) {
                return $digits;
            }

            return '254'.$digits;
        }

        return null;
    }

    protected function wantsCancel(string $lower): bool
    {
        return in_array($lower, ['cancel', 'start over', 'nevermind', 'never mind', 'stop'], true)
            || str_contains($lower, 'cancel order');
    }

    protected function wantsBackToCatalog(string $lower): bool
    {
        return in_array($lower, ['back', 'return', 'menu', '0'], true);
    }

    protected function wantsStartOrder(string $lower): bool
    {
        return $lower === '2' || $lower === 'order' || $lower === 'place order'
            || str_contains($lower, 'i want to order') || str_contains($lower, 'place an order');
    }

    protected function wantsCatalogOrPrices(string $lower): bool
    {
        if (str_contains($lower, 'price') || str_contains($lower, 'how much')) {
            return true;
        }
        if (str_contains($lower, 'catalog') || str_contains($lower, 'menu') || str_contains($lower, 'products') || str_contains($lower, 'list')) {
            return true;
        }

        return false;
    }

    /**
     * Show the catalog again while keeping the cart (browse / prices intents during picking).
     */
    protected function wantsCatalogRefresh(string $lower): bool
    {
        if ($this->looksLikeLocationOrShopInfoQuestion($lower)) {
            return false;
        }
        if ($this->wantsCatalogOrPrices($lower)) {
            return true;
        }
        if (in_array($lower, ['shop', 'browse', 'store', 'catalogue'], true)) {
            return true;
        }
        if (str_contains($lower, 'what do you sell') || str_contains($lower, 'what do you have')) {
            return true;
        }
        if (str_contains($lower, 'which products') || str_contains($lower, 'which items')) {
            return true;
        }
        if (str_contains($lower, 'show me') && (str_contains($lower, 'product') || str_contains($lower, 'catalog') || str_contains($lower, 'list'))) {
            return true;
        }

        return false;
    }

    /**
     * "Where is your shop?" style messages — answer via AI/FAQ, not as a catalog refresh.
     */
    protected function looksLikeLocationOrShopInfoQuestion(string $lower): bool
    {
        if (str_contains($lower, 'where') && (str_contains($lower, 'shop') || str_contains($lower, 'store') || str_contains($lower, 'located') || str_contains($lower, 'find you') || str_contains($lower, 'your address'))) {
            return true;
        }
        if (str_contains($lower, 'where are you') || str_contains($lower, 'where is the')) {
            return true;
        }

        return false;
    }

    /**
     * Let {@see AIReplyService} answer so the customer can ask questions without leaving the order state.
     */
    protected function shouldDelegateOrderStepToAssistant(string $lower, string $trimmed): bool
    {
        if ($trimmed === '') {
            return false;
        }
        if (preg_match('/^\d+$/', $trimmed)) {
            return false;
        }
        if ($this->looksLikeGreetingOnly($lower)) {
            return true;
        }
        if ($this->looksLikeLocationOrShopInfoQuestion($lower)) {
            return true;
        }
        if (str_contains($trimmed, '?')) {
            return true;
        }
        $patterns = [
            '/\b(thanks|thank you|thx|ty)\b/i',
            '/\b(do you|did you|are you|can you|could you|would you|will you|is there|can i|could i|should i)\b/i',
            '/\b(contact|email|whatsapp|hours|opening|closed|open|refund)\b/i',
            '/\b(help|support|agent|human|speak|representative)\b/i',
            '/\b(how do|how can|why|when|who)\b/i',
            '/\b(question|wondering|tell me|explain)\b/i',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $lower)) {
                return true;
            }
        }
        if (preg_match('/\bwhat\b/i', $lower)) {
            if (str_contains($lower, 'what do you sell') || str_contains($lower, 'what do you have')) {
                return false;
            }

            return true;
        }
        if (preg_match('/\bwhere\b/i', $lower)) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    protected function refreshCatalogInProductStep(Chat $chat, Company $company, array $draft): string
    {
        $draft = $this->withCatalogIds($company, $draft);
        $this->setStep($chat, self::STEP_PRODUCT, $draft);
        $list = $this->formatNumberedProductList($company)."\n\n".$this->numberedOrderInstructions();
        $items = $draft['items'] ?? [];
        if ($items !== []) {
            $summary = $this->formatDraftSummary($draft);

            return "Here's our catalog again.\n\n{$summary}\n\n{$list}";
        }

        return $list;
    }

    protected function productStepUnrecognizedReply(): string
    {
        return 'I didn\'t catch that as a product number or quantity. Reply with a number from the list, text like "2 x ProductName", or 0 / "done" when your cart is ready. You can also ask a question about our shop or products — say "cancel" to stop your order.';
    }

    /**
     * Keep in sync with {@see AIReplyService::looksLikeGreeting} so short words like "hi"
     * are not passed to product matching (e.g. "hi" matching inside "shirt").
     */
    protected function looksLikeGreetingOnly(string $lower): bool
    {
        $greetings = ['hi', 'hello', 'hey', 'good morning', 'good afternoon', 'good evening', 'salam', 'marhaba', 'hola'];
        foreach ($greetings as $g) {
            if ($lower === $g || str_starts_with($lower, $g.' ') || str_starts_with($lower, $g.',')) {
                return true;
            }
        }

        return false;
    }

    protected function wantsDone(string $lower): bool
    {
        return in_array($lower, ['done', 'that\'s all', 'thats all', 'finish', 'next', '0'], true);
    }

    protected function wantsConfirm(string $lower): bool
    {
        return in_array($lower, ['confirm', 'yes', 'place order', 'confirm order', '1'], true);
    }

    protected function wantsDiscardConfirmOrder(string $lower): bool
    {
        return $lower === '2';
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
        $products = Product::with('variants')
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->get();
        if ($products->isEmpty()) {
            return null;
        }

        $text = trim(str_replace('*', ' x ', $text));
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        if (preg_match('/^(\d+)\s*[x×]\s*(.+)$/iu', $text, $m)) {
            $qty = (int) $m[1];
            $namePart = trim($m[2]);
            if ($qty < 1) {
                return null;
            }
            $product = $this->matchProduct($products, $namePart);
            if ($product && $this->productHasActiveVariants($product)) {
                return null;
            }
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
            if ($product && $this->productHasActiveVariants($product)) {
                return null;
            }
            if ($product) {
                return [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'price' => (float) $product->price,
                    'quantity' => $qty,
                ];
            }
        }

        if (preg_match('/^(.+?)\s+(?:quantity|qty|quatity)\s*(\d+)\s*$/iu', $text, $m)) {
            $namePart = trim($m[1]);
            $qty = (int) $m[2];
            if ($qty < 1) {
                return null;
            }
            $product = $this->matchProduct($products, $namePart);
            if ($product && $this->productHasActiveVariants($product)) {
                return null;
            }
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
            if ($this->productHasActiveVariants($product)) {
                return null;
            }

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
        return $this->formatNumberedProductList($company);
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
            $lines[] = '• '.$item['name'].' x '.$item['quantity'].' = '.number_format($sub, 2);
        }
        $lines[] = 'Total: '.number_format($total, 2);

        return implode("\n", $lines);
    }

    protected function createOrderFromDraft(Company $company, Chat $chat, array $draft, string $customerName, string $customerPhone): Order
    {
        $items = $draft['items'] ?? [];
        $total = 0.0;
        foreach ($items as $item) {
            $total += ($item['price'] ?? 0) * ($item['quantity'] ?? 0);
        }

        $orderNumber = 'ORD-'.strtoupper(Str::random(8));
        while (Order::where('order_number', $orderNumber)->exists()) {
            $orderNumber = 'ORD-'.strtoupper(Str::random(8));
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

    protected function numberedOrderInstructions(): string
    {
        return 'Reply with a product number to add it (quantity comes next). You can also type e.g. "2 x ProductName" or "2*ProductName". Products with options will ask you to pick a variant first. When your cart is ready, reply 0 or "done" for delivery address.';
    }
}
