<?php

namespace App\Services\Agent\Cognitive;

use App\Models\Company;
use App\Models\Order;
use App\Services\Agent\Company\MessageSentimentService;

/**
 * Perception layer (#37) — structured signal extraction before reasoning.
 */
final class PerceptionService
{
    public function __construct(
        protected MessageSentimentService $sentiment,
    ) {}

    /**
     * @return array{
     *   emotion: string,
     *   topic: string,
     *   urgency: string,
     *   risk: string,
     *   relationship: string,
     *   sentiment: array<string, mixed>,
     *   raw_cues: list<string>
     * }
     */
    public function perceive(Company $company, string $message, ?string $customerPhone): array
    {
        $sentiment = $this->sentiment->detect($message);
        $lower = mb_strtolower(trim($message));

        $emotion = match ($sentiment['label']) {
            'frustrated' => 'disappointed',
            'concerned' => 'concerned',
            'positive' => 'positive',
            default => $this->detectEmotionFromCues($lower),
        };

        return [
            'emotion' => $emotion,
            'topic' => $this->detectTopic($lower),
            'urgency' => $this->detectUrgency($lower, $sentiment),
            'risk' => $this->detectRisk($lower, $emotion),
            'relationship' => $this->detectRelationship($company, $customerPhone),
            'sentiment' => $sentiment,
            'raw_cues' => $sentiment['cues'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $perception
     */
    public function guidanceForPrompt(array $perception): string
    {
        $parts = ['Perception (internal — do not expose raw JSON):'];
        $parts[] = 'Emotion: '.($perception['emotion'] ?? 'neutral');
        $parts[] = 'Topic: '.($perception['topic'] ?? 'general');
        $parts[] = 'Urgency: '.($perception['urgency'] ?? 'low');
        $parts[] = 'Risk: '.($perception['risk'] ?? 'none');
        $parts[] = 'Relationship: '.($perception['relationship'] ?? 'unknown');

        return implode("\n", $parts);
    }

    private function detectEmotionFromCues(string $lower): string
    {
        if (str_contains($lower, '😔') || str_contains($lower, '😞') || str_contains($lower, '😢')) {
            return 'disappointed';
        }
        if (str_contains($lower, 'wanted the') && (str_contains($lower, 'wrong') || str_contains($lower, 'not'))) {
            return 'disappointed';
        }

        return 'neutral';
    }

    private function detectTopic(string $lower): string
    {
        $topics = [
            'wrong product' => ['wrong', 'black one', 'white one', 'not what', 'different color', 'different size', 'sent wrong'],
            'refund' => ['refund', 'money back', 'return', 'cancel order'],
            'delivery' => ['delivery', 'deliver', 'shipping', 'where is my', 'not arrived', 'late'],
            'pricing' => ['price', 'discount', 'cheaper', 'competitor', 'cost', 'expensive'],
            'order status' => ['order status', 'my order', 'track', 'order number'],
            'product inquiry' => ['do you have', 'available', 'in stock', 'how much'],
            'payment' => ['payment', 'mpesa', 'paid', 'invoice', 'pay'],
        ];

        foreach ($topics as $topic => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($lower, $kw)) {
                    return $topic;
                }
            }
        }

        return 'general';
    }

    /**
     * @param  array<string, mixed>  $sentiment
     */
    private function detectUrgency(string $lower, array $sentiment): string
    {
        if (($sentiment['label'] ?? '') === 'frustrated') {
            return 'high';
        }

        $high = ['urgent', 'asap', 'immediately', 'today', 'now', 'deadline'];
        $medium = ['soon', 'quickly', 'waiting', 'when will'];

        foreach ($high as $w) {
            if (str_contains($lower, $w)) {
                return 'high';
            }
        }
        foreach ($medium as $w) {
            if (str_contains($lower, $w)) {
                return 'medium';
            }
        }

        return 'low';
    }

    private function detectRisk(string $lower, string $emotion = 'neutral'): string
    {
        if (str_contains($lower, 'refund') || str_contains($lower, 'return') || str_contains($lower, 'wrong')) {
            return 'possible return';
        }
        if (str_contains($lower, 'black one') || str_contains($lower, 'white one')
            || (str_contains($lower, 'wanted the') && $emotion === 'disappointed')) {
            return 'possible return';
        }
        if (str_contains($lower, 'competitor') || str_contains($lower, 'cheaper')) {
            return 'price negotiation';
        }
        if (str_contains($lower, 'fraud') || str_contains($lower, 'scam')) {
            return 'trust risk';
        }

        return 'low';
    }

    private function detectRelationship(Company $company, ?string $customerPhone): string
    {
        if (! $customerPhone) {
            return 'unknown';
        }

        $paidOrders = Order::query()
            ->where('company_id', $company->id)
            ->where('customer_phone', $customerPhone)
            ->where('payment_status', 'paid')
            ->count();

        if ($paidOrders >= 3) {
            return 'loyal customer';
        }
        if ($paidOrders >= 1) {
            return 'existing customer';
        }

        return 'new customer';
    }
}
