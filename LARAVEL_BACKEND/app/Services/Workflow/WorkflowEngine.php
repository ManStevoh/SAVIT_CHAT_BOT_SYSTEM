<?php

namespace App\Services\Workflow;

use App\DTOs\ConversationState;
use App\DTOs\IntentResult;
use App\DTOs\WorkflowTransitionResult;
use App\Enums\CheckoutStep;
use App\Enums\CommerceIntent;
use App\Enums\ResponseSpec;
use App\Models\Company;
use App\Models\Product;
use App\Services\Conversation\ConversationGreetingService;
use Illuminate\Support\Facades\Log;

final class WorkflowEngine
{
    public const WORKFLOW_VERSION = 'v2.1.1-conversational-os';

    public function __construct(
        private DomainServiceDispatcher $domain,
        private ResponseSpecRenderer $renderer,
    ) {
        Log::info('WorkflowEngine initialized', [
            'active_pipeline' => 'ConversationalOSPipeline',
            'workflow_version' => self::WORKFLOW_VERSION,
            'renderer_version' => ResponseSpecRenderer::RENDERER_VERSION,
        ]);
    }

    public function handle(ConversationState $state, IntentResult $intent, Company $company): WorkflowTransitionResult
    {
        $rawMessage = mb_strtolower(trim((string) ($intent->messageText ?? $intent->rawPayload['incoming_message'] ?? '')));

        // Global Store Location Interceptor (works mid-conversation at ANY step)
        $address = $company->settings?->business_address ?? $company->settings?->address ?? null;
        $isLocationQuery = $intent->intent === CommerceIntent::ASK_STORE_LOCATION ||
            $intent->intent->value === 'ask_store_location' ||
            str_contains($rawMessage, 'location') ||
            str_contains($rawMessage, 'where are you') ||
            str_contains($rawMessage, 'where is your shop') ||
            str_contains($rawMessage, 'where is the shop') ||
            str_contains($rawMessage, 'where you located');

        if ($isLocationQuery) {
            $storeUrl = app(\App\Services\Conversation\ConversationGreetingService::class)->publicStorefrontUrl($company, null, $state->customerPhone);
            if ($address) {
                $locationReply = "📍 *Store Location:*\n" . $address . "\n\nFeel free to ask if you need help finding us or ordering online!";
            } else {
                $locationReply = "📍 We operate online! You can view our shop location and browse our catalog online here:\n{$storeUrl}\n\nFeel free to ask if you have any questions or need help ordering!";
            }

            $nextState = $state->with([
                'step' => CheckoutStep::BUILDING_CART,
                'pendingDraftData' => array_diff_key($state->pendingDraftData, ['selecting_product_id' => 1, 'selecting_qty' => 1]),
            ]);
            return new WorkflowTransitionResult($nextState, [], ResponseSpec::GENERAL_ASSIST->value, $locationReply);
        }

        // Global FAQ Interceptor (works mid-conversation at ANY step)
        if ($intent->intent === CommerceIntent::ASK_FAQ || str_contains($rawMessage, 'faq') || str_contains($rawMessage, 'opening hours') || str_contains($rawMessage, 'working hours')) {
            $faqMatch = app(\App\Services\Conversation\FaqMatchingService::class)->matchBest($company, $rawMessage, $rawMessage);
            if ($faqMatch !== null && ! empty($faqMatch['answer'])) {
                $nextState = $state->with([
                    'step' => CheckoutStep::BUILDING_CART,
                    'pendingDraftData' => array_diff_key($state->pendingDraftData, ['selecting_product_id' => 1, 'selecting_qty' => 1]),
                ]);
                return new WorkflowTransitionResult($nextState, [], ResponseSpec::GENERAL_ASSIST->value, $faqMatch['answer']);
            }
        }

        // Context-Aware Agent Handoff Interceptor:
        // Reads the last message sent to the customer to determine if numeric '3' corresponds to 'Talk to agent'.
        $lastBotMessage = \App\Models\Message::where('chat_id', $state->chatId)
            ->where('sender', 'bot')
            ->latest('id')
            ->value('content') ?? '';

        $lastWasQuickMenu = str_contains($lastBotMessage, '3. Talk to agent')
            || str_contains($lastBotMessage, '1. Prices')
            || str_contains($lastBotMessage, '2. Order')
            || str_contains($lastBotMessage, 'Talk to agent');

        $isExplicitAgentRequest = $intent->intent === CommerceIntent::REQUEST_HUMAN ||
            str_contains($rawMessage, 'talk to agent') ||
            str_contains($rawMessage, 'talk to an agent') ||
            str_contains($rawMessage, 'speak to an agent') ||
            str_contains($rawMessage, 'speak to agent') ||
            str_contains($rawMessage, 'human') ||
            str_contains($rawMessage, 'speak to someone') ||
            str_contains($rawMessage, 'agent handoff') ||
            str_contains($rawMessage, 'real person') ||
            str_contains($rawMessage, 'customer support') ||
            str_contains($rawMessage, 'customer service');

        $isMenuOption3 = ($rawMessage === '3') && $lastWasQuickMenu;

        if ($isExplicitAgentRequest || $isMenuOption3) {
            $handoffText = "Connecting you with a support representative. An agent will be with you shortly!";
            return new WorkflowTransitionResult(
                $state->with(['step' => CheckoutStep::IDLE]),
                [['type' => 'RequestAgentHandoff']],
                ResponseSpec::GENERAL_ASSIST->value,
                $handoffText
            );
        }

        // Global Cancellation Interceptor (works mid-conversation at ANY step)
        $isCancelDirective = $intent->actionDirective === 'CANCEL_FLOW' ||
            $intent->intent === CommerceIntent::CANCEL_ORDER ||
            str_contains($rawMessage, "don't want") ||
            str_contains($rawMessage, 'cancel order') ||
            str_contains($rawMessage, 'nevermind') ||
            str_contains($rawMessage, 'stop order');

        if ($isCancelDirective) {
            if ($state->pendingOrderId) {
                \App\Models\Order::where('id', $state->pendingOrderId)->update(['status' => 'cancelled']);
            }

            $nextState = $state->with([
                'step' => CheckoutStep::IDLE,
                'cartItems' => [],
                'pendingOrderId' => null,
                'deliveryAddress' => null,
                'pendingDraftData' => [],
            ]);

            $reply = "No problem! I've cancelled your order request. Reply 'prices' whenever you'd like to browse our catalog or start a new order!";
            return new WorkflowTransitionResult($nextState, [], ResponseSpec::GENERAL_ASSIST->value, $reply);
        }

        return match ($state->step) {
            CheckoutStep::IDLE, CheckoutStep::BUILDING_CART => $this->handleBuildingCart($state, $intent, $company),
            CheckoutStep::SELECTING_VARIANT => $this->handleSelectingVariant($state, $intent, $company),
            CheckoutStep::COLLECTING_ADDRESS => $this->handleCollectingAddress($state, $intent, $company),
            CheckoutStep::REVIEWING_ORDER => $this->handleReviewingOrder($state, $intent, $company),
            CheckoutStep::SELECTING_PAYMENT_METHOD => $this->handleSelectingPayment($state, $intent, $company),
            CheckoutStep::PROVIDING_PHONE => $this->handleProvidingPhone($state, $intent, $company),
            CheckoutStep::AWAITING_PAYMENT, CheckoutStep::ORDER_COMPLETED => $this->handleAwaitingPayment($state, $intent, $company),
            default => $this->handleBuildingCart($state, $intent, $company),
        };
    }

