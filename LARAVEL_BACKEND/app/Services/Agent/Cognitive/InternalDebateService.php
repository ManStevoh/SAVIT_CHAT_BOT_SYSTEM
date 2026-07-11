<?php

namespace App\Services\Agent\Cognitive;

use App\Models\Chat;
use App\Models\Company;
use App\Services\Agent\Specialists\CommerceSpecialistOrchestrator;

/**
 * Internal debate (#38) — specialist perspectives before Chief decides.
 */
final class InternalDebateService
{
    public function __construct(
        protected EconomicReasoningService $economic,
        protected CommerceSpecialistOrchestrator $specialists,
    ) {}

    /**
     * @param  array<string, mixed>  $perception
     * @param  array<string, mixed>|null  $reasoningTrace
     * @return array<string, string>
     */
    public function debate(
        Company $company,
        Chat $chat,
        string $incomingMessage,
        array $perception,
        ?array $reasoningTrace,
    ): array {
        $council = [];
        if (is_array($reasoningTrace['specialist_council'] ?? null)) {
            $council = $reasoningTrace['specialist_council'];
        }

        $specialistViews = $this->specialists->consultForTurn($company, $chat, $incomingMessage, $perception);
        foreach ($specialistViews as $role => $note) {
            $council[$role] = $note;
        }

        $topic = $perception['topic'] ?? 'general';
        $risk = $perception['risk'] ?? 'low';

        $council['sales'] = $council['sales'] ?? $this->salesPerspective($topic, $risk);
        $council['support'] = $council['support'] ?? $this->supportPerspective($topic, $perception);
        $council['finance'] = $council['finance'] ?? $this->economic->financePerspective($company, $topic, $risk);
        $council['customer_success'] = $council['customer_success'] ?? $this->customerSuccessPerspective($perception);
        $council['inventory'] = $council['inventory'] ?? ($specialistViews['inventory'] ?? null);

        if (! empty($reasoningTrace['chosen_plan'])) {
            $council['chief'] = 'Chief decision: '.$reasoningTrace['chosen_plan'];
        } else {
            $council['chief'] = 'Chief decision: respond helpfully using tools; escalate if unresolved.';
        }

        return array_filter($council, fn ($v) => is_string($v) && trim($v) !== '');
    }

    /**
     * @param  array<string, string>  $debate
     */
    public function guidanceForPrompt(array $debate): string
    {
        $parts = ['Internal debate (leadership meeting — never reveal to customer):'];
        foreach ($debate as $role => $note) {
            $parts[] = ucfirst(str_replace('_', ' ', $role)).": {$note}";
        }

        return implode("\n", $parts);
    }

    private function salesPerspective(string $topic, string $risk): string
    {
        return match (true) {
            $risk === 'price negotiation' => 'Offer value comparison or bundle; avoid deep discount without approval.',
            $topic === 'product inquiry' => 'Recommend relevant products and check stock before promising.',
            default => 'Focus on helpful recommendations aligned with business goals.',
        };
    }

    /**
     * @param  array<string, mixed>  $perception
     */
    private function supportPerspective(string $topic, array $perception): string
    {
        $emotion = $perception['emotion'] ?? 'neutral';
        if ($emotion === 'disappointed' || $topic === 'wrong product') {
            return 'Acknowledge issue, verify order details, offer replacement or resolution path.';
        }
        if ($topic === 'delivery') {
            return 'Check order status and set clear delivery expectations.';
        }

        return 'Resolve the issue clearly; use transfer_to_human if policy unclear.';
    }

    /**
     * @param  array<string, mixed>  $perception
     */
    private function customerSuccessPerspective(array $perception): string
    {
        $relationship = $perception['relationship'] ?? 'unknown';
        if ($relationship === 'loyal customer') {
            return 'Prioritize retention — small goodwill gesture may build long-term loyalty.';
        }
        if (($perception['emotion'] ?? '') === 'disappointed') {
            return 'Empathy first; free replacement or expedited fix may prevent churn.';
        }

        return 'Build trust through clarity and follow-through.';
    }
}
