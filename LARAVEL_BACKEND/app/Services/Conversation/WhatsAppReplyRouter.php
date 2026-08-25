<?php

namespace App\Services\Conversation;

use App\Models\Company;
use Illuminate\Support\Facades\Log;

/**
 * Routes WhatsApp messages: AI first, then FAQ / catalog / keyword as fallbacks only.
 */
final class WhatsAppReplyRouter
{
    public function __construct(
        protected AiReplyModeResolver $modeResolver,
        protected CatalogShortcutMatcher $catalogMatcher,
        protected FaqMatchingService $faqMatchingService,
        protected KeywordReplyMatcher $keywordMatcher,
        protected WhatsAppAiReplyGenerator $aiGenerator,
        protected ConversationLearningRecorder $learningRecorder,
    ) {}

    public function fallbackMessage(Company $company): string
    {
        return $this->fallbackReply($company);
    }

    /**
     * @return array{route: string, text: string, meta?: array<string, mixed>}
     */
    public function resolve(
        Company $company,
        string $message,
        string $lower,
        ?string $customerName,
        ?int $chatId,
        ?string $orderFlowContext = null,
    ): array {
        // Always prefer AI. Keyword/catalog/FAQ remain fallbacks when AI is unavailable.
        $openAiReply = $this->aiGenerator->generate($company, $message, $customerName, $chatId, $orderFlowContext);
        if ($openAiReply !== null) {
            return ['route' => 'openai', 'text' => $openAiReply];
        }

        $faqMatch = $this->faqMatchingService->matchBest($company, $message, $lower);
        if ($faqMatch !== null) {
            $this->learningRecorder->recordFaqExchange($company, $message, $faqMatch['answer'], $chatId);

            return [
                'route' => $faqMatch['route'] ?? 'faq',
                'text' => $faqMatch['answer'],
                'meta' => [
                    'faq_id' => $faqMatch['faq_id'],
                    'score' => $faqMatch['score'],
                    'fallback' => true,
                ],
            ];
        }

        $catalogReply = $this->modeResolver->isAiFirst($company)
            ? $this->catalogMatcher->matchAiFirst($company, $lower, $message)
            : $this->catalogMatcher->matchBalanced($company, $lower);
        if ($catalogReply !== null) {
            return ['route' => 'catalog_quick', 'text' => $catalogReply, 'meta' => ['fallback' => true]];
        }

        $keywordReply = $this->keywordMatcher->match($company, $lower);
        if ($keywordReply !== null) {
            return ['route' => 'keyword', 'text' => $keywordReply, 'meta' => ['fallback' => true]];
        }

        Log::warning('WhatsAppReplyRouter: all reply paths failed, sending fallback', [
            'company_id' => $company->id,
            'message_preview' => mb_substr($message, 0, 100),
        ]);

        return ['route' => 'fallback', 'text' => $this->fallbackReply($company)];
    }

    private function fallbackReply(Company $company): string
    {
        $settings = $company->settings;
        $fallback = $settings?->fallback_message;
        if ($fallback && trim($fallback) !== '') {
            return trim($fallback);
        }

        if ($this->modeResolver->isAiFirst($company)) {
            return 'Thanks for your message — our assistant is briefly unavailable. A team member will follow up soon, or you can reply "prices" or "order" for quick options.';
        }

        return "Thanks for your message — we're on it.\n\n"
            ."Quick options: reply \"prices\" for our product list, \"order\" to place an order, or type your question (hours, delivery, etc.) and we'll answer from our business info.\n\n"
            .ConversationGreetingService::QUICK_MENU_SUFFIX;
    }
}
