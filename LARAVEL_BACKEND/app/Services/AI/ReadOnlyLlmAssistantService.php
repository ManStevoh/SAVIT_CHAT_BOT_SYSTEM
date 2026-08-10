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

        // If customer asks for catalog, products, prices, or what is sold, delegate directly to Systematic AI Renderer
        $isCatalogRequest = str_contains($lowerMsg, 'catalog') ||
            str_contains($lowerMsg, 'what do you sell') ||
            str_contains($lowerMsg, 'what you sell') ||
            str_contains($lowerMsg, 'what do you guys sell') ||
            str_contains($lowerMsg, 'prices') ||
            str_contains($lowerMsg, 'show products') ||
            str_contains($lowerMsg, 'product catalog') ||
            str_contains($lowerMsg, 'send me your catalog') ||
            $intent->intent === \App\Enums\CommerceIntent::ASK_PRODUCT_INFO ||
            $intent->intent === \App\Enums\CommerceIntent::ASK_PRICE;

        if ($isCatalogRequest) {
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
        $fallback = $this->deterministicFallback($intent, $company, $context);
        return \App\Services\WhatsAppMessageSenderService::cleanMarkdownLinksForWhatsApp($fallback);
    }

    private function buildSystemPrompt(string $storeName): string
    {
        return <<<PROMPT
You are a customer service assistant for {$storeName}.
Answer customer questions directly, warmly, and accurately in 1-3 sentences suitable for WhatsApp.

INSTRUCTIONS:
1. If you need details about specific products, store information, FAQs, or current cart status, call the available read-only tools.
2. DO NOT perform or promise any transactional action (do NOT add items to cart, place orders, cancel orders, or process payments).
3. If asking about item availability or prices, state the price and add: "Reply with the item name whenever you'd like to add it to your order!"
4. NEVER state "I have added X to your cart" or "Your order is updated".
5. Keep answers concise (under 60 words).
PROMPT;
    }

    private function deterministicFallback(IntentResult $intent, Company $company, array $context): string
    {
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
