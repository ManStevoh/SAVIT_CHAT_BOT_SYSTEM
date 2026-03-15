<?php

namespace App\Services;

use App\Models\Company;
use App\Models\ConversationLearningSample;
use App\Models\Faq;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Services\AI\OpenAIConversationBuilder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIReplyService
{
    private const PLATFORM_SETTINGS_CACHE_TTL = 300;

    public function __construct(
        protected WhatsAppMessageSenderService $whatsAppSender,
        protected OpenAIConversationBuilder $conversationBuilder,
        protected ConversationLearningService $learningService
    ) {}

    /**
     * Get reply text for an incoming customer message.
     *
     * @param  int|null  $chatId  For conversation history (OpenAI) and context
     */
    public function getReplyForMessage(Company $company, string $customerMessage, ?string $customerName = null, ?int $chatId = null): string
    {
        $message = trim($customerMessage);
        if ($message === '') {
            return $this->fallbackReply($company);
        }

        $lower = mb_strtolower($message);

        if ($this->isOutsideWorkingHours($company)) {
            $away = $company->settings?->away_message;
            if ($away) {
                return $away;
            }
            return "Thanks for your message. We're currently outside business hours. We'll get back to you as soon as we can.";
        }

        if ($this->looksLikeGreeting($lower)) {
            $settings = $company->settings;
            $greeting = $settings?->ai_greeting;
            if ($greeting) {
                return $this->appendQuickMenu($greeting);
            }
            $default = "Hello" . ($customerName ? " {$customerName}" : '') . "! Thanks for reaching out. How can we help you today?";
            return $this->appendQuickMenu($default);
        }

        $keywordReply = $this->matchKeywordReply($company, $lower);
        if ($keywordReply !== null) {
            return $keywordReply;
        }

        $faqReply = $this->matchFaq($company, $message, $lower);
        if ($faqReply !== null) {
            return $faqReply;
        }

        $openAiReply = $this->getOpenAiReply($company, $message, $customerName, $chatId);
        if ($openAiReply !== null) {
            return $openAiReply;
        }

        return $this->fallbackReply($company);
    }

    protected function appendQuickMenu(string $text): string
    {
        $menu = "\n\nReply with: 1. Prices  2. Order  3. Talk to agent";
        if (str_contains($text, $menu)) {
            return $text;
        }
        return rtrim($text) . $menu;
    }

    protected function isOutsideWorkingHours(Company $company): bool
    {
        $settings = $company->settings;
        if (! $settings || ! $settings->working_hours || ! is_array($settings->working_hours)) {
            return false;
        }
        $tz = $settings->timezone ?? 'UTC';
        try {
            $now = Carbon::now($tz);
        } catch (\Throwable) {
            return false;
        }
        $day = strtolower($now->format('D'));
        $schedule = $settings->working_hours[$day] ?? $settings->working_hours['*'] ?? null;
        if (! $schedule || ! is_string($schedule)) {
            return false;
        }
        $parts = explode('-', $schedule);
        if (count($parts) !== 2) {
            return false;
        }
        $open = trim($parts[0]);
        $close = trim($parts[1]);
        $current = $now->format('H:i');
        return $current < $open || $current > $close;
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
        if (str_contains($lower, 'location') || str_contains($lower, 'address') || str_contains($lower, 'where')) {
            $addr = $company->address ?? null;
            if ($addr) {
                return "We're located at: " . $addr;
            }
            return "Please contact us for our address.";
        }
        if (str_contains($lower, 'hour') || str_contains($lower, 'open') || str_contains($lower, 'when are you')) {
            $wh = $company->settings?->working_hours;
            if ($wh && is_array($wh)) {
                $lines = ['Our hours:'];
                foreach ($wh as $day => $hours) {
                    if ($hours && is_string($hours)) {
                        $lines[] = ucfirst($day) . ': ' . $hours;
                    }
                }
                return implode("\n", $lines);
            }
            return "Please contact us for our opening hours.";
        }
        if (str_contains($lower, 'delivery') || str_contains($lower, 'shipping')) {
            return "We offer delivery. For details and delivery areas, please type 'order' to place an order or ask us a specific question.";
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
        $messageWords = $this->tokenize($lower);
        foreach ($faqs as $faq) {
            $question = mb_strtolower($faq->question);
            if (str_contains($lower, $question) || str_contains($question, $lower)) {
                $faq->increment('usage_count');
                return $faq->answer;
            }
            $questionWords = $this->tokenize($question);
            if (count(array_intersect($messageWords, $questionWords)) >= min(2, count($questionWords))) {
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

    protected function tokenize(string $text): array
    {
        $words = preg_split('/\s+/', trim(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text)), -1, PREG_SPLIT_NO_EMPTY);
        return array_unique(array_map('mb_strtolower', $words ?: []));
    }

    protected function getOpenAiReply(Company $company, string $message, ?string $customerName, ?int $chatId): ?string
    {
        $platform = Cache::remember('platform_settings_openai', self::PLATFORM_SETTINGS_CACHE_TTL, fn () => PlatformSetting::first());
        $apiKey = $platform?->openai_api_key ?? config('openai.api_key');
        if (! $apiKey) {
            return null;
        }

        $model = $platform?->openai_model ?? config('openai.model', 'gpt-4o-mini');
        $maxTokens = $platform?->openai_max_tokens ?? config('openai.max_tokens', 512);

        $messages = $this->conversationBuilder->build($company, $message, $customerName, $chatId);

        try {
            $response = Http::withToken($apiKey)
                ->timeout(20)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'max_tokens' => $maxTokens,
                    'messages' => $messages,
                ]);

            if (! $response->successful()) {
                Log::warning('OpenAI API error', ['status' => $response->status(), 'body' => $response->json()]);
                return null;
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? null;
            $reply = $content ? trim($content) : null;

            if ($reply !== null && $this->shouldStoreLearningSample($company)) {
                $this->learningService->storeSample(
                    $company->id,
                    $message,
                    $reply,
                    ConversationLearningSample::SOURCE_OPENAI,
                    $chatId,
                    null
                );
            }

            return $reply;
        } catch (\Throwable $e) {
            Log::error('OpenAI request failed', ['message' => $e->getMessage()]);
            return null;
        }
    }

    protected function shouldStoreLearningSample(Company $company): bool
    {
        $settings = $company->settings;
        return $settings && ($settings->learn_from_conversations ?? true);
    }

    protected function fallbackReply(Company $company): string
    {
        $settings = $company->settings;
        $fallback = $settings?->fallback_message;
        if ($fallback && trim($fallback) !== '') {
            return trim($fallback);
        }
        return "Thanks for your message. Our team will get back to you shortly. Reply with 'prices' for our product list or 'order' to place an order.";
    }
}
