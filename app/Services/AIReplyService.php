<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Faq;
use App\Models\PlatformSetting;
use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIReplyService
{
    public function __construct(
        protected WhatsAppMessageSenderService $whatsAppSender
    ) {}

    /**
     * Get reply text for an incoming customer message: try FAQ match first, then OpenAI.
     */
    public function getReplyForMessage(Company $company, string $customerMessage, ?string $customerName = null): string
    {
        $message = trim($customerMessage);
        if ($message === '') {
            return $this->fallbackReply($company);
        }

        $lower = mb_strtolower($message);

        // 1) Greeting / first message
        if ($this->looksLikeGreeting($lower)) {
            $settings = $company->settings;
            $greeting = $settings?->ai_greeting;
            if ($greeting) {
                return $greeting;
            }
            return "Hello" . ($customerName ? " {$customerName}" : '') . "! Thanks for reaching out. How can we help you today?";
        }

        // 2) Keyword triggers: price, prices, catalog, menu, order, etc.
        $keywordReply = $this->matchKeywordReply($company, $lower);
        if ($keywordReply !== null) {
            return $keywordReply;
        }

        // 3) FAQ match (question/answer or keywords)
        $faqReply = $this->matchFaq($company, $message, $lower);
        if ($faqReply !== null) {
            return $faqReply;
        }

        // 4) OpenAI for open-ended questions (if API key set)
        $openAiReply = $this->getOpenAiReply($company, $message, $customerName);
        if ($openAiReply !== null) {
            return $openAiReply;
        }

        return $this->fallbackReply($company);
    }

    protected function looksLikeGreeting(string $lower): bool
    {
        $greetings = ['hi', 'hello', 'hey', 'good morning', 'good afternoon', 'good evening', 'salam', 'marhaba', 'hola'];
        foreach ($greetings as $g) {
            if ($lower === $g || str_starts_with($lower, $g . ' ') || str_starts_with($lower, $g . ',')) {
                return true;
            }
        }
        return false;
    }

    protected function matchKeywordReply(Company $company, string $lower): ?string
    {
        if (str_contains($lower, 'price') || str_contains($lower, 'prices') || str_contains($lower, 'how much')) {
            return $this->formatProductList($company);
        }
        if (str_contains($lower, 'catalog') || str_contains($lower, 'menu') || str_contains($lower, 'products') || str_contains($lower, 'list')) {
            return $this->formatProductList($company);
        }
        if (str_contains($lower, 'order') || str_contains($lower, 'place order')) {
            return "You can place an order by telling us the product name and quantity. For example: \"I want 2 x Product Name\". We'll confirm availability and delivery details.";
        }
        return null;
    }

    protected function formatProductList(Company $company): string
    {
        $products = Product::where('company_id', $company->id)->where('status', 'active')->orderBy('name')->get();
        if ($products->isEmpty()) {
            return "Our product list is being updated. Please ask us what you're looking for and we'll help you.";
        }
        $lines = ["Here are our products and prices:\n"];
        foreach ($products->take(30) as $p) {
            $price = is_numeric($p->price) ? number_format((float) $p->price, 2) : $p->price;
            $lines[] = "• {$p->name}: {$price}";
        }
        $lines[] = "\nReply with the product name and quantity to order.";
        return implode("\n", $lines);
    }

    protected function matchFaq(Company $company, string $message, string $lower): ?string
    {
        $faqs = Faq::where('company_id', $company->id)->where('is_active', true)->get();
        foreach ($faqs as $faq) {
            $question = mb_strtolower($faq->question);
            if (str_contains($lower, $question) || str_contains($question, $lower)) {
                $faq->increment('usage_count');
                return $faq->answer;
            }
            $keywords = $faq->keywords;
            if (is_array($keywords)) {
                foreach ($keywords as $kw) {
                    if (str_contains($lower, mb_strtolower((string) $kw))) {
                        $faq->increment('usage_count');
                        return $faq->answer;
                    }
                }
            }
        }
        return null;
    }

    protected function getOpenAiReply(Company $company, string $message, ?string $customerName): ?string
    {
        $platform = PlatformSetting::first();
        $apiKey = $platform?->openai_api_key ?? config('openai.api_key');
        if (! $apiKey) {
            return null;
        }

        $model = $platform?->openai_model ?? config('openai.model', 'gpt-4o-mini');
        $maxTokens = $platform?->openai_max_tokens ?? config('openai.max_tokens', 512);

        $systemPrompt = $this->buildSystemPrompt($company);
        $userPrompt = $customerName ? "[Customer name: {$customerName}]\n\nMessage: {$message}" : "Customer message: {$message}";

        try {
            $response = Http::withToken($apiKey)
                ->timeout(20)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'max_tokens' => $maxTokens,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('OpenAI API error', ['status' => $response->status(), 'body' => $response->json()]);
                return null;
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? null;
            return $content ? trim($content) : null;
        } catch (\Throwable $e) {
            Log::error('OpenAI request failed', ['message' => $e->getMessage()]);
            return null;
        }
    }

    protected function buildSystemPrompt(Company $company): string
    {
        $settings = $company->settings;
        $tone = $settings?->ai_tone ?? 'friendly and professional';
        $name = $company->name;
        $parts = [
            "You are a helpful customer service assistant for the business: {$name}.",
            "Reply in a {$tone} tone. Keep replies concise (1-3 short paragraphs).",
            "Do not invent prices or product names. If the customer asks about prices or products, say you'll share the catalog or ask them to type 'prices' or 'catalog'.",
        ];
        $faqs = Faq::where('company_id', $company->id)->where('is_active', true)->get();
        if ($faqs->isNotEmpty()) {
            $parts[] = "\nKnowledge base (use this when relevant):";
            foreach ($faqs->take(15) as $faq) {
                $parts[] = "Q: {$faq->question}\nA: {$faq->answer}";
            }
        }
        $products = Product::where('company_id', $company->id)->where('status', 'active')->get();
        if ($products->isNotEmpty()) {
            $parts[] = "\nProducts (do not invent; refer to catalog if they ask):";
            foreach ($products->take(20) as $p) {
                $parts[] = "- {$p->name}: {$p->price}";
            }
        }
        return implode("\n", $parts);
    }

    protected function fallbackReply(Company $company): string
    {
        $settings = $company->settings;
        if ($settings && method_exists($settings, 'getAttributes')) {
            $fallback = $settings->getAttributes()['fallback_message'] ?? null;
            if ($fallback) {
                return $fallback;
            }
        }
        return "Thanks for your message. Our team will get back to you shortly. You can also type 'prices' for our product list or 'order' to place an order.";
    }
}
