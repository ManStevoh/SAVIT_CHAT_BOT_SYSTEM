<?php

namespace App\Services\AI;

use App\DTOs\ConversationState;
use App\DTOs\IntentResult;
use App\Models\Company;
use App\Services\Workflow\ResponseSpecRenderer;
use Illuminate\Support\Facades\Log;

final class ReadOnlyLlmAssistantService
{
    public function __construct(
        private AiGateway $aiGateway,
        private ReadOnlyContextBuilder $contextBuilder,
        private ReadOnlyToolExecutor $toolExecutor
    ) {}

    public function generateReply(
        Company $company,
        ConversationState $state,
        IntentResult $intent,
        array $candidateContext = []
    ): string {
        $userMessage = (string) ($intent->messageText ?? $intent->rawPayload['incoming_message'] ?? '');
        $lowerMsg = mb_strtolower(trim($userMessage));

        // 0. Check for pure greetings (hello, hi, hey, good morning, etc.)
        $greetingService = app(\App\Services\Conversation\ConversationGreetingService::class);
        if ($greetingService->isPureGreeting($userMessage)) {
            return $greetingService->buildOpening($company, $state->customerName, null, $state->customerPhone);
        }

        // 0c-1. Batch / Catalog Image Requests (e.g. "send me the pictures of all you sell", "more", "see more")
        $cacheKey = "catalog_img_offset:{$company->id}:{$state->chatId}";
        $isMoreRequest = in_array($lowerMsg, ['more', 'see more', 'show more', 'next', 'more pictures', 'more photos', 'more images', 'yes'], true);
        $savedOffset = (int) \Illuminate\Support\Facades\Cache::get($cacheKey, 0);

        $isImageQuery = str_contains($lowerMsg, 'image') || str_contains($lowerMsg, 'picture') || str_contains($lowerMsg, 'photo') || str_contains($lowerMsg, 'look like');

        $isCatalogImagesQuery = (
            $isImageQuery && (
                str_contains($lowerMsg, 'all') ||
                str_contains($lowerMsg, 'everything') ||
                str_contains($lowerMsg, 'catalog') ||
                str_contains($lowerMsg, 'products') ||
                str_contains($lowerMsg, 'items') ||
                str_contains($lowerMsg, 'you sell') ||
                str_contains($lowerMsg, 'what you sell')
            )
        ) || ($isMoreRequest && $savedOffset > 0);

        if ($isCatalogImagesQuery) {
            $offset = $isMoreRequest ? $savedOffset : 0;

            $activeProducts = \App\Models\Product::where('company_id', $company->id)
                ->where('status', 'active')
                ->with(['images' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id')])
                ->orderBy('name')
                ->get();

            $productsWithImages = [];
            foreach ($activeProducts as $p) {
                $primaryImg = $p->primaryImage();
                $imgPath = $primaryImg?->path ?? $p->image;
                if ($imgPath) {
                    $imgUrl = filter_var($imgPath, FILTER_VALIDATE_URL) ? $imgPath : url(\Illuminate\Support\Facades\Storage::url($imgPath));
                    $productsWithImages[] = [
                        'product' => $p,
                        'url' => $imgUrl,
                    ];
                }
            }

            $totalCount = count($productsWithImages);
            if ($totalCount === 0) {
                return "📷 No photos are currently uploaded for our catalog items.\n\nReply with *prices* to browse our catalog!";
            }

            $batch = array_slice($productsWithImages, $offset, 5);
            if (empty($batch)) {
                \Illuminate\Support\Facades\Cache::forget($cacheKey);
                return "📷 *You've reached the end of our product photos!* ({$totalCount} photos total).\n\nReply with a product name or number to add items to your cart!";
            }

            $newOffset = $offset + count($batch);

            $imgTags = [];
            foreach ($batch as $item) {
                $p = $item['product'];
                $formattedPrice = \App\Support\MoneyFormatter::format((float) $p->price, $company->currency ?? 'USD');
                $imgTags[] = "[IMAGE_URL: {$item['url']} CAPTION: 📸 *{$p->name}* — {$formattedPrice}]";
            }

            $responseParts = ["🛍️ *OUR PRODUCT PHOTOS* (Showing {$newOffset} of {$totalCount}):"];
            $responseParts[] = implode("\n\n", $imgTags);

            if ($newOffset < $totalCount) {
                \Illuminate\Support\Facades\Cache::put($cacheKey, $newOffset, 900);
                $remaining = $totalCount - $newOffset;
                $responseParts[] = "📸 *Displaying {$newOffset} of {$totalCount} products.*\n\nWould you like to see more pictures?\nReply *\"more\"* or *\"yes\"* to see the next set of photos!";
            } else {
                \Illuminate\Support\Facades\Cache::forget($cacheKey);
                $responseParts[] = "📸 *Displayed all {$totalCount} product photos!*\n\nReply with a product name or number to add items to your cart!";
            }

            return implode("\n\n", $responseParts);
        }

        // 0c. Image / Picture / Photo Requests for specific products
        $isImageQuery = str_contains($lowerMsg, 'image') ||
            str_contains($lowerMsg, 'picture') ||
            str_contains($lowerMsg, 'photo') ||
            str_contains($lowerMsg, 'pic') ||
            str_contains($lowerMsg, 'look like');

        if ($isImageQuery) {
            $domain = app(\App\Services\Workflow\DomainServiceDispatcher::class);
            $targetStr = $intent->product ?? $userMessage;
            $product = $domain->findProduct($company, $targetStr);
            if (! $product && $intent->product && $intent->product !== $userMessage) {
                $product = $domain->findProduct($company, $userMessage);
            }
            if (! $product) {
                // Strip common leading phrases like "send me a picture of", "photo of", "picture of"
                $cleanedSearch = preg_replace('/^(?:send\s+me\s+a\s+|show\s+me\s+a\s+|can\s+i\s+see\s+a\s+|get\s+a\s+)?(?:picture|photo|image|pic)s?\s+(?:of\s+)?/iu', '', $userMessage);
                if ($cleanedSearch && $cleanedSearch !== $userMessage) {
                    $product = $domain->findProduct($company, trim($cleanedSearch));
                }
            }

            if ($product) {
                $formattedPrice = \App\Support\MoneyFormatter::format((float) $product->price, $company->currency ?? 'USD');
                $primaryImg = $product->primaryImage();
                $imgPath = $primaryImg?->path ?? $product->image;
                $imgUrl = null;
                if ($imgPath) {
                    if (filter_var($imgPath, FILTER_VALIDATE_URL)) {
                        $imgUrl = $imgPath;
                    } else {
                        $storageUrl = \Illuminate\Support\Facades\Storage::url($imgPath);
                        $imgUrl = str_starts_with($storageUrl, '/') ? url($storageUrl) : $storageUrl;
                    }
                }
                $descText = ! empty($product->description) ? "   _{$product->description}_\n" : "";

                if ($imgUrl) {
                    return "📸 Here is *{$product->name}*:\nPrice: {$formattedPrice}\n\n[IMAGE_URL: {$imgUrl} CAPTION: 📸 Here is *{$product->name}* — {$formattedPrice}]\n\nReply with '{$product->name}' to add it to your cart!";
                }

                return "Details for *{$product->name}*:\nPrice: {$formattedPrice}\n{$descText}(No photo is currently uploaded for this product).";
            }
        }

        // 0b. Explicit Catalog / Menu Requests (direct requests like "menu", "catalog", "prices", "what do you sell")
        $intentVal = $intent->intent->value ?? (string) $intent->intent;
        $isGenericCatalogIntent = ($intentVal === 'ask_product_info' || $intentVal === 'ask_price')
            && empty($intent->product)
            && empty($intent->resolvedProductId);

        $isExplicitCatalogRequest = ! $isImageQuery && (
            str_contains($lowerMsg, 'catalog') ||
            str_contains($lowerMsg, 'menu') ||
            str_contains($lowerMsg, 'what do you sell') ||
            str_contains($lowerMsg, 'what you sell') ||
            str_contains($lowerMsg, 'what do you guys sell') ||
            str_contains($lowerMsg, 'what items') ||
            $lowerMsg === 'prices' ||
            $lowerMsg === 'price' ||
            $lowerMsg === 'products' ||
            $lowerMsg === 'menu' ||
            $lowerMsg === '1' ||
            str_contains($lowerMsg, 'show products') ||
            str_contains($lowerMsg, 'show menu') ||
            str_contains($lowerMsg, 'price list') ||
            str_contains($lowerMsg, 'product list') ||
            str_contains($lowerMsg, 'product catalog') ||
            str_contains($lowerMsg, 'send me your catalog') ||
            $isGenericCatalogIntent
        );

        if ($isExplicitCatalogRequest) {
            return ResponseSpecRenderer::renderCatalogPrompt($company);
        }

        // 1. Check for configured store FAQs in database
        $faqMatch = app(\App\Services\Conversation\FaqMatchingService::class)->matchBest($company, $userMessage, $lowerMsg);
        if ($faqMatch !== null && ! empty($faqMatch['answer'])) {
            return \App\Services\WhatsAppMessageSenderService::cleanMarkdownLinksForWhatsApp($faqMatch['answer']);
        }

        // 2. Check for configured Store Location
        $intentVal = $intent->intent->value ?? '';
        if ($intentVal === 'ask_store_location' || $intentVal === 'ask_location' || str_contains($lowerMsg, 'location') || str_contains($lowerMsg, 'where are you')) {
            $address = $company->settings?->business_address ?? $company->settings?->address ?? null;
            if ($address && $address !== 'Online Store') {
                return "📍 *Store Location:*\n{$address}\n\nFeel free to ask if you need help finding us or ordering online!";
            }
        }

        // 3. Check for configured Store Opening Hours
        if ($intentVal === 'ask_hours' || str_contains($lowerMsg, 'hours') || str_contains($lowerMsg, 'opening time') || str_contains($lowerMsg, 'when do you open')) {
            $hours = $company->settings?->business_hours ?? $company->settings?->opening_hours ?? null;
            if ($hours) {
                return "🕒 *Opening Hours:*\n{$hours}\n\nReply with 'prices' anytime to browse our catalog!";
            }
        }

        $context = $this->contextBuilder->build($company, $state, $candidateContext);

        $systemPrompt = $this->buildSystemPrompt($context['store']['name'] ?? 'our store');
        $contextJson = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "STORE CONTEXT:\n{$contextJson}\n\nCUSTOMER MESSAGE: \"{$userMessage}\""],
        ];

        $tools = $this->toolExecutor->getToolDefinitions();

        try {
            // First LLM Call with Tool Definitions
            $result = $this->aiGateway->chatCompletionWithTools(
                messages: $messages,
                tools: $tools,
                useCase: 'whatsapp_read_only',
                company: $company,
                chatId: null,
                maxTokens: 300,
                temperature: 0.3
            );

            // Handle Tool Execution if LLM requested tool calls
            if ($result->success && ! empty($result->toolCalls)) {
                $messages[] = [
                    'role' => 'assistant',
                    'content' => $result->content ?? '',
                    'tool_calls' => $result->toolCalls,
                ];

                foreach ($result->toolCalls as $call) {
                    $fnName = $call['function']['name'] ?? $call['name'] ?? null;
                    $argsRaw = $call['function']['arguments'] ?? $call['arguments'] ?? [];
                    $args = is_array($argsRaw) ? $argsRaw : (json_decode((string) $argsRaw, true) ?: []);
                    $callId = $call['id'] ?? ('call_' . uniqid());

                    if ($fnName) {
                        $toolResult = $this->toolExecutor->executeTool($fnName, $args, $company, $state);
                        $messages[] = [
                            'role' => 'tool',
                            'tool_call_id' => $callId,
                            'name' => $fnName,
                            'content' => json_encode($toolResult, JSON_UNESCAPED_SLASHES),
                        ];
                    }
                }

                // Second LLM Call to generate final response using Tool Results
                $secondResult = $this->aiGateway->chatCompletion(
                    messages: $messages,
                    useCase: 'whatsapp_read_only',
                    company: $company,
                    chatId: null,
                    maxTokens: 300,
                    temperature: 0.3
                );

                if ($secondResult->success && ! empty($secondResult->content)) {
                    $clean = trim($secondResult->content);
                    return \App\Services\WhatsAppMessageSenderService::cleanMarkdownLinksForWhatsApp($clean);
                }
            }

            if ($result->success && ! empty($result->content)) {
                $clean = trim($result->content);
                return \App\Services\WhatsAppMessageSenderService::cleanMarkdownLinksForWhatsApp($clean);
            }
        } catch (\Throwable $e) {
            Log::warning('ReadOnlyLlmAssistantService: Tool-enabled AI completion failed, falling back', [
                'company_id' => $company->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Deterministic Fallback if LLM execution fails
        $fallback = $this->deterministicFallback($intent, $company, $context, $candidateContext);
        return \App\Services\WhatsAppMessageSenderService::cleanMarkdownLinksForWhatsApp($fallback);
    }

    private function buildSystemPrompt(string $storeName): string
    {
        return <<<PROMPT
You are an intelligent, helpful customer service assistant for {$storeName}.
Answer customer questions directly, warmly, and accurately in 1-3 sentences suitable for WhatsApp.

INSTRUCTIONS:
1. Recommend products: If the customer asks for recommendations or a category (e.g. "footwear", "books"), suggest 1-3 specific matching products from store context with prices.
2. Item Not Found: If the customer asks for an item or category NOT carried by the store (e.g. "laptops"), politely explain that the store does not stock that item, and mention what categories/items are available.
3. Clarification: If the customer's request is vague or ambiguous, ask a brief clarifying question presenting 2-3 candidate options.
4. DO NOT perform or promise transactional actions (do NOT add items to cart, place orders, cancel orders, or process payments).
5. If stating prices or options, add: "Reply with the item name or number to add it to your order!"
6. Keep answers concise and WhatsApp-friendly (under 60 words).
PROMPT;
    }

    private function deterministicFallback(IntentResult $intent, Company $company, array $context, array $candidateContext = []): string
    {
        $userMsg = mb_strtolower(trim((string) ($intent->messageText ?? '')));
        $intentVal = $intent->intent->value ?? (string) $intent->intent;

        // Order Tracking Fallback
        if (
            $intentVal === 'ask_order_status' ||
            $userMsg === '2' ||
            str_contains($userMsg, 'track') ||
            str_contains($userMsg, 'order status') ||
            str_contains($userMsg, 'my order') ||
            preg_match('/^#?\d{1,8}$/i', $userMsg) === 1
        ) {
            $trackingService = app(\App\Services\Domain\OrderTrackingService::class);
            $stateObj = new ConversationState(chatId: 1, companyId: $company->id, customerPhone: '', customerName: '');
            $specificOrder = $trackingService->findOrderByNumber($company, $userMsg);
            if ($specificOrder) {
                return $trackingService->formatOrderTrackingCard($company, $specificOrder);
            }
            $recentOrders = $trackingService->getRecentOrders($company, '');
            if ($recentOrders->isEmpty()) {
                return "🔍 *Order Tracker*\n\nWe couldn't find any recent orders associated with your phone number.\n\n• If you placed an order under a different phone number or Order ID, please reply with your *Order #* (e.g. *#160* or *160*).\n• Reply *'prices'* to browse our catalog and place your first order!";
            }
            if ($recentOrders->count() === 1) {
                return $trackingService->formatOrderTrackingCard($company, $recentOrders->first());
            }
            return $trackingService->formatOrderList($company, $recentOrders);
        }

        $candidates = $candidateContext['candidate_products'] ?? [];
        if (! empty($candidates)) {
            $recommendations = [];
            foreach (array_slice($candidates, 0, 3) as $c) {
                $pName = $c['name'] ?? 'Product';
                $price = isset($c['price']) ? '$' . number_format((float) $c['price'], 2) : '';
                $recommendations[] = "• *{$pName}*" . ($price !== '' ? " ({$price})" : '');
            }
            $recList = implode("\n", $recommendations);
            return "🛍️ *Here is what I recommend based on your search:*\n\n{$recList}\n\nReply with the product name or number to order!";
        }

        $userMsg = mb_strtolower(trim((string) ($intent->messageText ?? '')));
        if (str_contains($userMsg, 'do you sell') || str_contains($userMsg, 'do you have') || str_contains($userMsg, 'looking for') || str_contains($userMsg, 'sell ') || str_contains($userMsg, 'recommend')) {
            return "We don't currently carry that specific item in our store. Reply *prices* anytime to browse our available catalog!";
        }

        $intentVal = $intent->intent->value ?? 'general_chat';
        $storeUrl = $context['store']['url'] ?? '';
        $address = $context['store']['address'] ?? '';

        if ($intentVal === 'ask_location' || str_contains($intentVal, 'location')) {
            if ($address && $address !== 'Online Store') {
                return "📍 *Store Location:*\n{$address}\n\nFeel free to ask if you need help finding us or ordering online!";
            }
            return "📍 We operate online! You can view our shop location and browse our catalog online here:\n{$storeUrl}";
        }

        if ($intentVal === 'ask_hours' || str_contains($intentVal, 'hours')) {
            $hours = $context['store']['hours'] ?? 'Mon-Sat 8:00 AM - 6:00 PM';
            return "🕒 *Opening Hours:*\n{$hours}\n\nReply with 'prices' anytime to browse our catalog!";
        }

        return ResponseSpecRenderer::renderCatalogPrompt($company);
    }
}
