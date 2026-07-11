<?php

namespace App\Services\AI\Classification;

use App\Models\Company;
use App\Services\AI\AiGateway;
use App\Services\AI\AiUseCase;
use App\Services\Conversation\CustomerMessageClassifier;

/**
 * Lightweight intent routing — rules first, optional fast LLM for ambiguous cases.
 */
final class IntentClassificationService
{
    public function __construct(
        protected CustomerMessageClassifier $shortMessage,
        protected AiGateway $gateway,
    ) {}

    public function isTrivialMessage(string $message): bool
    {
        $trimmed = trim($message);
        if ($trimmed === '') {
            return true;
        }

        if ($this->shortMessage->buildOpenAiHint($message) !== null) {
            return true;
        }

        $lower = mb_strtolower($trimmed);
        if (mb_strlen($lower) <= 12 && preg_match('/^(hi|hello|hey|ok|thanks|thank you|yes|no|bye|good morning|good evening)[\s!.?]*$/u', $lower)) {
            return true;
        }

        return false;
    }

    /**
     * @return array{intent: string, confidence: float, method: string}
     */
    public function classify(string $message, ?Company $company = null): array
    {
        $rule = $this->classifyByRules($message);
        if ($rule['confidence'] >= 0.75) {
            return $rule;
        }

        if ($company === null || ! config('ai.intent_llm_fallback', true)) {
            return $rule;
        }

        return $this->classifyWithFastModel($message, $company, $rule);
    }

    /**
     * @return array{intent: string, confidence: float, method: string}
     */
    private function classifyByRules(string $message): array
    {
        $lower = mb_strtolower(trim($message));

        if ($this->isTrivialMessage($message)) {
            return ['intent' => 'greeting_or_ack', 'confidence' => 0.95, 'method' => 'rules'];
        }

        $patterns = [
            'buy' => '/\b(buy|order|purchase|need|want|get me|stock|price|how much|catalog|menu)\b/u',
            'return' => '/\b(return|refund|exchange|money back|replace)\b/u',
            'complaint' => '/\b(complain|angry|disappointed|terrible|worst|unacceptable|not happy)\b/u',
            'support' => '/\b(help|support|issue|problem|broken|not working|assist)\b/u',
            'warranty' => '/\b(warranty|guarantee|repair)\b/u',
            'appointment' => '/\b(appointment|book|schedule|visit|meeting)\b/u',
            'payment' => '/\b(pay|payment|mpesa|invoice|receipt|paid|stk)\b/u',
            'delivery' => '/\b(deliver|delivery|ship|shipping|track|where is my|arrive|dispatch)\b/u',
        ];

        $scores = [];
        foreach ($patterns as $intent => $regex) {
            if (preg_match($regex, $lower)) {
                $scores[$intent] = ($scores[$intent] ?? 0) + 1;
            }
        }

        if ($scores === []) {
            return ['intent' => 'general_inquiry', 'confidence' => 0.45, 'method' => 'rules'];
        }

        arsort($scores);
        $intent = array_key_first($scores);

        return ['intent' => $intent, 'confidence' => min(0.9, 0.55 + ($scores[$intent] * 0.15)), 'method' => 'rules'];
    }

    /**
     * @param  array{intent: string, confidence: float, method: string}  $fallback
     * @return array{intent: string, confidence: float, method: string}
     */
    private function classifyWithFastModel(string $message, Company $company, array $fallback): array
    {
        $system = <<<'TEXT'
Classify the customer message intent. Return JSON only:
{"intent":"buy|return|complaint|support|warranty|appointment|payment|delivery|greeting_or_ack|general_inquiry","confidence":0.0}
TEXT;

        $result = $this->gateway->chatCompletion(
            [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => mb_substr($message, 0, 800)],
            ],
            useCase: AiUseCase::INTENT,
            company: $company,
            maxTokens: 80,
            temperature: 0.0,
            jsonMode: true,
            timeoutSeconds: 12,
        );

        if (! $result->success || ! $result->content) {
            return $fallback;
        }

        $parsed = json_decode($result->content, true);
        if (! is_array($parsed) || empty($parsed['intent'])) {
            return $fallback;
        }

        return [
            'intent' => (string) $parsed['intent'],
            'confidence' => min(1.0, max(0.0, (float) ($parsed['confidence'] ?? 0.7))),
            'method' => 'fast_llm',
        ];
    }
}
