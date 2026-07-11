<?php

namespace App\Services\Agent\Specialists;

use App\Models\Chat;
use App\Models\Company;
use App\Models\Product;
use App\Services\Agent\Specialists\Contracts\CommerceSpecialist;
use App\Services\AI\AiGateway;
use Illuminate\Support\Facades\Log;

final class InventorySpecialistService implements CommerceSpecialist
{
    public function __construct(protected AiGateway $aiGateway) {}

    public function type(): string
    {
        return 'inventory';
    }

    public function consultForTurn(Company $company, Chat $chat, string $incomingMessage, array $perception): array
    {
        $rule = $this->rulePerspective($company, $perception);
        if (! config('agent.specialists.use_llm', true)) {
            return ['perspective' => $rule, 'confidence' => 0.72, 'source' => 'rules'];
        }

        $llm = $this->llmPerspective($company, $chat, $incomingMessage, $perception, $rule);

        return $llm ?? ['perspective' => $rule, 'confidence' => 0.66, 'source' => 'rules_fallback'];
    }

    public function analyzeBackground(Company $company, array $input = []): array
    {
        $threshold = (int) config('agent.company.low_stock_threshold', 5);
        $lowStock = Product::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->where('stock', '<=', $threshold)
            ->orderBy('stock')
            ->limit(15)
            ->get(['name', 'stock']);

        $slowMovers = Product::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->where('stock', '>', 10)
            ->limit(10)
            ->get(['name', 'stock']);

        return [
            'low_stock_count' => $lowStock->count(),
            'low_stock_items' => $lowStock->map(fn ($p) => ['name' => $p->name, 'stock' => $p->stock])->all(),
            'slow_mover_candidates' => $slowMovers->take(5)->pluck('name')->all(),
            'recommendation' => $lowStock->count() > 0
                ? 'Restock or substitute '.$lowStock->count().' low-stock SKU(s) before promising availability.'
                : 'Stock levels healthy for active catalog.',
        ];
    }

    /**
     * @param  array<string, mixed>  $perception
     */
    private function rulePerspective(Company $company, array $perception): string
    {
        $topic = $perception['topic'] ?? 'general';
        $threshold = (int) config('agent.company.low_stock_threshold', 5);
        $lowCount = Product::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->where('stock', '<=', $threshold)
            ->count();

        if ($topic === 'product inquiry' || $topic === 'order status') {
            return "Inventory: Verify stock with search_products before committing. {$lowCount} SKU(s) at or below threshold.";
        }

        return $lowCount > 0
            ? "Inventory: {$lowCount} low-stock item(s) — avoid overpromising; suggest alternatives or restock timeline."
            : 'Inventory: Stock generally healthy — confirm specific SKU before order confirmation.';
    }

    /**
     * @param  array<string, mixed>  $perception
     * @return array{perspective: string, confidence: float, source: string}|null
     */
    private function llmPerspective(
        Company $company,
        Chat $chat,
        string $message,
        array $perception,
        string $ruleHint,
    ): ?array {
        try {
            $result = $this->aiGateway->chatCompletion(
                [
                    ['role' => 'system', 'content' => 'You are the Inventory Director AI. One sentence internal advice only.'],
                    ['role' => 'user', 'content' => "Message: {$message}\nPerception: ".json_encode($perception)."\nHint: {$ruleHint}"],
                ],
                useCase: 'agent_specialist_inventory',
                company: $company,
                chatId: (int) $chat->id,
                maxTokens: 120,
                temperature: 0.3,
                timeoutSeconds: 15,
            );
            if ($result->success && $result->content) {
                return ['perspective' => 'Inventory: '.trim($result->content), 'confidence' => 0.8, 'source' => 'llm'];
            }
        } catch (\Throwable $e) {
            Log::debug('Inventory specialist LLM skipped', ['error' => $e->getMessage()]);
        }

        return null;
    }
}
