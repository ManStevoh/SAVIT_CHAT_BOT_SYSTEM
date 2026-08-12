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

        // 0b. Explicit Catalog Requests (only for direct requests like "catalog", "prices", "what do you sell")
        $isExplicitCatalogRequest = str_contains($lowerMsg, 'catalog') ||
            str_contains($lowerMsg, 'what do you sell') ||
            str_contains($lowerMsg, 'what you sell') ||
            str_contains($lowerMsg, 'what do you guys sell') ||
            $lowerMsg === 'prices' ||
            $lowerMsg === 'products' ||
            str_contains($lowerMsg, 'show products') ||
            str_contains($lowerMsg, 'product catalog') ||
            str_contains($lowerMsg, 'send me your catalog');

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
