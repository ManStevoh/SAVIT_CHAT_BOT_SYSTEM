<?php

namespace App\Services;

use App\Models\Company;
use App\Models\ConversationLearningSample;
use App\Models\PlatformSetting;
use App\Services\AI\OpenAIConversationBuilder;
use App\Services\Conversation\ConversationRoutingLogger;
use App\Services\Conversation\FaqMatchingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIReplyService
{
    private const PLATFORM_SETTINGS_CACHE_TTL = 300;

    /** Numbered quick menu (greetings, handoff, etc.). */
    public const QUICK_MENU_SUFFIX = "\n\nReply with: 1. Prices  2. Order  3. Talk to agent";

    public function __construct(
        protected WhatsAppMessageSenderService $whatsAppSender,
        protected OpenAIConversationBuilder $conversationBuilder,
        protected ConversationLearningService $learningService,
        protected OrderFlowService $orderFlow,
        protected FaqMatchingService $faqMatchingService,
        protected ConversationRoutingLogger $routingLogger
    ) {}

    /**
     * Get reply text for an incoming customer message.
     *
     * @param  int|null  $chatId  For conversation history (OpenAI) and context
     */
    public function getReplyForMessage(Company $company, string $customerMessage, ?string $customerName = null, ?int $chatId = null, ?string $orderFlowContext = null): string
    {
        $message = trim($customerMessage);
        if ($message === '') {
            $this->routingLogger->log($company->id, $chatId, 'empty_message');

            return $this->fallbackReply($company);
        }

        $lower = mb_strtolower($message);

        if ($this->isOutsideWorkingHours($company)) {
            $this->routingLogger->log($company->id, $chatId, 'away_hours');
            $away = $company->settings?->away_message;
            if ($away) {
                return $away;
            }

            return "Thanks for your message. We're currently outside business hours. We'll get back to you as soon as we can.";
        }

        if ($this->looksLikeGreeting($lower)) {
            $this->routingLogger->log($company->id, $chatId, 'greeting_menu');

            return $this->greetingWithQuickMenu($company, $customerName);
        }

        return $this->resolveReplyContent($company, $message, $lower, $customerName, $chatId, $orderFlowContext);
    }

    /**
     * Second bubble after opening greeting: FAQ / keywords / AI / fallback.
     * Returns null when a second message would duplicate the opening (away hours, pure greeting only).
     */
    public function getReplyAfterOpeningGreeting(Company $company, string $customerMessage, ?string $customerName = null, ?int $chatId = null, ?string $orderFlowContext = null): ?string
    {
        $message = trim($customerMessage);
        if ($message === '') {
            return null;
        }

        $lower = mb_strtolower($message);

        if ($this->isOutsideWorkingHours($company)) {
            return null;
        }

        if ($this->looksLikeGreeting($lower)) {
            return null;
        }

        return $this->resolveReplyContent($company, $message, $lower, $customerName, $chatId, $orderFlowContext);
    }

    /**
     * Catalog shortcuts → scored FAQ → keyword heuristics → OpenAI → fallback.
     */
    protected function resolveReplyContent(Company $company, string $message, string $lower, ?string $customerName, ?int $chatId, ?string $orderFlowContext = null): string
    {
        $catalogReply = $this->matchCatalogQuickReply($company, $lower);
        if ($catalogReply !== null) {
            $this->routingLogger->log($company->id, $chatId, 'catalog_quick');

            return $catalogReply;
        }

        $faqMatch = $this->faqMatchingService->matchBest($company, $message, $lower);
        if ($faqMatch !== null) {
            $this->routingLogger->log($company->id, $chatId, 'faq', [
                'faq_id' => $faqMatch['faq_id'],
                'score' => $faqMatch['score'],
            ]);

            return $faqMatch['answer'];
        }

        $keywordReply = $this->matchKeywordReply($company, $lower);
        if ($keywordReply !== null) {
            $this->routingLogger->log($company->id, $chatId, 'keyword');

            return $keywordReply;
        }

        $openAiReply = $this->getOpenAiReply($company, $message, $customerName, $chatId, $orderFlowContext);
        if ($openAiReply !== null) {
            $this->routingLogger->log($company->id, $chatId, 'openai');

            return $openAiReply;
        }

        $this->routingLogger->log($company->id, $chatId, 'fallback');

        return $this->fallbackReply($company);
    }

    /**
     * High-confidence shortcuts that must stay data-accurate (prices / product list).
     */
    protected function matchCatalogQuickReply(Company $company, string $lower): ?string
    {
        if ($lower === '1' || str_contains($lower, 'price') || str_contains($lower, 'prices') || str_contains($lower, 'how much')) {
            return $this->formatProductList($company);
        }
        if (str_contains($lower, 'catalog') || str_contains($lower, 'menu') || str_contains($lower, 'products') || str_contains($lower, 'product list') || ($lower === 'list' || str_contains($lower, 'price list'))) {
            return $this->formatProductList($company);
        }

        return null;
    }

    /**
     * Opening reply for a new conversation: configured greeting + quick menu, or away message when outside hours.
     */
    public function getGreetingOpening(Company $company, ?string $customerName = null): string
    {
        if ($this->isOutsideWorkingHours($company)) {
            $away = $company->settings?->away_message;
            if ($away) {
                return $away;
            }

            return "Thanks for your message. We're currently outside business hours. We'll get back to you as soon as we can.";
        }

        return $this->greetingWithQuickMenu($company, $customerName);
    }

    protected function greetingWithQuickMenu(Company $company, ?string $customerName = null): string
    {
        $settings = $company->settings;
        $greeting = $settings?->ai_greeting;
        if ($greeting) {
            return $this->appendQuickMenu($greeting);
        }
        $default = 'Hello'.($customerName ? " {$customerName}" : '').'! Thanks for reaching out. How can we help you today?';

        return $this->appendQuickMenu($default);
    }

    protected function appendQuickMenu(string $text): string
    {
        $menu = self::QUICK_MENU_SUFFIX;
        if (str_contains($text, $menu)) {
            return $text;
        }

        return rtrim($text).$menu;
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
            if ($lower === $g || str_starts_with($lower, $g.' ') || str_starts_with($lower, $g.',')) {
                return true;
            }
        }

        return false;
    }

    protected function matchKeywordReply(Company $company, string $lower): ?string
    {
        if (str_contains($lower, 'order') || str_contains($lower, 'place order')) {
            return 'You can place an order by typing "order" or "2", then reply with product numbers from the list and quantity when asked. You can also use text like "2 x Product Name".';
        }

        if ($this->looksLikeOrderStatusQuestion($lower)) {
            return null;
        }

        if (str_contains($lower, 'location')
            || str_contains($lower, 'address')
            || $this->looksLikeShopLocationQuestion($lower)) {
            $addr = $company->address ?? null;
            if ($addr) {
                return "We're located at: {$addr}";
            }

            return 'Please contact us for our address.';
        }
        if (str_contains($lower, 'hour') || str_contains($lower, 'open') || str_contains($lower, 'when are you')) {
            $wh = $company->settings?->working_hours;
            if ($wh && is_array($wh)) {
                $lines = ['Our hours:'];
                foreach ($wh as $day => $hours) {
                    if ($hours && is_string($hours)) {
                        $lines[] = ucfirst($day).': '.$hours;
                    }
                }

                return implode("\n", $lines);
            }

            return 'Please contact us for our opening hours.';
        }
        if (str_contains($lower, 'delivery') || str_contains($lower, 'shipping')) {
            return "We offer delivery. For details and delivery areas, type \"order\" to start an order or ask a specific question and we'll answer from our business info.";
        }

        return null;
    }

    protected function looksLikeOrderStatusQuestion(string $lower): bool
    {
        if (! str_contains($lower, 'order')) {
            return false;
        }

        return str_contains($lower, 'where is')
            || str_contains($lower, 'where\'s')
            || str_contains($lower, 'wheres')
            || str_contains($lower, 'status')
            || str_contains($lower, 'track')
            || str_contains($lower, 'tracking')
            || str_contains($lower, 'my order')
            || str_contains($lower, 'order number');
    }

    /** @see OrderFlowService::looksLikeLocationOrShopInfoQuestion() */
    protected function looksLikeShopLocationQuestion(string $lower): bool
    {
        if (str_contains($lower, 'where') && (str_contains($lower, 'shop') || str_contains($lower, 'store') || str_contains($lower, 'located') || str_contains($lower, 'find you') || str_contains($lower, 'your address'))) {
            return true;
        }
        if (str_contains($lower, 'where are you') || str_contains($lower, 'where is the')) {
            return true;
        }

        return false;
    }

    protected function formatProductList(Company $company): string
    {
        return $this->orderFlow->formatCatalogForDisplay($company);
    }

    protected function getOpenAiReply(Company $company, string $message, ?string $customerName, ?int $chatId, ?string $orderFlowContext = null): ?string
    {
        $platform = Cache::remember('platform_settings_openai', self::PLATFORM_SETTINGS_CACHE_TTL, fn () => PlatformSetting::first());
        $apiKey = $platform?->openai_api_key ?? config('openai.api_key');
        if (! $apiKey) {
            return null;
        }

        $model = $platform?->openai_model ?? config('openai.model', 'gpt-4o-mini');
        $maxTokens = $platform?->openai_max_tokens ?? config('openai.max_tokens', 512);

        $messages = $this->conversationBuilder->build($company, $message, $customerName, $chatId, $orderFlowContext);

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
            return trim($fallback)."\n\n".'Quick options: reply "prices" for our list, "order" to buy, or ask any question and we will answer from our business details.';
        }

        return "Thanks for your message — we're on it.\n\n"
            ."Quick options: reply \"prices\" for our product list, \"order\" to place an order, or type your question (hours, delivery, etc.) and we'll answer from our business info.\n\n"
            .self::QUICK_MENU_SUFFIX;
    }
}
