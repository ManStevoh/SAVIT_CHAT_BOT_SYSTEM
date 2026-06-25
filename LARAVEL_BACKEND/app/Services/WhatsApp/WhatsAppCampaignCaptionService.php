<?php

namespace App\Services\WhatsApp;

use App\Models\Company;
use App\Services\AI\OpenAiClient;

final class WhatsAppCampaignCaptionService
{
    public function __construct(
        private OpenAiClient $openAi,
    ) {}

    /**
     * @return array{success: bool, caption?: string, error?: string}
     */
    public function generate(Company $company, array $params = []): array
    {
        $topic = $params['topic'] ?? 'our latest promotion';
        $tone = $params['tone'] ?? 'friendly and professional';
        $posterHint = $params['posterHint'] ?? '';
        $includeCta = (bool) ($params['includeCta'] ?? true);

        $products = $company->products()->limit(5)->get(['name', 'price']);
        $productList = $products->map(fn ($p) => "- {$p->name}: {$p->price}")->implode("\n");

        $prompt = "Write a short WhatsApp marketing caption for {$company->name}.\n"
            ."Topic: {$topic}\n"
            ."Tone: {$tone}\n"
            .($posterHint !== '' ? "Poster context: {$posterHint}\n" : '')
            ."Products:\n{$productList}\n\n"
            .'Rules: max 280 characters, plain text only, no hashtags, no markdown. '
            .($includeCta ? 'End with a clear call-to-action to reply on WhatsApp.' : '')
            ."\nReturn JSON: {\"caption\": \"...\"}";

        $result = $this->openAi->chatCompletion(
            [
                ['role' => 'system', 'content' => 'You write concise WhatsApp marketing copy.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            OpenAiClient::USE_CASE_GROWTH,
            $company->id,
            null,
            256,
            0.7,
            30,
            true,
        );

        if (! $result->success || ! $result->content) {
            return [
                'success' => false,
                'error' => $result->error ?? 'Caption generation failed',
            ];
        }

        $parsed = json_decode($result->content, true);
        $caption = is_array($parsed) ? ($parsed['caption'] ?? null) : null;
        if (! is_string($caption) || trim($caption) === '') {
            $caption = trim($result->content, " \n\"{}");
        }

        return [
            'success' => true,
            'caption' => mb_substr(trim((string) $caption), 0, 1024),
        ];
    }
}
