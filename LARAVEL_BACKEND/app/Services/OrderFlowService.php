<?php

namespace App\Services;

use App\DTOs\IntentResult;
use App\Enums\CommerceIntent;
use App\Models\Chat;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Services\Orders\SpamOrderGuard;
use App\Services\Orders\TaxCalculationService;
use App\Services\Storefront\DeliveryFeeService;
use App\Support\MoneyFormatter;
use Carbon\Carbon;
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
        protected OrderPaymentService $orderPayment,
        protected TaxCalculationService $taxCalculator,
        protected DeliveryFeeService $deliveryFees,
        protected SpamOrderGuard $spamGuard,
    ) {}

    protected function formatMoney(Company $company, float $amount): string
    {
        $company->loadMissing('settings');

        return MoneyFormatter::formatFromSettings($amount, $company->settings);
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
     * Subtotal / tax / total block for WhatsApp (or just "Total: …" when no tax).
     */
    protected function formatOrderMoneySummary(Order $order): string
    {
        $calc = [
            'subtotal' => (float) ($order->subtotal ?? $order->total),
            'tax_total' => (float) ($order->tax_total ?? 0),
            'total' => (float) $order->total,
            'tax_breakdown' => is_array($order->tax_breakdown) ? $order->tax_breakdown : [],
        ];

        return implode("\n", $this->taxCalculator->formatSummaryLines(
            $calc,
            fn (float $amount) => $this->formatMoneyForOrder($order, $amount)
        ));
    }

    /**
     * Public, standalone summary formatter — accepts either a persisted Order or a raw
     * order_draft array so callers outside the chat flow (tests, APIs) get consistent output.
     *
     * @param  Order|array<string, mixed>  $draft
     */
    public function formatOrderSummary(Order|array $draft): string
    {
        if ($draft instanceof Order) {
            $order = $draft;
            $order->loadMissing('orderProducts');
            $lines = ['Order #'.$order->order_number];
            foreach ($order->orderProducts as $item) {
                $qty = (int) $item->quantity;
                $unit = (float) $item->price;
                $lines[] = '• '.$item->name.' — '.$qty.' x '.$this->formatMoneyForOrder($order, $unit).' = '.$this->formatMoneyForOrder($order, round($unit * $qty, 2));
            }
            $lines[] = 'Subtotal: '.$this->formatMoneyForOrder($order, (float) ($order->subtotal ?? $order->total));
            if ((float) ($order->delivery_fee ?? 0) > 0) {
                $lines[] = 'Delivery fee: '.$this->formatMoneyForOrder($order, (float) $order->delivery_fee);
            }
            foreach (is_array($order->tax_breakdown) ? $order->tax_breakdown : [] as $row) {
                $label = $row['code'] ?? $row['name'] ?? 'Tax';
                $lines[] = "{$label}: ".$this->formatMoneyForOrder($order, (float) ($row['amount'] ?? 0));
            }
            $lines[] = 'Total: '.$this->formatMoneyForOrder($order, (float) $order->total);
            $lines[] = 'Fulfillment: '.$this->fulfillmentLabel($order->fulfillment_type);
            if ($order->scheduled_for) {
                $lines[] = 'Scheduled for: '.$order->scheduled_for->toDayDateTimeString();
            }

            return implode("\n", $lines);
        }

        if (isset($draft['company_id']) && ($company = Company::find((int) $draft['company_id']))) {
            return $this->formatDraftSummary($company, $draft);
        }

        $items = $draft['items'] ?? [];
        $lines = ['Here’s your order summary:'];
        $subtotal = 0.0;
        foreach ($items as $item) {
            $qty = (int) ($item['quantity'] ?? 0);
            $unit = (float) ($item['price'] ?? 0);
            $lineTotal = round($unit * $qty, 2);
            $subtotal += $lineTotal;
            $lines[] = '• '.($item['name'] ?? 'Item').' — '.$qty.' x '.MoneyFormatter::format($unit).' = '.MoneyFormatter::format($lineTotal);
        }
        $subtotal = round($subtotal, 2);
        $lines[] = 'Subtotal: '.MoneyFormatter::format($subtotal);
        $deliveryFee = (float) ($draft['delivery_fee'] ?? 0);
        if ($deliveryFee > 0) {
            $lines[] = 'Delivery fee: '.MoneyFormatter::format($deliveryFee);
        }
        $lines[] = 'Total: '.MoneyFormatter::format(round($subtotal + $deliveryFee, 2));
        if (! empty($draft['fulfillment_type'])) {
            $lines[] = 'Fulfillment: '.$this->fulfillmentLabel((string) $draft['fulfillment_type']);
        }
        if (! empty($draft['scheduled_for'])) {
            try {
                $lines[] = 'Scheduled for: '.Carbon::parse((string) $draft['scheduled_for'])->toDayDateTimeString();
            } catch (\Throwable) {
                // ignore unparsable scheduled_for values in summary display
            }
        }

        return implode("\n", $lines);
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

     * Execute a cart mutation callback under a Redis/Cache lock on the chat instance.
     * Lock key: wa_chat_lock:{chat_id}, TTL: 10 seconds.
     * Reloads a fresh Chat model from DB inside the lock to prevent stale state overwrites.
     */
    public function withChatLock(Chat $chat, callable $callback): mixed
    {
        $lockKey = "wa_chat_lock:{$chat->id}";
        $lock = \Illuminate\Support\Facades\Cache::lock($lockKey, 10);

        return $lock->block(5, function () use ($chat, $callback) {
            $freshChat = $chat->fresh() ?? $chat;

            return $callback($freshChat);
        });
    }

    /**
     * Handle Phase 1 structured cart intents (ADD_TO_CART, REMOVE_FROM_CART, UPDATE_QUANTITY)
     * directly without natural language parsing or regex keyword heuristics.
     *
     * @return array{success: bool, message: string}|null
     */
    public function handleStructuredCartIntent(IntentResult $intent, Chat $chat, Company $company): ?array
    {
        return $this->withChatLock($chat, function (Chat $freshChat) use ($intent, $company) {
            $draft = $this->getDraft($freshChat);

            // 1. ADD_TO_CART
            if ($intent->intent === CommerceIntent::ADD_TO_CART) {
                $productQuery = $intent->product ?? '';
                if ($productQuery === '') {
                    return null;
                }

                $parsed = $this->parseProductLine($company, "{$intent->quantity} x {$productQuery}");
                if ($parsed) {
                    if (! empty($parsed['has_variants'])) {
                        $reply = $this->initVariantSelectionForProduct($freshChat, $company, $draft, $parsed['product_model'], $parsed['quantity']);

                        return ['success' => true, 'message' => $reply];
                    }

                    $draft['items'] = $draft['items'] ?? [];
                    $draft['items'][] = $parsed;
                    $draft = $this->withCatalogIds($company, $draft);
                    $this->setStep($freshChat, self::STEP_PRODUCT, $draft);

                    $reply = $this->formatAddedToCartMessage($company, (string) $parsed['name'], (int) $parsed['quantity'], $draft, $freshChat);

                    return ['success' => true, 'message' => $reply];
                }
            }

            // 2. REMOVE_FROM_CART
            if ($intent->intent === CommerceIntent::REMOVE_FROM_CART) {
                $target = mb_strtolower(trim($intent->product ?? ''));
                if ($target === '' || empty($draft['items'])) {
                    return null;
                }

                $newItems = [];
                $removedName = null;
                foreach ($draft['items'] as $item) {
                    $itemName = mb_strtolower($item['name'] ?? '');
                    if ($removedName === null && (str_contains($itemName, $target) || str_contains($target, $itemName))) {
                        $removedName = $item['name'];
                        continue;
                    }
                    $newItems[] = $item;
                }

                if ($removedName !== null) {
                    $draft['items'] = $newItems;
                    $this->setStep($freshChat, empty($newItems) ? self::STEP_NONE : self::STEP_PRODUCT, $draft);
                    app(\App\Services\Storefront\StorefrontService::class)->syncChatCartToStorefrontSession($company, $freshChat);

                    $reply = empty($newItems)
                        ? "🗑️ Removed *{$removedName}* from your cart.\n\n🛒 Your cart is now empty."
                        : "🗑️ Removed *{$removedName}* from your cart.\n\n".$this->formatDraftSummary($company, $draft, $freshChat);

                    return ['success' => true, 'message' => $reply];
                }
            }

            // 3. UPDATE_QUANTITY
            if ($intent->intent === CommerceIntent::UPDATE_QUANTITY) {
                $target = mb_strtolower(trim($intent->product ?? ''));
                if ($target === '' || empty($draft['items'])) {
                    return null;
                }

                $updatedName = null;
                $newQuantity = max(1, $intent->quantity);
                foreach ($draft['items'] as &$item) {
                    $itemName = mb_strtolower($item['name'] ?? '');
                    if ($updatedName === null && (str_contains($itemName, $target) || str_contains($target, $itemName))) {
                        $item['quantity'] = $newQuantity;
                        $updatedName = $item['name'];
                        break;
                    }
                }
                unset($item);

                if ($updatedName !== null) {
                    $draft = $this->withCatalogIds($company, $draft);
                    $this->setStep($freshChat, self::STEP_PRODUCT, $draft);
                    app(\App\Services\Storefront\StorefrontService::class)->syncChatCartToStorefrontSession($company, $freshChat);

                    $reply = "✏️ Updated *{$updatedName}* quantity to {$newQuantity}.\n\n".$this->formatDraftSummary($company, $draft, $freshChat);

                    return ['success' => true, 'message' => $reply];
                }
            }

            return null;
        });
    }

    /**
     * Process customer message in order flow context.
     * Returns reply text to send, or null to fall through to normal AI reply.
     */
    public function processMessage(Chat $chat, Company $company, string $messageText, string $customerName, string $customerPhone): ?string
    {
        return $this->withChatLock($chat, function (Chat $freshChat) use ($company, $messageText, $customerName, $customerPhone) {
            $chat = $freshChat;
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

        // Request catalog / product list
        if ($this->wantsCatalogList($lower, $trimmed)) {
            $this->setStep($chat, self::STEP_PRODUCT, $this->withCatalogIds($company, $draft));

            return $this->formatCatalogMessage($company, $chat);
        }

        // WhatsApp Cart Management Commands
        if (in_array($lower, ['view cart', 'my cart', 'cart', 'show cart', 'basket', 'view basket', '0'], true)) {
            $items = $draft['items'] ?? [];
            if (empty($items)) {
                return "🛒 *Your cart is currently empty.*\n\nReply with a product number or name to add items to your cart.";
            }

            return $this->formatDraftSummary($company, $draft, $chat)."\n\n📋 *Next steps:*\n• Reply *done* or *checkout* to place order\n• Reply *clear* to empty cart";
        }

        if (in_array($lower, ['clear cart', 'empty cart', 'reset cart', 'clear'], true)) {
            $this->clearState($chat);
            app(\App\Services\Storefront\StorefrontService::class)->clearCartForPhone($company, $customerPhone);

            return "🛒 *Your cart has been emptied.*\n\nReply with a product number or name whenever you'd like to start a new order!";
        }

        if (preg_match('/^(?:remove|delete|drop)\s+(.+)$/iu', $trimmed, $matches) && ! empty($draft['items'])) {
            $target = mb_strtolower(trim($matches[1]));
            $newItems = [];
            $removedName = null;
            foreach ($draft['items'] as $item) {
                $itemName = mb_strtolower($item['name'] ?? '');
                if ($removedName === null && (str_contains($itemName, $target) || str_contains($target, $itemName))) {
                    $removedName = $item['name'];
                    continue;
                }
                $newItems[] = $item;
            }
            if ($removedName !== null) {
                $draft['items'] = $newItems;
                $this->setStep($chat, empty($newItems) ? self::STEP_NONE : self::STEP_PRODUCT, $draft);
                app(\App\Services\Storefront\StorefrontService::class)->syncChatCartToStorefrontSession($company, $chat);
                if (empty($newItems)) {
                    return "🗑️ Removed *{$removedName}* from your cart.\n\n🛒 Your cart is now empty.";
                }

                return "🗑️ Removed *{$removedName}* from your cart.\n\n".$this->formatDraftSummary($company, $draft, $chat);
            }
        }

        // Request images of cart items
        if ($this->wantsCartImages($lower, $trimmed)) {
            $items = $draft['items'] ?? [];
            if (empty($items)) {
                return "🛒 *Your cart is currently empty.*\n\nReply with a product number or name to add items to your cart.";
            }

            $imagesSent = 0;
            $replyParts = [];

            foreach ($items as $item) {
                $pId = (int) ($item['product_id'] ?? 0);
                if ($pId <= 0) {
                    continue;
                }
                $product = Product::where('company_id', $company->id)->find($pId);
                if (! $product) {
                    continue;
                }

                $imageUrl = $this->resolveProductImageUrl($company, $product);
                if ($imageUrl) {
                    $imagesSent++;
                    $name = (string) ($item['name'] ?? $product->name);
                    $priceStr = $this->formatMoney($company, (float) ($item['price'] ?? $product->price));
                    $caption = "📷 *{$name}* — {$priceStr}";
                    if (! empty($product->description)) {
                        $caption .= "\n_".Str::limit(strip_tags($product->description), 120)."_";
                    }

                    $replyParts[] = "[IMAGE_URL: {$imageUrl} CAPTION: {$caption}]";
                }
            }

            if ($imagesSent === 0) {
                $noImageReply = "📷 *No images are currently uploaded for the items in your cart.*\n\n".$this->formatDraftSummary($company, $draft, $chat);
                if ($step === self::STEP_CONFIRM) {
                    $noImageReply .= "\n\nWhat would you like to do next?\n1 - Confirm & place order\n2 - Cancel";
                }

                return $noImageReply;
            }

            $summary = $this->formatDraftSummary($company, $draft, $chat);
            $imgHeader = "Here's the image of the item".(count($items) > 1 ? 's' : '')." in your cart: 📷";
            $response = $imgHeader."\n\n".implode("\n\n", $replyParts)."\n\n".$summary;

            if ($step === self::STEP_CONFIRM) {
                $response .= "\n\nWhat would you like to do next?\n1 - Confirm & place order\n2 - Cancel";
            }

            return $response;
        }

        // If the customer mentions another product name in their message (e.g. "i want headphones"),
        // intercept it and redirect the flow to that product instead of being stuck in the current step.
        if ($step && ! $this->looksLikeGreetingOnly($lower) && ! preg_match('/^\d+$/', $trimmed)) {
            $parsed = $this->parseProductLine($company, $messageText);
            if ($parsed) {
                if (! empty($parsed['has_variants'])) {
                    // Start variant selection for the new product
                    return $this->initVariantSelectionForProduct($chat, $company, $draft, $parsed['product_model'], $parsed['quantity']);
                }
                // Add the new product line to cart items
                $draft['items'] = $draft['items'] ?? [];
                $draft['items'][] = $parsed;
                unset($draft['pending_product_id'], $draft['pending_variant_id'], $draft['variant_ids'], $draft['pending_qty']);
                $draft = $this->withCatalogIds($company, $draft);
                $this->setStep($chat, self::STEP_PRODUCT, $draft);

                return $this->formatAddedToCartMessage($company, (string) $parsed['name'], (int) $parsed['quantity'], $draft, $chat);
            }
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
            $registry = app(\App\Services\PaymentGateways\PaymentGatewayRegistry::class);
            $availableDrivers = $registry->getAvailableDrivers($company);

            // Only manual configured → share till/bank details immediately (no empty menu).
            if (count($availableDrivers) === 1 && $availableDrivers[0]->getId() === 'manual') {
                $this->clearState($chat);

                return "Delivery address saved: {$address}\n\n".$this->formatOrderWithManualPaymentInstructions($order);
            }

            $this->setStep($chat, self::STEP_EXISTING_ORDER_PAYMENT_METHOD, $draft);

            return "Delivery address saved: {$address}\n\n".$this->formatPaymentMethodPrompt($order);
        }

        if ($step === self::STEP_EXISTING_ORDER_PAYMENT_METHOD) {
            $order = isset($draft['order_id']) ? Order::find((int) $draft['order_id']) : null;
            if (! $order) {
                $this->clearState($chat);

                return 'Something went wrong. Please ask us to resend your invoice or start a new order.';
            }

            $registry = app(\App\Services\PaymentGateways\PaymentGatewayRegistry::class);
            $matchedDriver = $registry->matchCustomerSelection($company, $messageText);

            if ($matchedDriver) {
                $method = $matchedDriver->getId();
                $order->update(['payment_method' => $method]);
                if ($method === 'manual') {
                    $this->clearState($chat);

                    return $this->formatOrderWithManualPaymentInstructions($order);
                }
                if ($method === 'cod') {
                    return $this->handleCodPayment($order, $chat);
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

                // Handle generic drivers dynamically
                $payResult = $matchedDriver->initiatePayment($order);
                $this->clearState($chat);
                $inst = $matchedDriver->getInstructions($company, $order);
                if (! empty($payResult['url'])) {
                    return "Order #{$order->order_number} confirmed! Please complete payment here: ".$payResult['url']."\n\nInstructions: ".($inst ?? 'Complete the payment using the link.');
                }

                return "Order #{$order->order_number} confirmed!\n\nInstructions: ".($inst ?? 'Please follow payment instructions.');
            }

            if ($this->shouldDelegateOrderStepToAssistant($lower, $trimmed)) {
                return null;
            }

            return $this->formatPaymentMethodPrompt($order, true);
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
                    if (! empty($parsed['has_variants'])) {
                        return $this->initVariantSelectionForProduct($chat, $company, [], $parsed['product_model'], $parsed['quantity']);
                    }
                    $draft = ['items' => [$parsed]];
                    $draft = $this->withCatalogIds($company, $draft);
                    $this->setStep($chat, self::STEP_PRODUCT, $draft);
                    return $this->formatAddedToCartMessage($company, (string) $parsed['name'], (int) $parsed['quantity'], $draft, $chat);
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
                if (! empty($parsed['has_variants'])) {
                    return $this->initVariantSelectionForProduct($chat, $company, $draft, $parsed['product_model'], $parsed['quantity']);
                }
                $draft['items'] = $draft['items'] ?? [];
                $draft['items'][] = $parsed;
                unset($draft['pending_product_id'], $draft['pending_variant_id'], $draft['variant_ids'], $draft['pending_qty']);
                $draft = $this->withCatalogIds($company, $draft);
                $this->setStep($chat, self::STEP_PRODUCT, $draft);
                return $this->formatAddedToCartMessage($company, (string) $parsed['name'], (int) $parsed['quantity'], $draft, $chat);
            }

            return $this->productStepUnrecognizedReply();
        }

        if ($step === self::STEP_ADDRESS) {
            if ($this->shouldDelegateOrderStepToAssistant($lower, $trimmed)
                || $this->looksLikeLocationOrShopInfoQuestion($lower)) {
                return null;
            }

            if ($this->wantsPickup($lower)) {
                $draft['fulfillment_type'] = 'pickup';
                unset($draft['delivery_address']);
                $this->setStep($chat, self::STEP_CONFIRM, $draft);
                $summary = $this->formatDraftSummary($company, $draft, $chat);

                return "📦 Pickup selected.\n\n{$summary}\n\n".$this->confirmationFootnote()."\n\nWhat would you like to do next?\n1 - Confirm & place order\n2 - Cancel";
            }
            if ($this->wantsDineIn($lower)) {
                $draft['fulfillment_type'] = 'dine_in';
                unset($draft['delivery_address']);
                $this->setStep($chat, self::STEP_CONFIRM, $draft);
                $summary = $this->formatDraftSummary($company, $draft, $chat);

                return "🍽️ Dine-in selected.\n\n{$summary}\n\n".$this->confirmationFootnote()."\n\nWhat would you like to do next?\n1 - Confirm & place order\n2 - Cancel";
            }

            $scheduled = $this->tryParseSchedule($trimmed);
            if ($scheduled !== null) {
                $draft['scheduled_for'] = $scheduled->toIso8601String();
                $this->setStep($chat, self::STEP_ADDRESS, $draft);

                return 'Scheduled for '.$scheduled->toDayDateTimeString().".\nNow reply with your delivery address (or say pickup / dine-in).";
            }

            $address = trim($messageText);
            if (! $this->looksLikeDeliveryAddress($address)) {
                return 'Please provide your delivery address (e.g., street, building, or area name), or reply "pickup" if you would like to pick up your order.';
            }
            $draft['delivery_address'] = $address;
            $draft['fulfillment_type'] = $draft['fulfillment_type'] ?? 'delivery';
            $this->setStep($chat, self::STEP_CONFIRM, $draft);
            $summary = $this->formatDraftSummary($company, $draft, $chat);

            return "📍 Delivery address:\n{$address}\n\n{$summary}\n\n".$this->confirmationFootnote()."\n\nWhat would you like to do next?\n1 - Confirm & place order\n2 - Cancel";
        }

        if ($step === self::STEP_CONFIRM) {
            if ($this->wantsDiscardConfirmOrder($lower)) {
                $this->clearState($chat);

                return 'Order not placed. Reply with "order" or "2" when you want to try again.';
            }
            if ($this->wantsConfirm($lower)) {
                $guard = $this->spamGuard->assertCanPlaceOrder($company, $customerPhone, $chat);
                if (! ($guard['allowed'] ?? true)) {
                    return $guard['reason'] ?? 'Unable to place order right now.';
                }

                $order = $this->createOrderFromDraft($company, $chat, $draft, $customerName, $customerPhone);
                $settings = $company->settings;
                $collectEnabled = $settings && $settings->orders_collect_payment_enabled !== false;
                if (! $collectEnabled) {
                    $this->clearState($chat);

                    return $this->withReceipt($order, "Order confirmed! Your order number is: {$order->order_number}.\n".$this->formatOrderMoneySummary($order)."\n".$this->formatPublicPayLinks($order)."\nWe'll prepare it and contact you for delivery.");
                }
                $registry = app(\App\Services\PaymentGateways\PaymentGatewayRegistry::class);
                $availableDrivers = $registry->getAvailableDrivers($company);
                if (count($availableDrivers) > 0) {
                    if (count($availableDrivers) === 1 && $availableDrivers[0]->getId() === 'manual') {
                        $this->clearState($chat);

                        return $this->formatOrderWithManualPaymentInstructions($order);
                    }
                    $draft['order_id'] = $order->id;
                    $this->setStep($chat, self::STEP_PAYMENT_METHOD, $draft);

                    return $this->formatPaymentMethodPrompt($order);
                }
                $this->clearState($chat);

                return $this->withReceipt($order, "Order confirmed! Your order number is: {$order->order_number}.\n".$this->formatOrderMoneySummary($order)."\n".$this->formatPublicPayLinks($order)."\nWe'll prepare it and contact you for delivery.");
            }

            // Stay on confirm — do not fall through to null (agent/customer shorthand must get a re-prompt).
            return "Please confirm to place your order:\n1 - Confirm & place order\n2 - Cancel";
        }

        if ($step === self::STEP_PAYMENT_METHOD) {
            $order = isset($draft['order_id']) ? Order::find($draft['order_id']) : null;
            if (! $order) {
                $this->clearState($chat);

                return 'Something went wrong. Please start over with "order" or "2".';
            }

            $registry = app(\App\Services\PaymentGateways\PaymentGatewayRegistry::class);
            $matchedDriver = $registry->matchCustomerSelection($company, $messageText);

            if ($matchedDriver) {
                $method = $matchedDriver->getId();
                $order->update(['payment_method' => $method]);
                if ($method === 'manual') {
                    $this->clearState($chat);

                    return $this->formatOrderWithManualPaymentInstructions($order);
                }
                if ($method === 'cod') {
                    return $this->handleCodPayment($order, $chat, true);
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

                // Handle generic drivers dynamically
                $payResult = $matchedDriver->initiatePayment($order);
                $this->clearState($chat);
                $inst = $matchedDriver->getInstructions($company, $order);
                if (! empty($payResult['url'])) {
                    return "Order confirmed! Please complete payment here: ".$payResult['url']."\n\nInstructions: ".($inst ?? 'Complete the payment using the link.');
                }

                return "Order confirmed!\n\nInstructions: ".($inst ?? 'Please follow payment instructions.');
            }

            if ($this->shouldDelegateOrderStepToAssistant($lower, $trimmed)) {
                return null;
            }

            return $this->formatPaymentMethodPrompt($order, true);
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

                    return $this->withReceipt($order, "Order #{$order->order_number} confirmed.\n".$this->formatOrderMoneySummary($order)."\nWe couldn't send M-Pesa right now (".($result['error'] ?? 'please try again later')."). We'll contact you for payment.");
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

            return $this->withReceipt($order, "Order #{$order->order_number} confirmed.\n".$this->formatOrderMoneySummary($order)."\nWe couldn't send M-Pesa right now (".($result['error'] ?? 'please try again later')."). We'll contact you for payment.");
        }

            return null;
        });
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
            // Rebuild catalog IDs if they were lost (e.g. Agent OS handled a prior turn)
            $ids = $this->getCatalogProducts($company)->pluck('id')->all();
            if ($ids !== []) {
                $draft['catalog_product_ids'] = $ids;
                $this->setStep($chat, self::STEP_PRODUCT, $draft);
            } else {
                return null;
            }
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

        $pendingQty = $draft['pending_qty'] ?? null;
        if ($pendingQty && $pendingQty >= 1) {
            $qty = (int) $pendingQty;
            if (!$product->usesInventory() || $variant->stock >= $qty) {
                $line = [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'name' => $product->name.' — '.$variant->label,
                    'price' => (float) $variant->price,
                    'quantity' => $qty,
                    'fulfillment_data' => $product->fulfillmentSnapshot($variant),
                ];
                $draft['items'] = $draft['items'] ?? [];
                $draft['items'][] = $line;
                unset($draft['pending_product_id'], $draft['pending_variant_id'], $draft['variant_ids'], $draft['pending_qty']);
                $draft = $this->withCatalogIds($company, $draft);
                $this->setStep($chat, self::STEP_PRODUCT, $draft);

                return $this->formatAddedToCartMessage($company, (string) $line['name'], (int) $qty, $draft, $chat);
            }
        }

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

        // Accept "10", "10x", "10 x" as quantity while a product is pending.
        if (! preg_match('/^(\d+)\s*[x×]?\s*$/u', $trimmed, $qtyMatch)) {
            return 'Reply with how many you want (a whole number), or "back" for the list. You can also ask a question about our shop.';
        }
        $qty = (int) $qtyMatch[1];
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
        return $this->formatAddedToCartMessage($company, (string) $line['name'], (int) $qty, $draft, $chat);
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
            return "🛍️ *Catalog currently empty*\n\nWe don't have any items available in our catalog right now. Please contact us for assistance.";
        }
        $lines = ["🛍️ *OUR CATALOG*\n_Reply with a number to select an item_\n"];
        $i = 1;
        foreach ($products as $p) {
            if ($this->productHasActiveVariants($p)) {
                $min = (float) $p->variants->where('status', 'active')->min('price');
                $priceLabel = 'from '.$this->formatMoney($company, $min);
            } else {
                $priceLabel = $this->formatMoney($company, (float) $p->price);
                $compare = $p->compare_at_price !== null ? (float) $p->compare_at_price : null;
                if ($compare !== null && $compare > (float) $p->price) {
                    $pct = (int) round((1 - ((float) $p->price / $compare)) * 100);
                    $priceLabel = $this->formatMoney($company, (float) $p->price)
                        .' ~~'.$this->formatMoney($company, $compare).'~~'
                        .($pct > 0 ? " (-{$pct}%)" : '');
                }
            }
            $line = "*{$i}. {$p->name}* — {$priceLabel}";
            if ($p->description) {
                $shortDesc = Str::limit(trim($p->description), 50);
                $line .= "\n   _{$shortDesc}_";
            }
            $lines[] = $line;
            $i++;
        }

        return implode("\n\n", $lines);
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
        return "📋 *Next steps:*\n"
            . "• Reply with a *product number* to add another item\n"
            . "• Type quantity & item name (e.g., *2 x Sugar*)\n"
            . "• Reply *0* or *\"done\"* to complete checkout";
    }

    /**
     * Standard WhatsApp-friendly "added to cart" template.
     *
     * @param  array<string, mixed>  $draft
     */
    protected function formatAddedToCartMessage(Company $company, string $name, int $quantity, array $draft, ?Chat $chat = null): string
    {
        $summary = $this->formatDraftSummary($company, $draft, $chat);

        return "✅ *Added to cart*\n*{$name}* x {$quantity}\n\n{$summary}\n\n".$this->afterAddItemInstructions();
    }



    /**
     * @return list<string>
     */
    protected function paymentMethodKeys(bool $acceptMpesa, bool $acceptStripe, bool $acceptPaystack, bool $acceptManual, bool $acceptCod = false): array
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
        if ($acceptCod) {
            $keys[] = 'cod';
        }
        if ($acceptManual) {
            $keys[] = 'manual';
        }

        return $keys;
    }

    protected function matchPaymentMethod(string $lower, bool $acceptMpesa, bool $acceptStripe, bool $acceptPaystack, bool $acceptManual, bool $acceptCod = false): ?string
    {
        $keys = $this->paymentMethodKeys($acceptMpesa, $acceptStripe, $acceptPaystack, $acceptManual, $acceptCod);
        if (preg_match('/^\d+$/', $lower)) {
            $idx = (int) $lower - 1;

            return $keys[$idx] ?? null;
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
        if ($acceptCod && $this->wantsCodText($lower)) {
            return 'cod';
        }
        if ($acceptManual && $this->wantsManual($lower)) {
            return 'manual';
        }

        return null;
    }

    protected function wantsMpesaText(string $lower): bool
    {
        return str_contains($lower, 'mpesa') || str_contains($lower, 'm-pesa') || str_contains($lower, 'm pesa');
    }

    protected function wantsStripeText(string $lower): bool
    {
        return str_contains($lower, 'stripe') || str_contains($lower, 'card') || str_contains($lower, 'credit') || str_contains($lower, 'debit');
    }

    protected function wantsPaystackText(string $lower): bool
    {
        return str_contains($lower, 'paystack');
    }

    protected function wantsCodText(string $lower): bool
    {
        return str_contains($lower, 'cod') || str_contains($lower, 'cash') || str_contains($lower, 'delivery');
    }

    protected function wantsManual(string $lower): bool
    {
        return str_contains($lower, 'manual') || str_contains($lower, 'till') || str_contains($lower, 'paybill') || str_contains($lower, 'custom');
    }

    protected function formatPaymentMethodPrompt(Order $order, bool $invalid = false): string
    {
        $line = 'Order #'.$order->order_number."\n".$this->formatOrderMoneySummary($order)."\n\nHow would you like to pay?";

        $registry = app(\App\Services\PaymentGateways\PaymentGatewayRegistry::class);
        $drivers = $registry->getAvailableDrivers($order->company);

        $opts = [];
        foreach ($drivers as $index => $driver) {
            $n = $index + 1;
            $opts[] = "{$n}. ".$driver->getDisplayName();
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
            return $this->withReceipt($order, "Order confirmed! Your order number is: {$order->order_number}.\n".$this->formatOrderMoneySummary($order)."\nWe'll prepare it and contact you for delivery.{$suffix}");
        }

        return $this->withReceipt($order, "Order #{$order->order_number} confirmed.\n".$this->formatOrderMoneySummary($order).$suffix);
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
            return $this->withReceipt($order, "Order confirmed! Your order number is: {$order->order_number}.\n".$this->formatOrderMoneySummary($order)."\nWe'll prepare it and contact you for delivery.{$suffix}");
        }

        return $this->withReceipt($order, "Order #{$order->order_number} confirmed.\n".$this->formatOrderMoneySummary($order).$suffix);
    }

    protected function formatOrderWithManualPaymentInstructions(Order $order): string
    {
        $settings = $order->company?->settings;
        $instructions = $settings && $settings->hasOrderPaymentManualInstructions()
            ? trim($settings->order_payment_manual_instructions)
            : '';
        $line = "Order #{$order->order_number} confirmed.\n".$this->formatOrderMoneySummary($order)."\n\nTo complete payment, please use the following details:\n\n{$instructions}\n\nReply once you have made the payment. Thank you!";

        return $this->withReceipt($order, $line);
    }

    protected function withReceipt(Order $order, string $message): string
    {
        return rtrim($message)."\n\n📄 *Invoice & Receipt:*\n".$order->publicReceiptUrl();
    }

    /**
     * "Pay online" + "Invoice" links shared after order placement and payment prompts.
     */
    protected function formatPublicPayLinks(Order $order): string
    {
        $order->ensurePublicTokens();

        return "💳 *Pay Online:*\n".$order->publicPayUrl()."\n\n📄 *View Invoice:*\n".$order->publicInvoiceUrl();
    }

    protected function handleCodPayment(Order $order, Chat $chat, bool $confirmedOrder = false): string
    {
        $this->orderPayment->markOrderCodConfirmed($order);
        $order->refresh();
        $this->clearState($chat);

        $header = $confirmedOrder
            ? "Order confirmed! Your order number is: {$order->order_number}."
            : "Order #{$order->order_number} confirmed.";

        return $this->withReceipt(
            $order,
            "{$header}\n".$this->formatOrderMoneySummary($order)
                ."\nYou'll pay in cash when your order arrives.\n\n"
                .$this->formatPublicPayLinks($order)
        );
    }

    protected function wantsPickup(string $lower): bool
    {
        return in_array($lower, ['pickup', 'pick up', 'pick-up', 'collect', 'collection'], true)
            || str_contains($lower, 'pickup')
            || str_contains($lower, 'pick up');
    }

    protected function wantsDineIn(string $lower): bool
    {
        return in_array($lower, ['dine in', 'dine-in', 'dinein', 'table', 'eat in'], true)
            || str_contains($lower, 'dine in')
            || str_contains($lower, 'dine-in');
    }

    protected function tryParseSchedule(string $text): ?Carbon
    {
        $lower = mb_strtolower(trim($text));
        if (! preg_match('/\b(today|tomorrow|schedule|at\s+\d|am|pm|\d{1,2}:\d{2})\b/i', $lower)) {
            return null;
        }

        try {
            if (str_starts_with($lower, 'tomorrow')) {
                $rest = trim(substr($lower, strlen('tomorrow')));
                $base = now()->addDay()->startOfDay();
                if ($rest === '' || $rest === 'morning') {
                    return $base->setTime(9, 0);
                }
                if ($rest === 'afternoon') {
                    return $base->setTime(14, 0);
                }
                if ($rest === 'evening') {
                    return $base->setTime(18, 0);
                }

                return Carbon::parse('tomorrow '.$rest);
            }
            if (str_starts_with($lower, 'today')) {
                $rest = trim(substr($lower, strlen('today')));
                if ($rest === '') {
                    return now()->addHour()->minute(0)->second(0);
                }

                return Carbon::parse('today '.$rest);
            }
            if (preg_match('/^(schedule|scheduled)\s+/i', $lower)) {
                $rest = trim(preg_replace('/^(schedule|scheduled)\s+/i', '', $lower) ?? '');

                return $rest !== '' ? Carbon::parse($rest) : null;
            }

            return Carbon::parse($text);
        } catch (\Throwable) {
            return null;
        }
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
        if (preg_match('/\b(?:order|buy|get|want)\s+(?:the|a|an)?\s*[a-z0-9]/iu', $lower) || str_contains($lower, 'them') || str_contains($lower, 'this')) {
            return false;
        }

        return $lower === '2' || $lower === 'order' || $lower === 'place order' || $lower === 'start order';
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
            '/\b(contact|email|whatsapp|hours|opening|closed|open|refund|about|shop|store)\b/i',
            '/\b(help|support|agent|human|speak|representative)\b/i',
            '/\b(how do|how can|why|when|who)\b/i',
            '/\b(question|wondering|tell me|explain)\b/i',
            '/\b(i want to add|add item|i want to order|i want to buy|want help|need help|help with)\b/i',
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
        return "I didn't catch that as a product number or quantity. 🤔\n\n"
            . "*How to respond:*\n"
            . "• Reply with a *number* from the catalog (e.g. *1*)\n"
            . "• Type quantity & item name (e.g. *2 x Coffee*)\n"
            . "• Reply *0* or *\"done\"* when your cart is ready\n"
            . "• Say *\"cancel\"* to stop your order\n\n"
            . "You can also ask any question about our products or shop!";
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
        $lower = trim($lower);
        if (in_array($lower, [
            'done', 'that\'s all', 'thats all', 'finish', 'next', '0', 'ok', 'okay', 'k', 'yes', 'y', 'yeah', 'yep', 'yup', 'sure', 'sawa', 'proceed', 'go ahead', 'confirm',
        ], true)) {
            return true;
        }

        return (bool) preg_match('/(?:^|\b)(?:done|ok|okay|yes|sure|proceed|go ahead|confirm|place order|ready to proceed|i am ready|i\'m ready|want to proceed|proceed with|checkout|finish|done with order)\b/iu', $lower);
    }

    protected function wantsConfirm(string $lower): bool
    {
        $lower = trim($lower);
        if (in_array($lower, [
            'confirm', 'yes', 'y', 'yeah', 'yep', 'yup', 'ok', 'okay', 'k', 'sure', 'sawa',
            'place order', 'confirm order', 'place it', 'go ahead', 'proceed', 'do it',
            'alright', 'all right', 'fine', '1',
        ], true)) {
            return true;
        }
        // "okay, pay via 12345" / "yes pay" while reviewing the cart.
        if (preg_match('/^(ok|okay|yes|sure|sawa|alright|confirm)\b/u', $lower)) {
            return true;
        }
        if (preg_match('/\b(pay|payment|mpesa|m-pesa|till|paybill|invoice|receipt)\b/u', $lower)) {
            return true;
        }
        if (preg_match('/(?:^|\b)(?:proceed|confirm|place order|ready to proceed|ready to order|go ahead|do it|finalize|finish|place it|i am ready|i\'m ready|want to proceed|proceed with order|confirm order|ready|yes proceed)\b/iu', $lower)) {
            return true;
        }

        return false;
    }

    protected function looksLikeDeliveryAddress(string $text): bool
    {
        $lower = mb_strtolower(trim($text));
        if (strlen($lower) < 3) {
            return false;
        }
        if (in_array($lower, ['yes', 'y', 'yeah', 'yep', 'yup', 'ok', 'okay', 'sure', 'proceed', 'confirm', 'go ahead', 'done', 'yes proceed', 'yes proceed with the order', 'i am ready', 'i am ready to proceed with the order', 'ready to proceed', 'have you finalized?'], true)) {
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

        $cleanText = preg_replace('/[^\w\s×*]/u', '', $text) ?? $text;

        if (preg_match('/^(\d+)\s*[x×]?\s*(.+)$/iu', $cleanText, $m)) {
            $qty = (int) $m[1];
            $namePart = trim($m[2]);
            if ($qty >= 1 && $namePart !== '') {
                $product = $this->matchProduct($products, $namePart);
                if ($product) {
                    $variantLine = $this->resolveVariantMatch($product, $text, $qty);
                    if ($variantLine) {
                        return $variantLine;
                    }
                    if ($this->productHasActiveVariants($product)) {
                        return [
                            'product_id' => $product->id,
                            'product_model' => $product,
                            'quantity' => $qty,
                            'has_variants' => true,
                        ];
                    }
                    return [
                        'product_id' => $product->id,
                        'name' => $product->name,
                        'price' => (float) $product->price,
                        'quantity' => $qty,
                        'fulfillment_data' => $product->fulfillmentSnapshot(),
                    ];
                }
            }
        }

        if (preg_match('/^(.+?)\s*[x×]\s*(\d+)$/iu', $text, $m)) {
            $namePart = trim($m[1]);
            $qty = (int) $m[2];
            if ($qty >= 1) {
                $product = $this->matchProduct($products, $namePart);
                if ($product) {
                    $variantLine = $this->resolveVariantMatch($product, $text, $qty);
                    if ($variantLine) {
                        return $variantLine;
                    }
                    if ($this->productHasActiveVariants($product)) {
                        return [
                            'product_id' => $product->id,
                            'product_model' => $product,
                            'quantity' => $qty,
                            'has_variants' => true,
                        ];
                    }
                    return [
                        'product_id' => $product->id,
                        'name' => $product->name,
                        'price' => (float) $product->price,
                        'quantity' => $qty,
                        'fulfillment_data' => $product->fulfillmentSnapshot(),
                    ];
                }
            }
        }

        if (preg_match('/^(.+?)\s+(?:quantity|qty|quatity)\s*(\d+)\s*$/iu', $text, $m)) {
            $namePart = trim($m[1]);
            $qty = (int) $m[2];
            if ($qty >= 1) {
                $product = $this->matchProduct($products, $namePart);
                if ($product) {
                    $variantLine = $this->resolveVariantMatch($product, $text, $qty);
                    if ($variantLine) {
                        return $variantLine;
                    }
                    if ($this->productHasActiveVariants($product)) {
                        return [
                            'product_id' => $product->id,
                            'product_model' => $product,
                            'quantity' => $qty,
                            'has_variants' => true,
                        ];
                    }
                    return [
                        'product_id' => $product->id,
                        'name' => $product->name,
                        'price' => (float) $product->price,
                        'quantity' => $qty,
                        'fulfillment_data' => $product->fulfillmentSnapshot(),
                    ];
                }
            }
        }

        $product = $this->matchProduct($products, $text);
        if ($product) {
            $variantLine = $this->resolveVariantMatch($product, $text, 1);
            if ($variantLine) {
                return $variantLine;
            }
            if ($this->productHasActiveVariants($product)) {
                return [
                    'product_id' => $product->id,
                    'product_model' => $product,
                    'quantity' => 1,
                    'has_variants' => true,
                ];
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

    public function publicCartUrl(Company $company, Chat $chat): ?string
    {
        $session = app(\App\Services\Storefront\StorefrontService::class)->syncChatCartToStorefrontSession($company, $chat);
        $slug = $company->store_slug ?: \Illuminate\Support\Str::slug($company->name);
        if (! $slug) {
            $slug = 'store-'.$company->id;
        }

        return url('/s/'.$slug.'/cart?token='.$session->session_token);
    }

    protected function wantsCatalogList(string $lower, string $trimmed): bool
    {
        if (in_array($lower, ['catalog', 'show catalog', 'my catalog', 'view catalog', 'our catalog', 'products', 'show products', 'menu', 'show menu', 'list', 'items', 'shop'], true)) {
            return true;
        }

        return (bool) preg_match('/\b(?:catalog|products|menu|items|what do you sell|show me your catalog|show me catalog|show catalog|view catalog|list products|see products|see catalog)\b/iu', $lower);
    }

    public function formatCatalogMessage(Company $company, ?Chat $chat = null): string
    {
        $catalogText = $this->formatNumberedProductList($company);

        $response = "🛍️ *OUR CATALOG*\n\n{$catalogText}\n\n".$this->numberedOrderInstructions();

        $storeUrl = app(\App\Services\Conversation\ConversationGreetingService::class)->publicStorefrontUrl($company, $chat);
        if ($storeUrl) {
            $response .= "\n\n🛍️ *Shop Online:*\n{$storeUrl}";
        }

        return $response;
    }

    protected function confirmationFootnote(): string
    {
        return "📷 *Would you like to see images of the items in your cart?*\nReply *\"images\"* or *\"photos\"* to view them.";
    }

    protected function wantsCartImages(string $lower, string $trimmed): bool
    {
        if (in_array($lower, ['images', 'image', 'photos', 'photo', 'pictures', 'picture', 'show images', 'show photos', 'send images', 'view images', 'view photos', 'can i get an image of the item in my cart'], true)) {
            return true;
        }

        return (bool) preg_match('/\b(?:image|images|photo|photos|picture|pictures|photo of|image of|picture of)\b/iu', $lower);
    }

    public function resolveProductImageUrl(Company $company, Product $product): ?string
    {
        $primary = $product->primaryImage();
        $path = $primary?->path ?? $product->image;
        if (! $path) {
            return null;
        }

        $url = Storage::url($path);
        if (str_starts_with($url, '/')) {
            return url($url);
        }

        return $url;
    }

    protected function formatDraftSummary(Company $company, array $draft, ?Chat $chat = null): string
    {
        $items = $draft['items'] ?? [];
        if (empty($items)) {
            return '🛒 *Cart is empty.*';
        }
        $lines = ['🛒 *YOUR CART SUMMARY*'];
        foreach ($items as $item) {
            $qty = (int) ($item['quantity'] ?? 0);
            $unit = (float) ($item['price'] ?? 0);
            $lineSubtotal = round($unit * $qty, 2);
            $lines[] = '• *'.$item['name'].'* — '.$qty.' x '.$this->formatMoney($company, $unit).' = *'.$this->formatMoney($company, $lineSubtotal).'*';
        }

        $calc = $this->taxCalculator->calculateForCompany($company, $items);
        $lines[] = '————————————';
        $lines[] = 'Subtotal: '.$this->formatMoney($company, (float) $calc['subtotal']);

        $needsDelivery = $this->draftRequiresDeliveryAddress($draft);
        $fulfillmentType = (string) ($draft['fulfillment_type'] ?? ($needsDelivery ? 'delivery' : 'pickup'));

        $deliveryFee = 0.0;
        if ($fulfillmentType === 'delivery') {
            $quote = $this->deliveryFees->quote($company, $draft['delivery_address'] ?? null, (float) $calc['subtotal'], $fulfillmentType);
            $deliveryFee = (float) ($quote['fee'] ?? 0.0);
            if ($deliveryFee > 0) {
                $lines[] = 'Delivery fee: '.$this->formatMoney($company, $deliveryFee);
            }
        }

        foreach ($calc['tax_breakdown'] ?? [] as $row) {
            $label = $row['code'] ?: $row['name'];
            $rate = rtrim(rtrim(number_format((float) $row['rate'], 4, '.', ''), '0'), '.');
            $incl = ! empty($row['inclusive']) ? ' incl.' : '';
            $lines[] = "{$label} ({$rate}%{$incl}): ".$this->formatMoney($company, (float) $row['amount']);
        }

        $total = round((float) $calc['total'] + $deliveryFee, 2);
        $lines[] = '*Total: '.$this->formatMoney($company, $total).'*';

        $lines[] = 'Fulfillment: *'.$this->fulfillmentLabel($fulfillmentType).'*';
        if (! $needsDelivery && $fulfillmentType === 'pickup' && empty($draft['fulfillment_type'])) {
            $lines[] = 'Delivery: _No physical shipping needed_';
        }

        if (! empty($draft['scheduled_for'])) {
            try {
                $lines[] = 'Scheduled for: _'.Carbon::parse((string) $draft['scheduled_for'])->toDayDateTimeString().'_';
            } catch (\Throwable) {
                // ignore unparsable scheduled_for values in summary display
            }
        }

        if ($company->store_slug && $chat) {
            $webCartUrl = $this->publicCartUrl($company, $chat);
            if ($webCartUrl) {
                $lines[] = '';
                $lines[] = '🌐 *View / Checkout on Web:*';
                $lines[] = $webCartUrl;
            }
        }

        return implode("\n", $lines);
    }

    protected function fulfillmentLabel(?string $type): string
    {
        return match ($type) {
            'pickup' => 'Pickup',
            'dine_in' => 'Dine-in',
            default => 'Delivery',
        };
    }

    protected function createOrderFromDraft(Company $company, Chat $chat, array $draft, string $customerName, string $customerPhone): Order
    {
        $items = $draft['items'] ?? [];
        $calc = $this->taxCalculator->calculateForCompany($company, $items);
        $fulfillmentType = (string) ($draft['fulfillment_type'] ?? 'delivery');
        if (! in_array($fulfillmentType, ['delivery', 'pickup', 'dine_in'], true)) {
            $fulfillmentType = 'delivery';
        }

        $quote = $this->deliveryFees->quote(
            $company,
            $draft['delivery_address'] ?? null,
            (float) $calc['subtotal'],
            $fulfillmentType
        );
        $deliveryFee = (float) $quote['fee'];
        $total = round((float) $calc['total'] + $deliveryFee, 2);

        $orderNumber = 'ORD-'.strtoupper(Str::random(8));
        while (Order::where('order_number', $orderNumber)->exists()) {
            $orderNumber = 'ORD-'.strtoupper(Str::random(8));
        }

        $scheduledFor = null;
        if (! empty($draft['scheduled_for'])) {
            try {
                $scheduledFor = Carbon::parse((string) $draft['scheduled_for']);
            } catch (\Throwable) {
                $scheduledFor = null;
            }
        }

        $order = Order::create([
            'company_id' => $company->id,
            'chat_id' => $chat->id,
            'order_number' => $orderNumber,
            'customer_name' => $customerName ?: 'Customer',
            'customer_phone' => $customerPhone,
            'delivery_address' => $draft['delivery_address'] ?? null,
            'fulfillment_type' => $fulfillmentType,
            'dine_in_table_id' => $draft['dine_in_table_id'] ?? null,
            'dine_in_table_name' => $draft['dine_in_table_name'] ?? null,
            'subtotal' => $calc['subtotal'],
            'tax_total' => $calc['tax_total'],
            'delivery_fee' => $deliveryFee,
            'tax_breakdown' => $calc['tax_breakdown'] !== [] ? $calc['tax_breakdown'] : null,
            'total' => $total,
            'status' => 'pending',
            'payment_status' => 'pending',
            'scheduled_for' => $scheduledFor,
            'source' => $draft['source'] ?? 'whatsapp',
        ]);

        $order->ensurePublicTokens();

        foreach ($items as $index => $item) {
            $lineTax = $calc['lines'][$index] ?? [
                'tax_rate_id' => null,
                'tax_name' => null,
                'tax_code' => null,
                'tax_rate' => null,
                'tax_inclusive' => false,
                'tax_amount' => 0.0,
                'line_subtotal' => round(((float) ($item['price'] ?? 0)) * ((int) ($item['quantity'] ?? 1)), 2),
            ];

            OrderProduct::create([
                'order_id' => $order->id,
                'product_id' => $item['product_id'] ?? null,
                'product_variant_id' => $item['product_variant_id'] ?? null,
                'name' => $item['name'] ?? 'Item',
                'quantity' => (int) ($item['quantity'] ?? 1),
                'price' => (float) ($item['price'] ?? 0),
                'tax_rate_id' => $lineTax['tax_rate_id'],
                'tax_name' => $lineTax['tax_name'],
                'tax_code' => $lineTax['tax_code'],
                'tax_rate' => $lineTax['tax_rate'],
                'tax_inclusive' => $lineTax['tax_inclusive'],
                'tax_amount' => $lineTax['tax_amount'],
                'line_subtotal' => $lineTax['line_subtotal'],
                'fulfillment_data' => $item['fulfillment_data'] ?? null,
            ]);
        }

        return $order->fresh(['orderProducts']);
    }

    protected function numberedOrderInstructions(): string
    {
        return "📌 *How to order:*\n"
            . "• Reply with a *product number* (e.g., *1*)\n"
            . "• Or type: *2 x Product Name*\n"
            . "• Reply *0* or *\"done\"* to checkout\n\n"
            . "💡 _Tip: Reply \"back\" anytime to return to the catalog._";
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

    public function initializeProductStep(Chat $chat, Company $company): void
    {
        $draft = $this->getDraft($chat);
        $draft['items'] = $draft['items'] ?? [];
        $draft = $this->withCatalogIds($company, $draft);
        $this->setStep($chat, self::STEP_PRODUCT, $draft);
    }

    protected function initVariantSelectionForProduct(Chat $chat, Company $company, array $draft, Product $product, int $qty): string
    {
        $variantIds = $product->variants->where('status', 'active')->pluck('id')->values()->all();
        $draft['pending_product_id'] = $product->id;
        $draft['variant_ids'] = $variantIds;
        $draft['pending_qty'] = $qty;
        unset($draft['pending_variant_id']);
        $this->setStep($chat, self::STEP_VARIANT, $draft);

        return $this->formatVariantListMessage($company, $product);
    }

    protected function resolveVariantMatch(Product $product, string $text, int $qty): ?array
    {
        if ($this->productHasActiveVariants($product)) {
            $matchedVariant = null;
            $lowerText = mb_strtolower($text);
            foreach ($product->variants as $variant) {
                if ($variant->status === 'active' && str_contains($lowerText, mb_strtolower($variant->label))) {
                    $matchedVariant = $variant;
                    break;
                }
            }
            if ($matchedVariant) {
                return [
                    'product_id' => $product->id,
                    'product_variant_id' => $matchedVariant->id,
                    'name' => $product->name.' — '.$matchedVariant->label,
                    'price' => (float) $matchedVariant->price,
                    'quantity' => $qty,
                    'fulfillment_data' => $product->fulfillmentSnapshot($matchedVariant),
                ];
            }
        }

        return null;
    }
}
