<?php

namespace App\Services;

use App\Models\Chat;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Support\MoneyFormatter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
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

    /**
     * Dashboard-created order continuation.
     */
    public const STEP_EXISTING_ORDER_ADDRESS = 'existing_order_address';

    public const STEP_EXISTING_ORDER_PAYMENT_METHOD = 'existing_order_payment_method';

    public const STEP_EXISTING_ORDER_PROMPT = 'existing_order_prompt';

    public function __construct(
        protected OrderPaymentService $orderPayment
    ) {}

    protected function formatMoney(Company $company, float $amount): string
    {
        $company->loadMissing('settings');

        return MoneyFormatter::format($amount, $company->settings?->display_currency);
    }

    protected function formatMoneyForOrder(Order $order, float $amount): string
    {
        $order->loadMissing('company.settings');
        $c = $order->company;
        if (! $c) {
            return MoneyFormatter::format($amount, null);
        }

        return $this->formatMoney($c, $amount);
    }

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

        if ($step === self::STEP_EXISTING_ORDER_PROMPT) {
            $order = isset($draft['order_id']) ? Order::find((int) $draft['order_id']) : null;
            if (! $order) {
                $this->clearState($chat);

                return 'Something went wrong. Please ask us to resend your invoice or start a new order.';
            }
            if (in_array($lower, ['2', 'cancel', 'no'], true)) {
                $order->update(['status' => 'cancelled']);
                $this->clearState($chat);

                return 'Order cancelled. If you change your mind, reply "order" or "2" to start a new order.';
            }
            if (in_array($lower, ['1', 'continue', 'pay', 'yes', 'proceed'], true)) {
                $this->setStep($chat, self::STEP_EXISTING_ORDER_ADDRESS, ['order_id' => $order->id]);

                return 'Great — please reply with your delivery address to continue.';
            }

            return "Reply with:\n1 - Continue and pay\n2 - Cancel";
        }

        if ($this->wantsCancel($lower)) {
            $this->clearState($chat);

            return 'Order cancelled. Reply with "order" or "2" when you\'re ready to place a new order.';
        }

        if ($step === self::STEP_EXISTING_ORDER_ADDRESS) {
            $order = isset($draft['order_id']) ? Order::find((int) $draft['order_id']) : null;
            if (! $order) {
                $this->clearState($chat);

                return 'Something went wrong. Please ask us to resend your invoice or start a new order.';
            }
            if ($this->shouldDelegateOrderStepToAssistant($lower, $trimmed)) {
                return null;
            }
            $address = trim($messageText);
            if (strlen($address) < 3) {
                return 'Please provide a valid delivery address (at least a few characters).';
            }
            $order->update(['delivery_address' => $address]);
            $draft['order_id'] = $order->id;
            $this->setStep($chat, self::STEP_EXISTING_ORDER_PAYMENT_METHOD, $draft);

            $pay = $this->resolvePaymentAcceptance($company->settings);

            return "Delivery address saved: {$address}\n\n".$this->formatPaymentMethodPrompt($order, $pay['acceptMpesa'], $pay['acceptStripe'], $pay['acceptPaystack'], $pay['acceptManual']);
        }

        if ($step === self::STEP_EXISTING_ORDER_PAYMENT_METHOD) {
            $order = isset($draft['order_id']) ? Order::find((int) $draft['order_id']) : null;
            if (! $order) {
                $this->clearState($chat);

                return 'Something went wrong. Please ask us to resend your invoice or start a new order.';
            }
            $pay = $this->resolvePaymentAcceptance($company->settings);
            $method = $this->matchPaymentMethod($lower, $pay['acceptMpesa'], $pay['acceptStripe'], $pay['acceptPaystack'], $pay['acceptManual']);

            if ($method === 'manual') {
                $this->clearState($chat);

                return $this->formatOrderWithManualPaymentInstructions($order);
            }
            if ($method === 'mpesa') {
                $draft['payment_method'] = 'mpesa';
                $this->setStep($chat, self::STEP_MPESA_PHONE, $draft);
                $displayPhone = $this->formatPhoneForDisplay($customerPhone);

                return "We'll send an M-Pesa payment request to your phone.\n\nReply with:\n1 - Send to this number ({$displayPhone})\n2 - Use a different number";
            }
            if ($method === 'stripe') {
                return $this->handleStripePayment($order, $chat);
            }
            if ($method === 'paystack') {
                return $this->handlePaystackPayment($order, $chat);
            }
            if ($this->shouldDelegateOrderStepToAssistant($lower, $trimmed)) {
                return null;
            }

            return $this->formatPaymentMethodPrompt($order, $pay['acceptMpesa'], $pay['acceptStripe'], $pay['acceptPaystack'], $pay['acceptManual'], true);
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
                    return $this->formatAddedToCartMessage($company, (string) $parsed['name'], (int) $parsed['quantity'], $draft);
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
                $draft = $this->stripPickingDraft($draft);
                if ($this->draftRequiresDeliveryAddress($draft)) {
                    $this->setStep($chat, self::STEP_ADDRESS, $draft);

                    return 'What is your delivery address?';
                }
                $this->setStep($chat, self::STEP_CONFIRM, $draft);
                $summary = $this->formatDraftSummary($company, $draft);

                return "{$summary}\n\nThis order does not need a delivery address.\n\nWhat would you like to do next?\n1 - Confirm & place order\n2 - Cancel";
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
                return $this->formatAddedToCartMessage($company, (string) $parsed['name'], (int) $parsed['quantity'], $draft);
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
            $summary = $this->formatDraftSummary($company, $draft);

            return "📍 Delivery address:\n{$address}\n\n{$summary}\n\nWhat would you like to do next?\n1 - Confirm & place order\n2 - Cancel";
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

                    return $this->withReceipt($order, "Order confirmed! Your order number is: {$order->order_number}. Total: ".$this->formatMoneyForOrder($order, (float) $order->total).". We'll prepare it and contact you for delivery.");
                }
                $pay = $this->resolvePaymentAcceptance($settings);
                if ($pay['acceptMpesa'] || $pay['acceptStripe'] || $pay['acceptPaystack']) {
                    $draft['order_id'] = $order->id;
                    $this->setStep($chat, self::STEP_PAYMENT_METHOD, $draft);

                    return $this->formatPaymentMethodPrompt($order, $pay['acceptMpesa'], $pay['acceptStripe'], $pay['acceptPaystack'], $pay['acceptManual']);
                }
                $acceptManual = $pay['acceptManual'];
                if ($acceptManual) {
                    $this->clearState($chat);

                    return $this->formatOrderWithManualPaymentInstructions($order);
                }
                $this->clearState($chat);

                return $this->withReceipt($order, "Order confirmed! Your order number is: {$order->order_number}. Total: ".$this->formatMoneyForOrder($order, (float) $order->total).". We'll prepare it and contact you for delivery.");
            }
        }

        if ($step === self::STEP_PAYMENT_METHOD) {
            $order = isset($draft['order_id']) ? Order::find($draft['order_id']) : null;
            if (! $order) {
                $this->clearState($chat);

                return 'Something went wrong. Please start over with "order" or "2".';
            }
            $pay = $this->resolvePaymentAcceptance($company->settings);
            $method = $this->matchPaymentMethod($lower, $pay['acceptMpesa'], $pay['acceptStripe'], $pay['acceptPaystack'], $pay['acceptManual']);

            if ($method === 'manual') {
                $this->clearState($chat);

                return $this->formatOrderWithManualPaymentInstructions($order);
            }
            if ($method === 'mpesa') {
                $draft['payment_method'] = 'mpesa';
                $this->setStep($chat, self::STEP_MPESA_PHONE, $draft);
                $displayPhone = $this->formatPhoneForDisplay($customerPhone);

                return "We'll send an M-Pesa payment request to your phone.\n\nReply with:\n1 - Send to this number ({$displayPhone})\n2 - Use a different number";
            }
            if ($method === 'stripe') {
                return $this->handleStripePayment($order, $chat, true);
            }
            if ($method === 'paystack') {
                return $this->handlePaystackPayment($order, $chat, true);
            }
            if ($this->shouldDelegateOrderStepToAssistant($lower, $trimmed)) {
                return null;
            }

            return $this->formatPaymentMethodPrompt($order, $pay['acceptMpesa'], $pay['acceptStripe'], $pay['acceptPaystack'], $pay['acceptManual'], true);
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

            // Two-step choice:
            // 1) Reply "1" to use current WhatsApp number
            // 2) Reply "2" to provide a different M-Pesa number (then send it)
            $awaiting = (bool) ($draft['mpesa_awaiting_phone'] ?? false);

            if (! $awaiting) {
                if (in_array($lower, ['1', 'yes', 'ok', 'same', 'this one', 'use this', 'current'], true)) {
                    $phone = $this->resolveMpesaPhone('yes', $customerPhone);
                    if (! $phone) {
                        $displayPhone = $this->formatPhoneForDisplay($customerPhone);
                        return "I don't have a valid phone number for this chat.\n\nReply with your M-Pesa number (e.g. 254712345678) to receive the prompt.\nCurrent: {$displayPhone}";
                    }
                    $result = $this->orderPayment->sendStkPushForOrder($order, $phone);
                    $this->clearState($chat);
                    if ($result['success']) {
                        return $this->withReceipt($order, "We've sent an M-Pesa payment request to your phone. Enter your M-Pesa PIN to complete payment. You'll get a confirmation here once payment is received.");
                    }

                    return $this->withReceipt($order, "Order #{$order->order_number} confirmed. Total: ".$this->formatMoneyForOrder($order, (float) $order->total).". We couldn't send M-Pesa right now (".($result['error'] ?? 'please try again later')."). We'll contact you for payment.");
                }

                if (in_array($lower, ['2', 'different', 'another', 'other'], true)) {
                    $draft['mpesa_awaiting_phone'] = true;
                    $this->setStep($chat, self::STEP_MPESA_PHONE, $draft);

                    return 'Okay — reply with the M-Pesa phone number to receive the prompt (e.g. 254712345678).';
                }
            }

            // If they picked "2" previously, or they just typed a phone directly, parse it.
            $phone = $this->resolveMpesaPhone(trim($messageText), $customerPhone);
            if (! $phone) {
                $displayPhone = $this->formatPhoneForDisplay($customerPhone);
                return "Reply with:\n1 - Send to this number ({$displayPhone})\n2 - Use a different number";
            }

            $result = $this->orderPayment->sendStkPushForOrder($order, $phone);
            $this->clearState($chat);
            if ($result['success']) {
                return $this->withReceipt($order, "We've sent an M-Pesa payment request to your phone. Enter your M-Pesa PIN to complete payment. You'll get a confirmation here once payment is received.");
            }

            return $this->withReceipt($order, "Order #{$order->order_number} confirmed. Total: ".$this->formatMoneyForOrder($order, (float) $order->total).". We couldn't send M-Pesa right now (".($result['error'] ?? 'please try again later')."). We'll contact you for payment.");
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

            return $this->formatVariantListMessage($company, $product);
        }
        $draft['pending_product_id'] = $product->id;
        unset($draft['pending_variant_id'], $draft['variant_ids']);
        $this->setStep($chat, self::STEP_PRODUCT_QTY, $draft);

        return "✅ Selected:\n{$product->name}\nPrice: ".$this->formatMoney($company, (float) $product->price)."\n\nHow many would you like?\nReply with a number (e.g. 2)\n\nReply \"back\" to return to the product list.";
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

        return "✅ Selected:\n{$product->name} — {$variant->label}\nPrice: ".$this->formatMoney($company, (float) $variant->price)."\n\nHow many would you like?\nReply with a number (e.g. 2)\n\nReply \"back\" to change the option.";
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

                    return $this->formatVariantListMessage($company, $product);
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
            if ($product->usesInventory() && $variant->stock < $qty) {
                return "Only {$variant->stock} in stock for this option. Enter a smaller quantity or \"back\".";
            }
            $poolError = app(\App\Services\DigitalAccessService::class)->assertPoolCapacity($product, $qty);
            if ($poolError) {
                return $poolError.' Reply "back" to choose another item.';
            }
            $line = [
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'name' => $product->name.' — '.$variant->label,
                'price' => (float) $variant->price,
                'quantity' => $qty,
                'fulfillment_data' => $product->fulfillmentSnapshot($variant),
            ];
        } else {
            if ($this->productHasActiveVariants($product)) {
                return 'This product requires choosing an option first. Reply "back".';
            }
            if ($product->usesInventory() && $product->stock < $qty) {
                return "Only {$product->stock} in stock. Enter a smaller quantity or \"back\".";
            }
            $poolError = app(\App\Services\DigitalAccessService::class)->assertPoolCapacity($product, $qty);
            if ($poolError) {
                return $poolError.' Reply "back" to choose another item.';
            }
            $line = [
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'quantity' => $qty,
                'fulfillment_data' => $product->fulfillmentSnapshot(),
            ];
        }

        $draft['items'] = $draft['items'] ?? [];
        $draft['items'][] = $line;
        unset($draft['pending_product_id'], $draft['pending_variant_id'], $draft['variant_ids']);
        $draft = $this->withCatalogIds($company, $draft);
        $this->setStep($chat, self::STEP_PRODUCT, $draft);
        return $this->formatAddedToCartMessage($company, (string) $line['name'], (int) $qty, $draft);
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
            ->with([
                'images' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id'),
                'variants' => fn ($q) => $q
                    ->where('status', 'active')
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->with(['images' => fn ($iq) => $iq->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id')]),
            ])
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
            ->with([
                'images' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id'),
                'variants' => fn ($q) => $q
                    ->where('status', 'active')
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->with(['images' => fn ($iq) => $iq->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id')]),
            ])
            ->first();

        return $p;
    }

    protected function productHasActiveVariants(Product $product): bool
    {
        return $product->variants->where('status', 'active')->isNotEmpty();
    }

    protected function formatVariantListMessage(Company $company, Product $product): string
    {
        $lines = ["Options for {$product->name}:\n"];
        $i = 1;
        foreach ($product->variants->where('status', 'active') as $v) {
            $lines[] = "{$i}. {$v->label} — ".$this->formatMoney($company, (float) $v->price);
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
        $lines = ["🛍️ Product list\n(Reply with a number to add an item)\n"];
        $i = 1;
        foreach ($products as $p) {
            if ($this->productHasActiveVariants($p)) {
                $min = (float) $p->variants->where('status', 'active')->min('price');
                $priceLabel = 'from '.$this->formatMoney($company, $min);
            } else {
                $priceLabel = $this->formatMoney($company, (float) $p->price);
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
        return "Next steps:\n1 - Add another item (reply with a product number)\n2 - Add with quantity (example: 2 x Sugar)\n0 - Done (enter delivery address)";
    }

    /**
     * Standard WhatsApp-friendly "added to cart" template.
     *
     * @param  array<string, mixed>  $draft
     */
    protected function formatAddedToCartMessage(Company $company, string $name, int $quantity, array $draft): string
    {
        $summary = $this->formatDraftSummary($company, $draft);

        return "✅ Added to cart\n{$name} x {$quantity}\n\n{$summary}\n\n".$this->afterAddItemInstructions();
    }

    /**
     * @return array{acceptMpesa: bool, acceptStripe: bool, acceptPaystack: bool, acceptManual: bool}
     */
    protected function resolvePaymentAcceptance(?\App\Models\CompanySetting $settings): array
    {
        return [
            'acceptMpesa' => $settings && $settings->orders_accept_mpesa && (MpesaService::isEnabled() || $settings->hasOrderPaymentMpesaConfig()),
            'acceptStripe' => $settings && $settings->orders_accept_stripe && (StripeService::isEnabled() || $settings->hasOrderPaymentStripeConfig()),
            'acceptPaystack' => $settings && $settings->orders_accept_paystack && PaystackService::isEnabled(),
            'acceptManual' => $settings && $settings->hasOrderPaymentManualInstructions(),
        ];
    }

    /**
     * @return list<string>
     */
    protected function paymentMethodKeys(bool $acceptMpesa, bool $acceptStripe, bool $acceptPaystack, bool $acceptManual): array
    {
        $keys = [];
        if ($acceptMpesa) {
            $keys[] = 'mpesa';
        }
        if ($acceptStripe) {
            $keys[] = 'stripe';
        }
        if ($acceptPaystack) {
            $keys[] = 'paystack';
        }
        if ($acceptManual) {
            $keys[] = 'manual';
        }

        return $keys;
    }

    protected function matchPaymentMethod(string $lower, bool $acceptMpesa, bool $acceptStripe, bool $acceptPaystack, bool $acceptManual): ?string
    {
        $keys = $this->paymentMethodKeys($acceptMpesa, $acceptStripe, $acceptPaystack, $acceptManual);
        if (preg_match('/^\d+$/', $lower)) {
            $idx = (int) $lower - 1;

            return $keys[$idx] ?? null;
        }
        if ($acceptManual && $this->wantsManual($lower)) {
            return 'manual';
        }
        if ($acceptMpesa && $this->wantsMpesaText($lower)) {
            return 'mpesa';
        }
        if ($acceptStripe && $this->wantsStripeText($lower)) {
            return 'stripe';
        }
        if ($acceptPaystack && $this->wantsPaystackText($lower)) {
            return 'paystack';
        }

        return null;
    }

    protected function formatPaymentMethodPrompt(Order $order, bool $acceptMpesa, bool $acceptStripe, bool $acceptPaystack, bool $acceptManual = false, bool $invalid = false): string
    {
        $line = 'Order #'.$order->order_number.' – Total: '.$this->formatMoneyForOrder($order, (float) $order->total).".\n\nHow would you like to pay?";
        $opts = [];
        $n = 1;
        if ($acceptMpesa) {
            $opts[] = "{$n}. M-Pesa (pay on your phone)";
            $n++;
        }
        if ($acceptStripe) {
            $opts[] = "{$n}. Card (pay online)";
            $n++;
        }
        if ($acceptPaystack) {
            $opts[] = "{$n}. Paystack (pay online)";
            $n++;
        }
        if ($acceptManual) {
            $opts[] = "{$n}. Pay manually (bank / other details)";
        }
        $line .= "\n".implode("\n", $opts);
        if ($invalid) {
            $count = count($opts);
            $line .= $count > 1
                ? "\n\nPlease reply with a number 1–{$count} or name the payment method."
                : "\n\nPlease reply with 1 or name the payment method.";
        }

        return $this->withReceipt($order, $line);
    }

    protected function handleStripePayment(Order $order, Chat $chat, bool $confirmedOrder = false): string
    {
        $result = $this->orderPayment->createStripePaymentLinkForOrder($order);
        $this->clearState($chat);
        if ($result['success'] && ! empty($result['url'])) {
            return $this->withReceipt($order, "Order #{$order->order_number} – Pay by card here: {$result['url']}\n\nReply once you've completed payment. Thank you!");
        }
        $suffix = ' ('.($result['error'] ?? 'Payment link unavailable.').')';
        if ($confirmedOrder) {
            return $this->withReceipt($order, "Order confirmed! Your order number is: {$order->order_number}. Total: ".$this->formatMoneyForOrder($order, (float) $order->total).". We'll prepare it and contact you for delivery.{$suffix}");
        }

        return $this->withReceipt($order, "Order #{$order->order_number} confirmed. Total: ".$this->formatMoneyForOrder($order, (float) $order->total).".{$suffix}");
    }

    protected function handlePaystackPayment(Order $order, Chat $chat, bool $confirmedOrder = false): string
    {
        $result = $this->orderPayment->createPaystackPaymentLinkForOrder($order);
        $this->clearState($chat);
        if ($result['success'] && ! empty($result['url'])) {
            return $this->withReceipt($order, "Order #{$order->order_number} – Pay here: {$result['url']}\n\nReply once you've completed payment. Thank you!");
        }
        $suffix = ' ('.($result['error'] ?? 'Payment link unavailable.').')';
        if ($confirmedOrder) {
            return $this->withReceipt($order, "Order confirmed! Your order number is: {$order->order_number}. Total: ".$this->formatMoneyForOrder($order, (float) $order->total).". We'll prepare it and contact you for delivery.{$suffix}");
        }

        return $this->withReceipt($order, "Order #{$order->order_number} confirmed. Total: ".$this->formatMoneyForOrder($order, (float) $order->total).".{$suffix}");
    }

    protected function formatOrderWithManualPaymentInstructions(Order $order): string
    {
        $settings = $order->company?->settings;
        $instructions = $settings && $settings->hasOrderPaymentManualInstructions()
            ? trim($settings->order_payment_manual_instructions)
            : '';
        $total = $this->formatMoneyForOrder($order, (float) $order->total);
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

    protected function wantsMpesaText(string $lower): bool
    {
        return in_array($lower, ['mpesa', 'm-pesa', 'mobile', 'phone'], true)
            || str_contains($lower, 'mpesa') || str_contains($lower, 'm-pesa');
    }

    protected function wantsStripeText(string $lower): bool
    {
        return in_array($lower, ['card', 'stripe', 'pay online', 'online'], true)
            || str_contains($lower, 'card') || str_contains($lower, 'pay online');
    }

    protected function wantsPaystackText(string $lower): bool
    {
        return in_array($lower, ['paystack', 'link'], true) || str_contains($lower, 'paystack');
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
        if ($lower === '1') {
            return true;
        }
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
            $summary = $this->formatDraftSummary($company, $draft);

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
        $products = Product::query()
            ->with([
                'images' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id'),
                'variants' => fn ($q) => $q->with(['images' => fn ($iq) => $iq->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id')]),
            ])
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
                    'fulfillment_data' => $product->fulfillmentSnapshot(),
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
                    'fulfillment_data' => $product->fulfillmentSnapshot(),
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
                    'fulfillment_data' => $product->fulfillmentSnapshot(),
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
                'fulfillment_data' => $product->fulfillmentSnapshot(),
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

    protected function formatDraftSummary(Company $company, array $draft): string
    {
        $items = $draft['items'] ?? [];
        if (empty($items)) {
            return 'Your cart is empty for now.';
        }
        $lines = ['Here’s your cart so far:'];
        $total = 0.0;
        foreach ($items as $item) {
            $sub = ($item['price'] ?? 0) * ($item['quantity'] ?? 0);
            $total += $sub;
            $lines[] = '• '.$item['name'].' x '.$item['quantity'].' — '.$this->formatMoney($company, (float) $sub);
        }
        $lines[] = 'Total: '.$this->formatMoney($company, (float) $total);
        if (! $this->draftRequiresDeliveryAddress($draft)) {
            $lines[] = 'Delivery: No physical shipping needed';
        }

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
                'product_id' => $item['product_id'] ?? null,
                'product_variant_id' => $item['product_variant_id'] ?? null,
                'name' => $item['name'] ?? 'Item',
                'quantity' => (int) ($item['quantity'] ?? 1),
                'price' => (float) ($item['price'] ?? 0),
                'fulfillment_data' => $item['fulfillment_data'] ?? null,
            ]);
        }

        return $order;
    }

    protected function numberedOrderInstructions(): string
    {
        return "How to order:\n1) Reply with a product number (we’ll ask for quantity)\n2) Or type: 2 x ProductName\n0) Done (we’ll continue checkout)\n\nTip: Reply \"back\" anytime to return to the list.";
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    protected function draftRequiresDeliveryAddress(array $draft): bool
    {
        $items = $draft['items'] ?? [];
        foreach ($items as $item) {
            $data = $item['fulfillment_data'] ?? null;
            if (! is_array($data)) {
                return true;
            }
            if (($data['requiresDeliveryAddress'] ?? true) === true) {
                return true;
            }
        }

        return false;
    }

    public function beginExistingOrderCheckout(Chat $chat, Order $order): string
    {
        $draft = [
            'order_id' => $order->id,
        ];
        $this->setStep($chat, self::STEP_EXISTING_ORDER_PROMPT, $draft);

        return "Reply with:\n1 - Continue and pay\n2 - Cancel";
    }

    /**
     * Resolve currently selected catalog item image for preview.
     *
     * @return array{url: string, caption: string}|null
     */
    public function resolveCurrentSelectionImage(Chat $chat, Company $company): ?array
    {
        $draft = $this->getDraft($chat);
        $productId = isset($draft['pending_product_id']) ? (int) $draft['pending_product_id'] : null;
        if (! $productId) {
            return null;
        }

        $product = $this->getCatalogProductById($company, $productId);
        if (! $product) {
            return null;
        }

        $variant = null;
        $variantId = isset($draft['pending_variant_id']) ? (int) $draft['pending_variant_id'] : null;
        if ($variantId) {
            $variant = $product->variants->firstWhere('id', $variantId);
        }

        $imageUrl = $this->resolveImageUrlForSelection($product, $variant);
        if (! $imageUrl) {
            return null;
        }

        $caption = $variant
            ? $product->name.' - '.$variant->label
            : $product->name;

        return [
            'url' => $imageUrl,
            'caption' => $caption,
        ];
    }

    private function resolveImageUrlForSelection(Product $product, ?ProductVariant $variant): ?string
    {
        $path = null;
        if ($variant) {
            $path = $this->resolvePrimaryImagePath($variant->images);
        }
        if (! $path) {
            $path = $this->resolvePrimaryImagePath($product->images);
        }
        if (! $path && $product->image) {
            $path = $product->image;
        }

        return $path ? Storage::url($path) : null;
    }

    private function resolvePrimaryImagePath(Collection $images): ?string
    {
        if ($images->isEmpty()) {
            return null;
        }

        /** @var ProductImage|null $image */
        $image = $images->firstWhere('is_primary', true) ?? $images->first();

        return $image?->path;
    }
}
