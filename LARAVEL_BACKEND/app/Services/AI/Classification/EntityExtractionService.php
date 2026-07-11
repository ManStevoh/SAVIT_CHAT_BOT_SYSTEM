<?php

namespace App\Services\AI\Classification;

use App\Models\Company;
use App\Services\AI\AiGateway;
use App\Services\AI\AiUseCase;

/**
 * Extract structured commerce entities from free-text messages.
 */
final class EntityExtractionService
{
    public function __construct(
        protected AiGateway $gateway,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function extract(string $message, ?Company $company = null): array
    {
        $entities = $this->extractByRules($message);

        if ($this->needsLlmExtraction($entities, $message) && $company !== null) {
            $llm = $this->extractWithFastModel($message, $company);
            if ($llm !== []) {
                $entities = array_merge($entities, $llm);
                $entities['method'] = 'rules+llm';
            }
        } else {
            $entities['method'] = 'rules';
        }

        return $entities;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractByRules(string $message): array
    {
        $out = [
            'product' => null,
            'quantity' => null,
            'delivery_date' => null,
            'phone' => null,
            'location' => null,
        ];

        if (preg_match('/\b(\d{1,4})\s+(chairs?|tables?|units?|pcs?|pieces?|bags?|boxes?|items?)\b/i', $message, $m)) {
            $out['quantity'] = (int) $m[1];
            $out['product'] = trim($m[2]);
        } elseif (preg_match('/\b(\d{1,4})\b/', $message, $m)) {
            $out['quantity'] = (int) $m[1];
        }

        if (preg_match('/\bby\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday|tomorrow|today|next week)\b/i', $message, $m)) {
            $out['delivery_date'] = mb_strtolower($m[1]);
        }

        if (preg_match('/\b(?:\+?254|0)?7\d{8}\b/', $message, $m)) {
            $out['phone'] = $m[0];
        }

        if (preg_match('/\b(?:deliver to|delivery to|in)\s+([A-Za-z\s]{3,40})/i', $message, $m)) {
            $out['location'] = trim($m[1]);
        }

        if ($out['product'] === null && preg_match('/\bneed\s+(.{2,40}?)(?:\s+by|\s*$)/i', $message, $m)) {
            $out['product'] = trim(rtrim($m[1], '.,!'));
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $entities
     */
    private function needsLlmExtraction(array $entities, string $message): bool
    {
        if (mb_strlen(trim($message)) < 20) {
            return false;
        }

        return ($entities['product'] === null && $entities['quantity'] === null)
            || (bool) preg_match('/\b(order|quote|invoice|bulk)\b/i', $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractWithFastModel(string $message, Company $company): array
    {
        $system = <<<'TEXT'
Extract commerce entities from the customer message. Return JSON only:
{"product":null,"quantity":null,"delivery_date":null,"phone":null,"location":null}
Use null for unknown fields.
TEXT;

        $result = $this->gateway->chatCompletion(
            [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => mb_substr($message, 0, 1200)],
            ],
            useCase: AiUseCase::ENTITY,
            company: $company,
            maxTokens: 120,
            temperature: 0.0,
            jsonMode: true,
            timeoutSeconds: 12,
        );

        if (! $result->success || ! $result->content) {
            return [];
        }

        $parsed = json_decode($result->content, true);

        return is_array($parsed) ? $parsed : [];
    }
}
