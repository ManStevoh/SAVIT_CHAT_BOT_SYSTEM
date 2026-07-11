<?php

namespace App\Services\Agent\Vision;

use App\Models\Message;
use App\Models\MessageVisionAnalysis;
use App\Models\Product;
use App\Services\Agent\AgentChatService;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp image → product/warranty recognition via vision-capable LLM.
 */
final class VisionPipelineService
{
    public function __construct(
        protected AgentChatService $agentChat,
    ) {}

    public function analyzeMessage(Message $message): ?MessageVisionAnalysis
    {
        if (! config('agent.vision.enabled', true)) {
            return null;
        }

        if ($message->message_type !== 'image' || empty($message->attachment_url)) {
            return null;
        }

        $existing = MessageVisionAnalysis::where('message_id', $message->id)->first();
        if ($existing) {
            return $existing;
        }

        $chat = $message->chat;
        if (! $chat) {
            return null;
        }

        $company = $chat->company;
        if (! $company) {
            return null;
        }

        $imageUrl = $this->absoluteUrl((string) $message->attachment_url);
        if ($imageUrl === null) {
            return null;
        }

        $instruction = <<<'PROMPT'
Analyze this customer WhatsApp image for a commerce business. Return JSON only:
{
  "scene_summary": "brief description",
  "detected_products": ["product names or labels visible"],
  "warranty_card_detected": false,
  "warranty_details": "serial, expiry, brand if visible",
  "receipt_detected": false,
  "damage_visible": false,
  "confidence": 0.0
}
Identify products, warranty cards, receipts, or damage. Be factual; use empty arrays/false when unsure.
PROMPT;

        $result = $this->agentChat->completeWithVision(
            company: $company,
            imageUrl: $imageUrl,
            instruction: $instruction,
            chatId: (int) $chat->id,
            jsonMode: true,
        );

        if (! $result->success || empty($result->content)) {
            Log::info('Vision pipeline: analysis failed', [
                'message_id' => $message->id,
                'error' => $result->error,
            ]);

            return null;
        }

        $parsed = json_decode($result->content, true);
        if (! is_array($parsed)) {
            return null;
        }

        $detected = array_values(array_filter(
            array_map('strval', $parsed['detected_products'] ?? []),
            fn ($v) => trim($v) !== '',
        ));

        $productMatches = $this->matchCatalogProducts((int) $company->id, $detected);
        $analysisType = 'general';
        if (! empty($parsed['warranty_card_detected']) || ! empty($parsed['receipt_detected'])) {
            $analysisType = 'warranty';
        } elseif ($productMatches !== []) {
            $analysisType = 'product';
        }

        return MessageVisionAnalysis::create([
            'company_id' => $company->id,
            'chat_id' => $chat->id,
            'message_id' => $message->id,
            'analysis_type' => $analysisType,
            'labels' => $detected,
            'product_matches' => $productMatches,
            'warranty_detected' => (bool) ($parsed['warranty_card_detected'] ?? $parsed['receipt_detected'] ?? false),
            'confidence' => min(1.0, max(0.0, (float) ($parsed['confidence'] ?? 0.5))),
            'raw_response' => $parsed,
            'model_used' => $result->model,
        ]);
    }

    public function getForMessage(int $messageId): ?MessageVisionAnalysis
    {
        return MessageVisionAnalysis::where('message_id', $messageId)->first();
    }

    /**
     * @param  list<string>  $labels
     * @return list<array{product_id: int, name: string, label: string}>
     */
    private function matchCatalogProducts(int $companyId, array $labels): array
    {
        if ($labels === []) {
            return [];
        }

        $products = Product::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->limit(200)
            ->get(['id', 'name']);

        $matches = [];
        foreach ($labels as $label) {
            $labelLower = mb_strtolower(trim($label));
            if ($labelLower === '') {
                continue;
            }
            foreach ($products as $product) {
                $nameLower = mb_strtolower((string) $product->name);
                if (str_contains($nameLower, $labelLower) || str_contains($labelLower, $nameLower)) {
                    $matches[] = [
                        'product_id' => (int) $product->id,
                        'name' => (string) $product->name,
                        'label' => $label,
                    ];
                }
            }
        }

        return array_values(array_unique($matches, SORT_REGULAR));
    }

    private function absoluteUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return url($url);
    }
}
