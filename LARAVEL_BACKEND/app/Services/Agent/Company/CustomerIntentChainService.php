<?php

namespace App\Services\Agent\Company;

use App\Models\Company;
use App\Models\CustomerIntentChain;
use App\Models\Order;
use App\Models\Product;

/**
 * Tracks multi-step customer journeys across conversations (intent chains).
 */
final class CustomerIntentChainService
{
    /**
     * @return array<string, mixed>|null
     */
    public function getChain(int $companyId, string $phone): ?array
    {
        $row = CustomerIntentChain::query()
            ->where('company_id', $companyId)
            ->where('customer_phone', $this->normalizePhone($phone))
            ->first();

        return $row ? [
            'primary_intent' => $row->primary_intent,
            'stage' => $row->stage,
            'journey' => $row->journey ?? [],
        ] : null;
    }

    public function getForPrompt(Company $company, string $phone): string
    {
        $chain = $this->getChain((int) $company->id, $phone);
        if ($chain === null) {
            return '';
        }

        $steps = is_array($chain['journey']) ? array_slice($chain['journey'], -5) : [];

        return "Customer journey (intent chain):\n"
            ."- Primary intent: {$chain['primary_intent']}\n"
            ."- Stage: {$chain['stage']}\n"
            .(count($steps) > 0 ? '- Recent steps: '.implode(' → ', $steps) : '');
    }

    /**
     * @param  array<string, mixed>  $trace
     */
    public function advanceFromReasoning(Company $company, string $phone, array $trace): void
    {
        $phone = $this->normalizePhone($phone);
        $understanding = mb_strtolower((string) ($trace['understanding'] ?? ''));
        $actionKind = mb_strtolower(trim((string) ($trace['action_kind'] ?? '')));
        $plan = mb_strtolower((string) ($trace['chosen_plan'] ?? ''));
        $blob = trim($understanding.' '.$plan.' '.$actionKind);

        $intent = match (true) {
            in_array($actionKind, ['create_order', 'pay'], true) => 'purchase',
            in_array($actionKind, ['refund', 'handoff', 'track', 'send_document'], true) => 'support',
            $actionKind === 'lookup' && (str_contains($blob, 'price') || str_contains($blob, 'compare')) => 'evaluate',
            str_contains($blob, 'order') || str_contains($blob, 'buy') || str_contains($blob, 'purchase') || str_contains($blob, 'checkout') => 'purchase',
            str_contains($blob, 'support') || str_contains($blob, 'broken') || str_contains($blob, 'refund') || str_contains($blob, 'invoice') || str_contains($blob, 'track') => 'support',
            str_contains($blob, 'compare') || str_contains($blob, 'price') => 'evaluate',
            default => 'exploring',
        };

        $stage = match (true) {
            $actionKind === 'pay' => 'payment',
            $actionKind === 'create_order' => 'checkout',
            $actionKind === 'track' || $actionKind === 'send_document' => 'post_purchase',
            $actionKind === 'refund' || $actionKind === 'handoff' => 'resolve',
            $intent === 'purchase' => 'checkout',
            $intent === 'support' => 'resolve',
            $intent === 'evaluate' => 'compare',
            default => 'discover',
        };

        $row = CustomerIntentChain::firstOrNew([
            'company_id' => $company->id,
            'customer_phone' => $phone,
        ]);

        $journey = is_array($row->journey) ? $row->journey : [];
        $journey[] = $stage.'@'.now()->toDateString();
        if (count($journey) > 20) {
            $journey = array_slice($journey, -20);
        }

        $row->primary_intent = $intent;
        $row->stage = $stage;
        $row->journey = $journey;
        $row->last_active_at = now();
        $row->save();
    }

    /**
     * Predictive reorder signal from order history.
     *
     * @return array{due: bool, days_since_last: ?int, usual_cycle_days: ?int, last_order_number: ?string}|null
     */
    public function reorderSignal(int $companyId, string $phone): ?array
    {
        $orders = Order::query()
            ->where('company_id', $companyId)
            ->where('customer_phone', $this->normalizePhone($phone))
            ->where('payment_status', 'paid')
            ->orderByDesc('created_at')
            ->limit(6)
            ->get(['id', 'order_number', 'created_at']);

        if ($orders->count() < 2) {
            return null;
        }

        $gaps = [];
        for ($i = 0; $i < $orders->count() - 1; $i++) {
            $gaps[] = $orders[$i]->created_at->diffInDays($orders[$i + 1]->created_at);
        }
        $cycle = (int) round(array_sum($gaps) / count($gaps));
        if ($cycle < 7) {
            return null;
        }

        $daysSince = $orders->first()->created_at->diffInDays(now());
        $threshold = (int) round($cycle * config('agent.company.reorder_threshold_ratio', 0.85));

        return [
            'due' => $daysSince >= $threshold,
            'days_since_last' => $daysSince,
            'usual_cycle_days' => $cycle,
            'last_order_number' => $orders->first()->order_number,
        ];
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? $phone;
    }
}