    private function handleBuildingCart(ConversationState $state, IntentResult $intent, Company $company): WorkflowTransitionResult
    {
        $rawMessage = (string) ($intent->messageText ?? $intent->rawPayload['incoming_message'] ?? '');
        $lowerRaw = mb_strtolower(trim($rawMessage));
        $lastBotMessage = \App\Models\Message::where('chat_id', $state->chatId)
            ->where('sender', 'bot')
            ->latest('id')
            ->value('content') ?? '';

        $lastWasQuickMenu = str_contains($lastBotMessage, '3. Talk to agent')
            || str_contains($lastBotMessage, '1. Prices')
            || str_contains($lastBotMessage, '2. Order')
            || str_contains($lastBotMessage, '2. Track Order')
            || str_contains($lastBotMessage, 'Talk to agent');

        $isSelectingResolvedProduct = ! empty($intent->resolvedProductId)
            || ! empty($intent->selectedToken)
            || $intent->intent === CommerceIntent::SELECT_OPTION
            || $intent->intent === CommerceIntent::ADD_TO_CART;

        // Catalog Inquiry (Evaluated FIRST when customer asks for catalog/prices/menu or selects menu option 1 from quick menu)
        $isCatalogRequest = ($lowerRaw === 'prices' || $lowerRaw === 'catalog' || $lowerRaw === 'menu' || str_contains($lowerRaw, 'menu') || ($intent->intent === CommerceIntent::ASK_PRODUCT_INFO && $lowerRaw !== '2') || $intent->intent === CommerceIntent::ASK_PRICE || ($lowerRaw === '1' && ! $isSelectingResolvedProduct)) && $lowerRaw !== '2';

        if ($isCatalogRequest && ! $isSelectingResolvedProduct) {
            $catalogText = ResponseSpecRenderer::renderCatalogPrompt($company);
            return new WorkflowTransitionResult($state, [], ResponseSpec::GENERAL_ASSIST->value, $catalogText);
        }

        $isCheckoutTrigger = $intent->intent === CommerceIntent::START_CHECKOUT ||
            $intent->intent === CommerceIntent::CONFIRM_ORDER ||
            $lowerRaw === 'done' ||
            $lowerRaw === 'checkout' ||
            $lowerRaw === '0' ||
            $lowerRaw === 'yes' ||
            $lowerRaw === 'proceed' ||
            $lowerRaw === 'continue' ||
            str_contains($lowerRaw, 'done') ||
            str_contains($lowerRaw, 'checkout');

        if ($isCheckoutTrigger) {
            if (! $state->hasItems()) {
                $reply = "Your cart is currently empty. Reply with a product name or 'prices' to add items to your cart!";
                return new WorkflowTransitionResult($state, [], ResponseSpec::GENERAL_ASSIST->value, $reply);
            }

            $nextStep = CheckoutStep::COLLECTING_ADDRESS;
            $spec = ResponseSpec::PROMPT_DELIVERY_ADDRESS;

            $remembered = $this->getRememberedAddressForState($state, $company);
            $facts = $remembered ? ['remembered_address' => $remembered] : [];

            $nextState = $state->with([
                'step' => $nextStep,
                'pendingDraftData' => array_merge($state->pendingDraftData, $remembered ? ['remembered_address' => $remembered] : []),
            ]);
            $reply = $this->renderer->render($spec, $nextState, $company, $facts);

            return new WorkflowTransitionResult($nextState, [], $spec->value, $reply);
        }

        $isImageQuery = str_contains($lowerRaw, 'image') || str_contains($lowerRaw, 'picture') || str_contains($lowerRaw, 'photo') || str_contains($lowerRaw, 'look like');
        if ($isImageQuery) {
            $product = $this->domain->findProduct($company, $intent->product ?? $rawMessage);
            if ($product) {
                $imgUrl = $product->image_url;
                if ($imgUrl) {
                    $reply = "📷 Here is *{$product->name}*:\nPrice: " . \App\Support\MoneyFormatter::format((float) $product->price, $company->currency ?? 'USD') . "\n\nReply with '{$product->name}' to add it to your cart!\n\n[IMAGE_URL: {$imgUrl}]";
                    return new WorkflowTransitionResult($state, [], ResponseSpec::GENERAL_ASSIST->value, $reply, null, ['image_url' => $imgUrl]);
                }
                $reply = "Details for *{$product->name}*:\nPrice: " . \App\Support\MoneyFormatter::format((float) $product->price, $company->currency ?? 'USD') . "\n(No photo is currently uploaded for this product).";
                return new WorkflowTransitionResult($state, [], ResponseSpec::GENERAL_ASSIST->value, $reply);
            }
        }

        $isRemoveOrClear = $intent->intent === CommerceIntent::REMOVE_FROM_CART ||
            $intent->intent === CommerceIntent::CLEAR_CART ||
            str_contains($lowerRaw, 'remove') ||
            str_contains($lowerRaw, 'delete') ||
            str_contains($lowerRaw, 'drop') ||
            str_contains($lowerRaw, 'clear') ||
            str_contains($lowerRaw, 'empty') ||
            str_contains($lowerRaw, 'take out') ||
            str_contains($lowerRaw, 'take off') ||
            str_contains($lowerRaw, 'minus');

        if ($isRemoveOrClear && $intent->intent !== CommerceIntent::GENERAL_CHAT && ! $isImageQuery) {
            $isFullClear = $intent->intent === CommerceIntent::CLEAR_CART ||
                $lowerRaw === 'clear' ||
                $lowerRaw === 'clear cart' ||
                $lowerRaw === 'clear my cart' ||
                $lowerRaw === 'empty' ||
                $lowerRaw === 'empty cart' ||
                $lowerRaw === 'empty my cart' ||
                $lowerRaw === 'reset cart' ||
                $lowerRaw === 'delete cart' ||
                $lowerRaw === 'delete my cart' ||
                $lowerRaw === 'delete all' ||
                $lowerRaw === 'clear all' ||
                str_contains($lowerRaw, 'clear cart') ||
                str_contains($lowerRaw, 'clear my cart') ||
                str_contains($lowerRaw, 'empty cart') ||
                str_contains($lowerRaw, 'empty my cart') ||
                str_contains($lowerRaw, 'delete cart') ||
                str_contains($lowerRaw, 'delete my cart') ||
                str_contains($lowerRaw, 'clear all') ||
                str_contains($lowerRaw, 'empty all') ||
                str_contains($lowerRaw, 'delete all') ||
                (empty($intent->product) && (str_contains($lowerRaw, 'clear') || str_contains($lowerRaw, 'empty') || str_contains($lowerRaw, 'reset')));

            if ($isFullClear) {
                $nextState = $state->with(['cartItems' => []]);
                $reply = "🛒 Your cart has been cleared. Reply 'prices' or a product name to add new items!";
                return new WorkflowTransitionResult($nextState, [], ResponseSpec::GENERAL_ASSIST->value, $reply);
            }

            // Extract target product name & quantity to remove
            $targetProduct = $intent->product ?? '';
            $qtyToRemove = $intent->quantity ?? 1;

            if (preg_match('/(?:remove|delete|drop|minus|reduce|take out|take off)\s+(?:(\d+|one|two|three|four|five)\s+)?(.+)/iu', $rawMessage, $m)) {
                $rawQtyStr = mb_strtolower(trim($m[1] ?? ''));
                $rawTargetStr = trim($m[2] ?? '');
                if ($rawQtyStr !== '') {
                    $qtyWordMap = ['one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5];
                    $qtyToRemove = is_numeric($rawQtyStr) ? (int) $rawQtyStr : ($qtyWordMap[$rawQtyStr] ?? 1);
                }
                if ($rawTargetStr !== '') {
                    $cleanedTarget = preg_replace('/(?:\s+(?:from|in)?\s*(?:my|the)?\s*cart|\s+i\s+just\s+want.*)$/iu', '', $rawTargetStr);
                    $targetProduct = trim($cleanedTarget);
                }
            }

            $remResult = $this->domain->removeOrReduceItem($state, $targetProduct, $qtyToRemove);
            $nextState = $remResult['state'];
            $itemName = $remResult['item_name'];

            if ($itemName !== null) {
                $nextState = $nextState->with(['step' => CheckoutStep::BUILDING_CART]);
                $summary = $this->renderer->render(ResponseSpec::CART_SUMMARY, $nextState, $company);
                $prefix = $remResult['was_reduced']
                    ? "🗑️ Updated cart: *{$itemName}* quantity reduced to {$remResult['new_qty']}.\n\n"
                    : "🗑️ Removed *{$itemName}* from your cart.\n\n";

                return new WorkflowTransitionResult(
                    nextState: $nextState,
                    executedActions: [['type' => 'RemoveFromCart', 'payload' => ['item' => $itemName, 'qty' => $qtyToRemove]]],
                    responseSpec: ResponseSpec::CART_SUMMARY->value,
                    customerReply: $prefix . $summary
                );
            }

            if ($intent->intent === CommerceIntent::REMOVE_FROM_CART && empty($state->cartItems)) {
                $reply = "Your cart is currently empty.";
                return new WorkflowTransitionResult($state, [], ResponseSpec::GENERAL_ASSIST->value, $reply);
            }
        }

        // Handle UPDATE_QUANTITY NLU intent (e.g., "change sneakers to 1", "make it 3 pairs", "set count to 2")
        if ($intent->intent === CommerceIntent::UPDATE_QUANTITY && ! $isImageQuery) {
            $targetProduct = $intent->product ?? '';
            $newQty = $intent->quantity ?? 1;

            if ($targetProduct === '' && preg_match('/(?:change|make|set|update)\s+(?:(\w+\s*)+)\s+to\s+(\d+)/iu', $rawMessage, $m)) {
                $targetProduct = trim($m[1]);
                $newQty = (int) $m[2];
            }

            $updResult = $this->domain->updateItemQuantity($state, $targetProduct, $newQty);
            $nextState = $updResult['state'];
            $itemName = $updResult['item_name'];

            if ($itemName !== null) {
                $nextState = $nextState->with(['step' => CheckoutStep::BUILDING_CART]);
                $summary = $this->renderer->render(ResponseSpec::CART_SUMMARY, $nextState, $company);
                $prefix = "✏️ Updated cart: *{$itemName}* quantity set to {$updResult['new_qty']}.\n\n";

                return new WorkflowTransitionResult(
                    nextState: $nextState,
                    executedActions: [['type' => 'UpdateQuantity', 'payload' => ['item' => $itemName, 'qty' => $newQty]]],
                    responseSpec: ResponseSpec::CART_SUMMARY->value,
                    customerReply: $prefix . $summary
                );
            }
        }

        $matchedProduct = null;
        if (! $isRemoveOrClear && $lowerRaw !== 'prices' && $lowerRaw !== 'menu' && $lowerRaw !== 'catalog') {
            $matchedProduct = $this->domain->findProduct($company, $intent->product ?? $rawMessage);
            if (! $matchedProduct && $intent->product && $intent->product !== $rawMessage) {
                $matchedProduct = $this->domain->findProduct($company, $rawMessage);
            }
        }

        $isExplicitAddToCart = (! $isRemoveOrClear) && (
            $intent->intent === CommerceIntent::ADD_TO_CART ||
            $intent->intent === CommerceIntent::SELECT_OPTION ||
            $matchedProduct !== null ||
            str_contains($lowerRaw, 'want') ||
            str_contains($lowerRaw, 'buy') ||
            str_contains($lowerRaw, 'order') ||
            str_contains($lowerRaw, 'add')
        );

        if ($isExplicitAddToCart && ! $isImageQuery && ($matchedProduct !== null || ($intent->intent !== CommerceIntent::GENERAL_CHAT && $intent->intent !== CommerceIntent::ASK_PRODUCT_INFO))) {
            $product = $matchedProduct;
            if (! $product && $intent->resolvedProductId) {
                $product = Product::where('company_id', $company->id)->where('id', $intent->resolvedProductId)->where('status', 'active')->first();
            }

            if ($product) {
                // Check if product has active variants
                $activeVariants = $product->activeVariants()->get()->values();

                // Check if variant was specified in LLM intent OR directly in the message text (e.g. "red", "white")
                $targetVariant = $intent->variant;
                $resolvedVariantId = $intent->resolvedVariantId;
                if (! $targetVariant && ! $resolvedVariantId) {
                    foreach ($activeVariants as $v) {
                        $vLabel = mb_strtolower($v->label ?? $v->name ?? '');
                        if ($vLabel !== '' && str_contains($lowerRaw, $vLabel)) {
                            $targetVariant = $v->label ?? $v->name;
                            break;
                        }
                    }
                }

                if ($activeVariants->count() > 0 && ! $targetVariant && ! $resolvedVariantId) {
                    $nextState = $state->with([
                        'step' => CheckoutStep::SELECTING_VARIANT,
                        'pendingDraftData' => array_merge($state->pendingDraftData, [
                            'selecting_product_id' => $product->id,
                            'selecting_qty' => $intent->quantity ?? 1,
                        ]),
                    ]);

                    $reply = $this->renderer->render(
                        ResponseSpec::PROMPT_VARIANT_SELECTION,
                        $nextState,
                        $company,
                        ['product' => $product, 'variants' => $activeVariants]
                    );

                    return new WorkflowTransitionResult($nextState, [], ResponseSpec::PROMPT_VARIANT_SELECTION->value, $reply);
                }

                // Add directly if no variants or variant resolved
                $variantId = null;
                $displayName = $product->name;
                if ($targetVariant) {
                    $foundVar = $activeVariants->first(function ($v) use ($targetVariant) {
                        $vLabel = $v->label ?? $v->name ?? '';
                        return str_contains(mb_strtolower($vLabel), mb_strtolower($targetVariant)) ||
                               str_contains(mb_strtolower($targetVariant), mb_strtolower($vLabel));
                    });
                    if ($foundVar) {
                        $variantId = $foundVar->id;
                        $displayName = $product->name . ' (' . ($foundVar->label ?? $foundVar->name) . ')';
                    }
                }

                $nextState = $this->domain->addToCart($state, $product, $intent->quantity ?? 1, $variantId);
                $nextState = $nextState->with(['step' => CheckoutStep::BUILDING_CART]);

                $domainFacts = [
                    'added_product_name' => $displayName,
                    'added_product_qty' => $intent->quantity ?? 1,
                ];

                $reply = $this->renderer->render(ResponseSpec::CART_SUMMARY, $nextState, $company, $domainFacts);

                $extra = [];
                if (! empty($product->image_url)) {
                    $reply .= "\n\n[IMAGE_URL: {$product->image_url}]";
                    $extra = ['image_url' => $product->image_url];
                }

                return new WorkflowTransitionResult(
                    nextState: $nextState,
                    executedActions: [['type' => 'AddToCart', 'payload' => ['product_id' => $product->id, 'qty' => $intent->quantity]]],
                    responseSpec: ResponseSpec::CART_SUMMARY->value,
                    customerReply: $reply,
                    extra: $extra
                );
            }
        }

        $lowerRaw = mb_strtolower($rawMessage);

        // Cart Clear / Remove Inquiry
        if (
            $intent->intent === CommerceIntent::REMOVE_FROM_CART ||
            str_contains($lowerRaw, 'remove') ||
            str_contains($lowerRaw, 'clear') ||
            str_contains($lowerRaw, 'empty') ||
            str_contains($lowerRaw, 'delete')
        ) {
            $nextState = $state->with(['cartItems' => []]);
            $reply = "🛒 Your cart has been cleared. Reply 'prices' or a product name to add new items!";
            return new WorkflowTransitionResult($nextState, [], ResponseSpec::GENERAL_ASSIST->value, $reply);
        }

        $greetingService = app(ConversationGreetingService::class);
        if ($greetingService->isPureGreeting($rawMessage)) {
            $greetingText = $greetingService->buildOpening($company, $state->customerName);
            return new WorkflowTransitionResult($state, [], ResponseSpec::GENERAL_ASSIST->value, $greetingText);
        }

        // Cart Summary Inquiry
        if (str_contains($lowerRaw, 'cart') || str_contains($lowerRaw, 'selected so far') || str_contains($lowerRaw, 'my items') || str_contains($lowerRaw, 'what i have')) {
            $reply = $this->renderer->render(ResponseSpec::CART_SUMMARY, $state, $company);
            return new WorkflowTransitionResult($state, [], ResponseSpec::CART_SUMMARY->value, $reply);
        }

        // Quick Menu Option 1 or Prices / Catalog / Menu Inquiry
        if (! $isSelectingResolvedProduct && ($lowerRaw === '1' || $lowerRaw === 'prices' || $lowerRaw === 'catalog' || $lowerRaw === 'menu' || str_contains($lowerRaw, 'price') || str_contains($lowerRaw, 'catalog') || str_contains($lowerRaw, 'menu') || str_contains($lowerRaw, 'what do you') || str_contains($lowerRaw, 'what you sell'))) {
            $catalogText = ResponseSpecRenderer::renderCatalogPrompt($company);
            return new WorkflowTransitionResult($state, [], ResponseSpec::GENERAL_ASSIST->value, $catalogText);
        }

        // Quick Menu Option 2: Order Tracker Inquiry & Order # Lookups
        $isTrackOrderRequest = ($lowerRaw === '2' && $lastWasQuickMenu) ||
            $intent->intent === CommerceIntent::ASK_ORDER_STATUS ||
            str_contains($lowerRaw, 'track') ||
            str_contains($lowerRaw, 'order status') ||
            str_contains($lowerRaw, 'my order') ||
            str_contains($lowerRaw, 'check order') ||
            str_contains($lowerRaw, 'where is my order') ||
            preg_match('/^#?\d{1,8}$/i', trim($rawMessage)) === 1 ||
            preg_match('/^(?:order|ord)\s*#?\d+/i', trim($rawMessage)) === 1;

        if ($isTrackOrderRequest) {
            $trackingService = app(\App\Services\Domain\OrderTrackingService::class);
            $orderFlow = app(\App\Services\OrderFlowService::class);

            // 1. Check if specific order number requested (e.g. #160, order 160)
            $specificOrder = $trackingService->findOrderByNumber($company, $rawMessage);
            if ($specificOrder) {
                $reply = $trackingService->formatOrderTrackingCard($company, $specificOrder, $state->customerPhone);
                if (in_array($specificOrder->payment_status, ['unpaid', 'pending'], true) && $specificOrder->status !== 'cancelled') {
                    $orderFlow->setStep($chat, \App\Services\OrderFlowService::STEP_TRACKING_ACTIONS, ['order_id' => $specificOrder->id]);
                }
                return new WorkflowTransitionResult($state, [], ResponseSpec::GENERAL_ASSIST->value, $reply);
            }

            // 2. Priority 1: Check for single active unpaid/pending order for this customer
            $pendingOrder = $trackingService->getPendingUnpaidOrder($company, $state->customerPhone, $state->chatId);
            if ($pendingOrder) {
                $reply = $trackingService->formatOrderTrackingCard($company, $pendingOrder, $state->customerPhone);
                $orderFlow->setStep($chat, \App\Services\OrderFlowService::STEP_TRACKING_ACTIONS, ['order_id' => $pendingOrder->id]);
                return new WorkflowTransitionResult($state, [], ResponseSpec::GENERAL_ASSIST->value, $reply);
            }

            // 3. Otherwise retrieve recent orders for this customer
            $recentOrders = $trackingService->getRecentOrders($company, $state->customerPhone, $state->chatId);
            if ($recentOrders->isEmpty()) {
                $cleanPhone = preg_replace('/\D+/', '', $state->customerPhone);
                $phoneDisplay = $cleanPhone !== '' ? ('+' . $cleanPhone) : 'your number';
                $reply = "🔍 *Order Tracker*\n\nWe couldn't find any recent orders associated with your phone number ({$phoneDisplay}).\n\n• If you placed an order under a different phone number or Order ID, please reply with your *Order #* (e.g. *#160* or *160*).\n• Reply *'prices'* to browse our catalog and place your first order!";
                return new WorkflowTransitionResult($state, [], ResponseSpec::GENERAL_ASSIST->value, $reply);
            }

            if ($recentOrders->count() === 1) {
                $reply = $trackingService->formatOrderTrackingCard($company, $recentOrders->first(), $state->customerPhone);
                if (in_array($recentOrders->first()->payment_status, ['unpaid', 'pending'], true) && $recentOrders->first()->status !== 'cancelled') {
                    $orderFlow->setStep($chat, \App\Services\OrderFlowService::STEP_TRACKING_ACTIONS, ['order_id' => $recentOrders->first()->id]);
                }
                return new WorkflowTransitionResult($state, [], ResponseSpec::GENERAL_ASSIST->value, $reply);
            }

            $reply = $trackingService->formatOrderList($company, $recentOrders);
            return new WorkflowTransitionResult($state, [], ResponseSpec::GENERAL_ASSIST->value, $reply);
        }

        // Quick Menu Option 3: Talk to Agent
        if (($lowerRaw === '3' && $lastWasQuickMenu) || str_contains($lowerRaw, 'talk to agent') || str_contains($lowerRaw, 'speak to agent') || str_contains($lowerRaw, 'speak to someone')) {
            $handoffText = "Connecting you with a support representative. An agent will be with you shortly!";
            return new WorkflowTransitionResult($state->with(['step' => CheckoutStep::IDLE]), [['type' => 'RequestAgentHandoff']], ResponseSpec::GENERAL_ASSIST->value, $handoffText);
        }

        // FAQ / Store location inquiry resolution
        $faqMatch = app(\App\Services\Conversation\FaqMatchingService::class)->matchBest($company, $rawMessage, $lowerRaw);
        if ($faqMatch !== null && ! empty($faqMatch['answer'])) {
            return new WorkflowTransitionResult($state, [], ResponseSpec::GENERAL_ASSIST->value, $faqMatch['answer']);
        }

        $address = $company->settings?->business_address ?? $company->settings?->address ?? null;
        if (($intent->intent->value === 'ask_store_location' || str_contains($lowerRaw, 'location') || str_contains($lowerRaw, 'where')) && $address) {
            $locationReply = "📍 *Store Location:*\n" . $address . "\n\nFeel free to ask if you need help finding us or ordering online!";
            return new WorkflowTransitionResult($state, [], ResponseSpec::GENERAL_ASSIST->value, $locationReply);
        }

        if (($intent->intent === CommerceIntent::ASK_PRODUCT_INFO || $intent->intent === CommerceIntent::ASK_PRICE) && empty($intent->product) && empty($intent->resolvedProductId)) {
            $catalogText = ResponseSpecRenderer::renderCatalogPrompt($company);
            return new WorkflowTransitionResult($state, [], ResponseSpec::GENERAL_ASSIST->value, $catalogText);
        }

        if ($intent->intent === CommerceIntent::GENERAL_CHAT) {
            $greetingText = $greetingService->buildOpening($company, $state->customerName);
            return new WorkflowTransitionResult($state, [], ResponseSpec::GENERAL_ASSIST->value, $greetingText);
        }

        $reply = $intent->clarificationQuestion ?? "I didn't quite get that! I can help you check prices, recommend products, or place an order. Reply 'prices' anytime to browse our catalog!";

        return new WorkflowTransitionResult($state, [], ResponseSpec::GENERAL_ASSIST->value, $reply);
    }

    private function handleSelectingVariant(ConversationState $state, IntentResult $intent, Company $company): WorkflowTransitionResult
    {
        $productId = $state->pendingDraftData['selecting_product_id'] ?? null;
        $qty = $state->pendingDraftData['selecting_qty'] ?? 1;
        $rawMessage = mb_strtolower(trim((string) ($intent->messageText ?? $intent->rawPayload['incoming_message'] ?? '')));

        if (! $productId) {
            $nextState = $state->with(['step' => CheckoutStep::BUILDING_CART]);
            return $this->handleBuildingCart($nextState, $intent, $company);
        }

        $product = Product::find($productId);
        if (! $product) {
            $nextState = $state->with(['step' => CheckoutStep::BUILDING_CART]);
            return $this->handleBuildingCart($nextState, $intent, $company);
        }

        // Check for cancellation / rejection / refusal / change of mind
        $isCancelOrReject = $intent->intent === CommerceIntent::CANCEL_ORDER ||
            $intent->intent === CommerceIntent::REMOVE_FROM_CART ||
            str_contains($rawMessage, "don't want") ||
            str_contains($rawMessage, "dont want") ||
            str_contains($rawMessage, "cancel") ||
            str_contains($rawMessage, "no thanks") ||
            str_contains($rawMessage, "nevermind") ||
            str_contains($rawMessage, "why have you") ||
            str_contains($rawMessage, "why did you") ||
            $rawMessage === 'no' ||
            $rawMessage === 'stop';

        if ($isCancelOrReject) {
            $nextState = $state->with([
                'step' => CheckoutStep::BUILDING_CART,
                'pendingDraftData' => array_diff_key($state->pendingDraftData, ['selecting_product_id' => 1, 'selecting_qty' => 1]),
            ]);
            $productName = $product->name ?? 'item';
            $reply = "No problem! I've cancelled adding *{$productName}* to your cart. Reply 'prices' to see our catalog or let me know how else I can help!";
            return new WorkflowTransitionResult($nextState, [], ResponseSpec::GENERAL_ASSIST->value, $reply);
        }

        // Check for Store Location / FAQ / Greeting / Product Info / Price Catalog / Topic Switches
        if (
            $intent->intent === CommerceIntent::ASK_STORE_LOCATION ||
            $intent->intent === CommerceIntent::ASK_FAQ ||
            $intent->intent === CommerceIntent::GENERAL_CHAT ||
            $intent->intent === CommerceIntent::ASK_PRODUCT_INFO ||
            $rawMessage === 'prices' ||
            $rawMessage === 'catalog' ||
            str_contains($rawMessage, 'price') ||
            str_contains($rawMessage, 'catalog') ||
            str_contains($rawMessage, 'location') ||
            str_contains($rawMessage, 'where is your shop') ||
            str_contains($rawMessage, 'where are you')
        ) {
            $nextState = $state->with([
                'step' => CheckoutStep::BUILDING_CART,
                'pendingDraftData' => array_diff_key($state->pendingDraftData, ['selecting_product_id' => 1, 'selecting_qty' => 1, 'selecting_variant_retries' => 1]),
            ]);
            return $this->handleBuildingCart($nextState, $intent, $company);
        }

        $variants = $product->activeVariants()->get()->values();
        $selectedVariant = null;

        // 0. Try resolved variant ID from ephemeral token mapping
        if ($intent->resolvedVariantId) {
            $selectedVariant = $variants->first(fn ($v) => $v->id == $intent->resolvedVariantId);
        }

        // 1. Try numeric selection (e.g. "1", "2")
        if (! $selectedVariant && is_numeric($rawMessage)) {
            $index = ((int) $rawMessage) - 1;
            if (isset($variants[$index])) {
                $selectedVariant = $variants[$index];
            }
        }

        // 2. Try variant extracted by NLU (e.g. $intent->variant = "red")
        if (! $selectedVariant && $intent->variant) {
            $targetVar = mb_strtolower(trim($intent->variant));
            foreach ($variants as $var) {
                $varLabel = mb_strtolower($var->label ?? $var->name ?? '');
                if ($varLabel !== '' && (str_contains($varLabel, $targetVar) || str_contains($targetVar, $varLabel))) {
                    $selectedVariant = $var;
                    break;
                }
            }
        }

        // 3. Try text match in rawMessage (e.g. "Red", "red", "the red ones bro", "I want the red earphones l")
        if (! $selectedVariant) {
            foreach ($variants as $var) {
                $varLabel = $var->label ?? $var->name ?? '';
                $varLower = mb_strtolower($varLabel);
                if ($varLower !== '' && (str_contains($rawMessage, $varLower) || str_contains($varLower, $rawMessage))) {
                    $selectedVariant = $var;
                    break;
                }
            }
        }

        if ($selectedVariant) {
            $varLabel = $selectedVariant->label ?? $selectedVariant->name ?? 'Option';
            $displayName = $product->name . ' (' . $varLabel . ')';
            $nextState = $this->domain->addToCart($state, $product, $qty, $selectedVariant->id);
            $nextState = $nextState->with([
                'step' => CheckoutStep::BUILDING_CART,
                'pendingDraftData' => array_diff_key($state->pendingDraftData, ['selecting_product_id' => 1, 'selecting_qty' => 1]),
            ]);

            $domainFacts = [
                'added_product_name' => $displayName,
                'added_product_qty' => $qty,
            ];

            $reply = $this->renderer->render(ResponseSpec::CART_SUMMARY, $nextState, $company, $domainFacts);

            $extra = [];
            if (! empty($product->image_url)) {
                $extra = ['image_url' => $product->image_url];
            }

            return new WorkflowTransitionResult($nextState, [], ResponseSpec::CART_SUMMARY->value, $reply, null, $extra);
        }

        // Failsafe Retry Counter: Prevent infinite re-prompting loops if input is unparseable
        $retries = ((int) ($state->pendingDraftData['selecting_variant_retries'] ?? 0)) + 1;
        if ($retries >= 2) {
            $nextState = $state->with([
                'step' => CheckoutStep::BUILDING_CART,
                'pendingDraftData' => array_diff_key($state->pendingDraftData, [
                    'selecting_product_id' => 1,
                    'selecting_qty' => 1,
                    'selecting_variant_retries' => 1,
                ]),
            ]);
            $reply = "I'm having trouble matching your option. I've cancelled adding *{$product->name}* for now. Reply 'prices' to see our catalog or let me know how I can help!";
            return new WorkflowTransitionResult($nextState, [], ResponseSpec::GENERAL_ASSIST->value, $reply);
        }

        $nextState = $state->with([
            'pendingDraftData' => array_merge($state->pendingDraftData, ['selecting_variant_retries' => $retries]),
        ]);

        $reply = $this->renderer->render(
            ResponseSpec::PROMPT_VARIANT_SELECTION,
            $nextState,
            $company,
            ['product' => $product, 'variants' => $variants]
        );

        return new WorkflowTransitionResult($nextState, [], ResponseSpec::PROMPT_VARIANT_SELECTION->value, $reply);
    }

    private function handleCollectingAddress(ConversationState $state, IntentResult $intent, Company $company): WorkflowTransitionResult
    {
        $rawMessage = mb_strtolower(trim((string) ($intent->messageText ?? $intent->rawPayload['incoming_message'] ?? '')));

        if ($intent->intent === CommerceIntent::CHOOSE_PICKUP || str_contains($rawMessage, 'pickup')) {
            $nextState = $state->with([
                'step' => CheckoutStep::REVIEWING_ORDER,
                'fulfillmentType' => 'pickup',
                'deliveryAddress' => 'Store Pickup',
            ]);
            $reply = $this->renderer->render(ResponseSpec::PROMPT_ORDER_CONFIRMATION, $nextState, $company);

            return new WorkflowTransitionResult($nextState, [], ResponseSpec::PROMPT_ORDER_CONFIRMATION->value, $reply);
        }

        $rememberedAddress = $state->pendingDraftData['remembered_address'] ?? $this->getRememberedAddressForState($state, $company);

        if ($rememberedAddress && ($this->wantsConfirmRememberedAddress($rawMessage) || $rawMessage === '1')) {
            $nextState = $state->with([
                'step' => CheckoutStep::REVIEWING_ORDER,
                'deliveryAddress' => $rememberedAddress,
                'fulfillmentType' => 'delivery',
            ]);
            $reply = $this->renderer->render(ResponseSpec::PROMPT_ORDER_CONFIRMATION, $nextState, $company);

            return new WorkflowTransitionResult($nextState, [], ResponseSpec::PROMPT_ORDER_CONFIRMATION->value, $reply);
        }

        $addressInput = $intent->address ?? $rawMessage;

        if ($addressInput && $this->domain->isValidAddress($addressInput)) {
            $nextState = $state->with([
                'step' => CheckoutStep::REVIEWING_ORDER,
                'deliveryAddress' => trim((string) $intent->address ?: (string) $intent->rawPayload['incoming_message']),
                'fulfillmentType' => 'delivery',
            ]);
            $reply = $this->renderer->render(ResponseSpec::PROMPT_ORDER_CONFIRMATION, $nextState, $company);

            return new WorkflowTransitionResult($nextState, [], ResponseSpec::PROMPT_ORDER_CONFIRMATION->value, $reply);
        }

        $facts = $rememberedAddress ? ['remembered_address' => $rememberedAddress] : [];
        $reply = $this->renderer->render(ResponseSpec::PROMPT_DELIVERY_ADDRESS, $state, $company, $facts);

        return new WorkflowTransitionResult($state, [], ResponseSpec::PROMPT_DELIVERY_ADDRESS->value, $reply);
    }

    private function handleReviewingOrder(ConversationState $state, IntentResult $intent, Company $company): WorkflowTransitionResult
    {
        $rawMessage = mb_strtolower(trim((string) ($intent->messageText ?? $intent->rawPayload['incoming_message'] ?? '')));

        $isAffirmativeConfirm = $intent->intent === CommerceIntent::CONFIRM_ORDER ||
            $intent->intent === CommerceIntent::START_CHECKOUT ||
            $rawMessage === '1' ||
            str_contains($rawMessage, 'confirm') ||
            str_contains($rawMessage, 'place order') ||
            str_contains($rawMessage, 'yes') ||
            str_contains($rawMessage, 'ok') ||
            str_contains($rawMessage, 'sure');

        if ($isAffirmativeConfirm) {
            $order = $this->domain->createOrder($company, $state);

            $nextState = $state->with([
                'step' => CheckoutStep::SELECTING_PAYMENT_METHOD,
                'pendingOrderId' => $order->id,
            ]);

            $reply = $this->renderer->render(ResponseSpec::PROMPT_PAYMENT_SELECTION, $nextState, $company, ['order' => $order]);

            return new WorkflowTransitionResult(
                nextState: $nextState,
                executedActions: [['type' => 'CreateOrder', 'payload' => ['order_id' => $order->id]]],
                responseSpec: ResponseSpec::PROMPT_PAYMENT_SELECTION->value,
                customerReply: $reply
            );
        }

        if ($rawMessage === '2' || str_contains($rawMessage, 'cancel')) {
            $nextState = $state->with([
                'step' => CheckoutStep::BUILDING_CART,
            ]);
            $reply = "Order cancelled. Your items are still in your cart. Reply 'checkout' whenever you're ready to order!";

            return new WorkflowTransitionResult($nextState, [], ResponseSpec::GENERAL_ASSIST->value, $reply);
        }

        if (str_contains($rawMessage, 'cart') || str_contains($rawMessage, 'item') || $intent->intent === CommerceIntent::ADD_TO_CART || $intent->intent === CommerceIntent::REMOVE_FROM_CART) {
            $nextState = $state->with(['step' => CheckoutStep::BUILDING_CART]);
            return $this->handleBuildingCart($nextState, $intent, $company);
        }

        $reply = $this->renderer->render(ResponseSpec::PROMPT_ORDER_CONFIRMATION, $state, $company);

        return new WorkflowTransitionResult($state, [], ResponseSpec::PROMPT_ORDER_CONFIRMATION->value, $reply);
    }

    private function handleSelectingPayment(ConversationState $state, IntentResult $intent, Company $company): WorkflowTransitionResult
    {
        $rawMsg = mb_strtolower(trim((string) ($intent->messageText ?? $intent->rawPayload['incoming_message'] ?? '')));
        $methods = ResponseSpecRenderer::getActivePaymentMethods($company);

        $selectedMethod = null;

        // Try numeric index matching (1-based)
        if (is_numeric($rawMsg)) {
            $idx = ((int) $rawMsg) - 1;
            if (isset($methods[$idx])) {
                $selectedMethod = $methods[$idx]['key'];
            }
        }

        // Try string matching or explicit intent payment method
        if (! $selectedMethod && $intent->paymentMethod) {
            $selectedMethod = $intent->paymentMethod;
        }

        if (! $selectedMethod) {
            foreach ($methods as $m) {
                $key = $m['key'];
                $label = mb_strtolower($m['label']);
                if (str_contains($rawMsg, $key) || str_contains($label, $rawMsg)) {
                    $selectedMethod = $key;
                    break;
                }
            }
        }

        if (! $selectedMethod) {
            if (str_contains($rawMsg, 'mpesa') || str_contains($rawMsg, 'm-pesa')) {
                $selectedMethod = 'mpesa';
            } elseif (str_contains($rawMsg, 'pesapal')) {
                $selectedMethod = 'pesapal';
            } elseif (str_contains($rawMsg, 'paystack')) {
                $selectedMethod = 'paystack';
            } elseif (str_contains($rawMsg, 'stripe')) {
                $selectedMethod = 'stripe';
            } elseif (str_contains($rawMsg, 'flutterwave')) {
                $selectedMethod = 'flutterwave';
            } elseif (str_contains($rawMsg, 'cod') || str_contains($rawMsg, 'cash')) {
                $selectedMethod = 'cod';
            }
        }

        if (! $selectedMethod && ! empty($methods)) {
            $selectedMethod = $methods[0]['key'];
        }

        $nextState = $state->with([
            'step' => CheckoutStep::ORDER_COMPLETED,
            'selectedPaymentMethod' => $selectedMethod ?? 'cod',
        ]);

        $order = \App\Models\Order::find($state->pendingOrderId);

        if ($selectedMethod === 'mpesa') {
            $nextState = $state->with([
                'step' => CheckoutStep::PROVIDING_PHONE,
                'selectedPaymentMethod' => 'mpesa',
            ]);
            $reply = $this->renderer->render(ResponseSpec::PROMPT_MPESA_PHONE, $nextState, $company);

            return new WorkflowTransitionResult($nextState, [], ResponseSpec::PROMPT_MPESA_PHONE->value, $reply);
        }

        $paymentService = app(\App\Services\OrderPaymentService::class);
        $payUrl = null;
        $gatewayError = null;
        $isFallbackUrl = false;

        if ($order) {
            $order->update(['payment_method' => $selectedMethod]);

            if ($selectedMethod === 'paystack') {
                $res = $paymentService->createPaystackPaymentLinkForOrder($order);
                $payUrl = $res['url'] ?? null;
                $gatewayError = $res['error'] ?? null;
            } elseif ($selectedMethod === 'pesapal') {
                $res = $paymentService->createPesapalPaymentLinkForOrder($order);
                $payUrl = $res['url'] ?? null;
                $gatewayError = $res['error'] ?? null;
            } elseif ($selectedMethod === 'stripe') {
                $res = $paymentService->createStripePaymentLinkForOrder($order);
                $payUrl = $res['url'] ?? null;
                $gatewayError = $res['error'] ?? null;
            } elseif ($selectedMethod === 'flutterwave') {
                $res = $paymentService->createFlutterwavePaymentLinkForOrder($order);
                $payUrl = $res['url'] ?? null;
                $gatewayError = $res['error'] ?? null;
            }
        }

        if (! $payUrl) {
            $isFallbackUrl = true;
            $slug = $company->store_slug ?: \Illuminate\Support\Str::slug($company->name) ?: ('store-'.$company->id);
            $phone = preg_replace('/\D+/', '', $state->customerPhone ?? '');
            $orderId = $order?->id ?? $state->pendingOrderId;
            $payUrl = url('/s/'.$slug.'?phone='.urlencode($phone).($orderId ? '&order='.$orderId : ''));
        }

        if ($selectedMethod === 'cod' || $selectedMethod === 'manual') {
            $reply = "🎉 *Order Confirmed! (Cash on Delivery)*\nOrder #: *".($order?->order_number ?? $state->pendingOrderId)."*\nTotal: *".($order?->formatted_total ?? '$'.$state->calculateCartTotal())."*\nDelivery Address: *".($state->deliveryAddress ?? 'Store Pickup')."*\n\nThank you! We will prepare your items and collect payment upon delivery.";
            return new WorkflowTransitionResult($nextState, [], ResponseSpec::ORDER_RECEIPT_CONFIRMATION->value, $reply);
        }

        $methodLabel = strtoupper((string) $selectedMethod);
        $ctaButtonText = 'Shop Online';
        if (! $isFallbackUrl) {
            $ctaButtonText = match ($selectedMethod) {
                'paystack' => 'Pay via Paystack',
                'pesapal' => 'Pay via Pesapal',
                'stripe' => 'Pay via Stripe',
                'flutterwave' => 'Pay via Flutterwave',
                default => 'Pay Online',
            };
        }

        if ($isFallbackUrl) {
            $errNote = $gatewayError ? "\n\n⚠️ _Note: {$gatewayError}_" : "";
            $reply = "💳 *{$methodLabel} Payment:* \nThank you! Your order #".($order?->order_number ?? $state->pendingOrderId)." has been recorded.{$errNote}\n\n🔗 *Click the link below to complete your order and payment online:*\n{$payUrl}\n\n_(Reply 'change payment' if you would like to switch payment method, e.g. to Cash on Delivery or M-Pesa)_";
        } else {
            $reply = "💳 *{$methodLabel} Payment Details:*\nThank you! Your order #".($order?->order_number ?? $state->pendingOrderId)." has been recorded.\n\n🔗 *Click the link below to complete your payment:*\n{$payUrl}";
        }

        return new WorkflowTransitionResult(
            nextState: $nextState,
            executedActions: [],
            responseSpec: ResponseSpec::PAYMENT_INSTRUCTIONS->value,
            customerReply: $reply,
            payUrl: $payUrl,
            ctaButtonText: $ctaButtonText,
            extra: [
                'cta_url' => $payUrl,
                'cta_button_text' => $ctaButtonText,
            ]
        );
    }

    private function handleProvidingPhone(ConversationState $state, IntentResult $intent, Company $company): WorkflowTransitionResult
    {
        $rawMessage = mb_strtolower(trim((string) ($intent->messageText ?? $intent->rawPayload['incoming_message'] ?? '')));

        $phone = $state->customerPhone;
        if ($rawMessage !== '1' && preg_match('/\d{9,}/', $rawMessage, $matches)) {
            $phone = $matches[0];
        }

        $nextState = $state->with([
            'step' => CheckoutStep::AWAITING_PAYMENT,
        ]);
        $reply = $this->renderer->render(ResponseSpec::MPESA_PUSH_SENT_NOTICE, $nextState, $company, ['phone' => $phone]);

        return new WorkflowTransitionResult(
            nextState: $nextState,
            executedActions: [['type' => 'TriggerMpesaStkPush', 'payload' => ['phone' => $phone, 'order_id' => $state->pendingOrderId]]],
            responseSpec: ResponseSpec::MPESA_PUSH_SENT_NOTICE->value,
            customerReply: $reply
        );
    }

    private function handleAwaitingPayment(ConversationState $state, IntentResult $intent, Company $company): WorkflowTransitionResult
    {
        $rawMessage = mb_strtolower(trim((string) ($intent->messageText ?? $intent->rawPayload['incoming_message'] ?? '')));

        $isCancelRequest = $intent->intent === CommerceIntent::CANCEL_ORDER ||
            $intent->intent === CommerceIntent::REMOVE_FROM_CART ||
            $intent->actionDirective === 'CANCEL_FLOW' ||
            str_contains($rawMessage, "don't want") ||
            str_contains($rawMessage, "dont want") ||
            str_contains($rawMessage, "cancel") ||
            str_contains($rawMessage, "stop") ||
            str_contains($rawMessage, "nevermind") ||
            str_contains($rawMessage, "no longer");

        if ($isCancelRequest) {
            $nextState = $state->with([
                'step' => CheckoutStep::BUILDING_CART,
                'cartItems' => [],
                'pendingOrderId' => null,
                'deliveryAddress' => null,
            ]);

            if ($state->pendingOrderId) {
                $order = \App\Models\Order::find($state->pendingOrderId);
                if ($order && $order->status === 'pending') {
                    $order->update(['status' => 'cancelled']);
                }
            }

            $reply = "No problem! I've cancelled your order request. Reply 'prices' whenever you'd like to browse our catalog or start a new order!";
            return new WorkflowTransitionResult($nextState, [], ResponseSpec::GENERAL_ASSIST->value, $reply);
        }

        // Check if user explicitly wants to change payment method or names a payment method
        $isGeneralChangePaymentRequest = $intent->intent === CommerceIntent::CHOOSE_PAYMENT_METHOD ||
            str_contains($rawMessage, 'change payment') ||
            str_contains($rawMessage, 'choose another') ||
            str_contains($rawMessage, 'different payment') ||
            str_contains($rawMessage, 'other payment') ||
            str_contains($rawMessage, 'switch payment') ||
            str_contains($rawMessage, 'another method') ||
            str_contains($rawMessage, 'payment method');

        $matchedMethodKey = null;
        $isSingleDigitMenuOption = in_array(trim($rawMessage), ['1', '2', '3'], true);

        if ($intent->paymentMethod) {
            $matchedMethodKey = $intent->paymentMethod;
        } elseif (! $isSingleDigitMenuOption) {
            $activeMethods = ResponseSpecRenderer::getActivePaymentMethods($company);
            foreach ($activeMethods as $m) {
                $k = $m['key'];
                $lbl = mb_strtolower($m['label']);
                if ($rawMessage !== '' && (str_contains($rawMessage, $k) || str_contains($lbl, $rawMessage))) {
                    $matchedMethodKey = $k;
                    break;
                }
            }
            if (! $matchedMethodKey) {
                if (str_contains($rawMessage, 'mpesa') || str_contains($rawMessage, 'm-pesa')) {
                    $matchedMethodKey = 'mpesa';
                } elseif (str_contains($rawMessage, 'pesapal')) {
                    $matchedMethodKey = 'pesapal';
                } elseif (str_contains($rawMessage, 'paystack')) {
                    $matchedMethodKey = 'paystack';
                } elseif (str_contains($rawMessage, 'stripe')) {
                    $matchedMethodKey = 'stripe';
                } elseif (str_contains($rawMessage, 'flutterwave')) {
                    $matchedMethodKey = 'flutterwave';
                } elseif (str_contains($rawMessage, 'cod') || str_contains($rawMessage, 'cash')) {
                    $matchedMethodKey = 'cod';
                }
            }
        }

        if ($matchedMethodKey) {
            $selectingState = $state->with(['step' => CheckoutStep::SELECTING_PAYMENT_METHOD]);
            $modifiedIntent = new IntentResult(
                intent: CommerceIntent::CHOOSE_PAYMENT_METHOD,
                confidence: 1.0,
                paymentMethod: $matchedMethodKey,
                messageText: $matchedMethodKey,
                rawPayload: $intent->rawPayload
            );
            return $this->handleSelectingPayment($selectingState, $modifiedIntent, $company);
        }

        if ($isGeneralChangePaymentRequest) {
            $nextState = $state->with(['step' => CheckoutStep::SELECTING_PAYMENT_METHOD]);
            $reply = $this->renderer->render(ResponseSpec::PROMPT_PAYMENT_SELECTION, $nextState, $company);

            return new WorkflowTransitionResult(
                nextState: $nextState,
                executedActions: [],
                responseSpec: ResponseSpec::PROMPT_PAYMENT_SELECTION->value,
                customerReply: $reply
            );
        }

        // Check if user asks for payment link/info again
        $isPaymentInfoRequest = str_contains($rawMessage, 'how to pay') ||
            str_contains($rawMessage, 'payment link') ||
            str_contains($rawMessage, 'pay link') ||
            str_contains($rawMessage, 'send link') ||
            str_contains($rawMessage, 'how do i pay') ||
            str_contains($rawMessage, 'link');

        if ($isPaymentInfoRequest) {
            $selectingState = $state->with(['step' => CheckoutStep::SELECTING_PAYMENT_METHOD]);
            return $this->handleSelectingPayment($selectingState, $intent, $company);
        }

        // Check if customer wants to start a new order, view prices, or order something else
        $isNewOrderRequest = $intent->intent === CommerceIntent::ADD_TO_CART ||
            $intent->intent === CommerceIntent::START_CHECKOUT ||
            $intent->intent === CommerceIntent::ASK_PRODUCT_INFO ||
            $intent->intent === CommerceIntent::SELECT_OPTION ||
            ! empty($intent->resolvedProductId) ||
            ! empty($intent->selectedToken) ||
            is_numeric($rawMessage) ||
            str_contains($rawMessage, 'price') ||
            str_contains($rawMessage, 'catalog') ||
            str_contains($rawMessage, 'menu') ||
            str_contains($rawMessage, 'something else') ||
            str_contains($rawMessage, 'new order') ||
            str_contains($rawMessage, 'order again');

        if ($isNewOrderRequest) {
            $nextState = $state->with([
                'step' => CheckoutStep::BUILDING_CART,
                'cartItems' => [],
                'pendingOrderId' => null,
                'deliveryAddress' => null,
            ]);
            return $this->handleBuildingCart($nextState, $intent, $company);
        }

        if (str_contains($rawMessage, 'cart')) {
            $reply = "🛒 Your cart is currently empty (your items were placed in Order #{$state->pendingOrderId}).\n\nReply with 'prices' or a product name to place a new order!";
            return new WorkflowTransitionResult($state, [], ResponseSpec::GENERAL_ASSIST->value, $reply);
        }

        // Check if customer is asking a general question, FAQ, or store inquiry (e.g. "do you deliver?", "where are you located")
        $isGeneralInquiry = $intent->intent === CommerceIntent::GENERAL_CHAT ||
            $intent->intent === CommerceIntent::ASK_FAQ ||
            $intent->intent === CommerceIntent::ASK_STORE_LOCATION ||
            $intent->intent === CommerceIntent::ASK_DELIVERY_FEE ||
            $intent->intent === CommerceIntent::REQUEST_HUMAN ||
            str_contains($rawMessage, 'deliver') ||
            str_contains($rawMessage, 'shipping') ||
            str_contains($rawMessage, 'location') ||
            str_contains($rawMessage, 'who are') ||
            str_contains($rawMessage, 'where are') ||
            str_contains($rawMessage, 'what do you');

        if ($isGeneralInquiry) {
            $readOnlyAssistant = app(\App\Services\AI\ReadOnlyLlmAssistantService::class);
            $aiReply = $readOnlyAssistant->generateReply($company, $state, $intent, []);

            $reply = $aiReply."\n\n_(Order #{$state->pendingOrderId} update: Your order has been recorded. Reply 'change payment' if you'd like to switch payment method)_";
            return new WorkflowTransitionResult($state, [], ResponseSpec::GENERAL_ASSIST->value, $reply);
        }

        $reply = "Your order #{$state->pendingOrderId} is being processed. Thank you for shopping with us! Reply with 'prices' if you'd like to place another order, or reply 'change payment' to switch payment method.";

        return new WorkflowTransitionResult($state, [], ResponseSpec::GENERAL_ASSIST->value, $reply);
    }

    private function getRememberedAddressForState(ConversationState $state, Company $company): ?string
    {
        $query = \App\Models\Order::where('company_id', $company->id)
            ->whereNotNull('delivery_address')
            ->where('delivery_address', '!=', '')
            ->where('delivery_address', '!=', 'Store Pickup')
            ->where('delivery_address', '!=', 'Dine-In')
            ->where('delivery_address', '!=', 'N/A');

        if ($state->customerPhone) {
            $query->where(function ($q) use ($state) {
                $q->where('chat_id', $state->chatId)
                  ->orWhere('customer_phone', $state->customerPhone);
            });
        } else {
            $query->where('chat_id', $state->chatId);
        }

        $addr = $query->orderByDesc('id')->value('delivery_address');
        return $addr ? trim((string) $addr) : null;
    }

    private function wantsConfirmRememberedAddress(string $lower): bool
    {
        if (in_array($lower, ['1', 'yes', 'yep', 'yeah', 'ok', 'sure', 'confirm', 'use this', 'use that', 'same', 'use saved', 'same address'], true)) {
            return true;
        }
        return str_contains($lower, 'use this address')
            || str_contains($lower, 'use that address')
            || str_contains($lower, 'same address')
            || str_contains($lower, 'use saved')
            || str_contains($lower, 'use previous')
            || str_contains($lower, 'confirm address');
    }
}
