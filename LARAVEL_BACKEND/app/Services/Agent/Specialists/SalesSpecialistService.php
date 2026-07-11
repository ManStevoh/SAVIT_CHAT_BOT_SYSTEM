<?php

namespace App\Services\Agent\Specialists;

use App\Models\Chat;
use App\Models\Company;
use App\Models\Product;
use App\Services\Agent\Specialists\Contracts\CommerceSpecialist;
use App\Services\AI\AiGateway;
use Illuminate\Support\Facades\Log;

final class SalesSpecialistService implements CommerceSpecialist
{
    public function __construct(protected AiGateway $aiGateway) {}

    public function type(): string
    {
        return 'sales';
    }

    public function consultForTurn(Company $company, Chat $chat, string $incomingMessage, array $perception): array
    {
        $rule = $this->rulePerspective($perception);
        if (! config('agent.specialists.use_llm', true)) {
            return ['perspective' => $rule, 'confidence' => 0.7, 'source' => 'rules'];
        }

        $llm = $this->llmPerspective($company, $chat, $incomingMessage, $perception, $rule);

        return $llm ?? ['perspective' => $rule, 'confidence' => 0.65, 'source' => 'rules_fallback'];
    }

    public function analyzeBackground(Company $company, array $input = []): array
    {
        $companyId = (int) $company->id;
        $topProducts = Product::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->orderByDesc('stock')
            ->limit(5)
            ->get(['name', 'stock', 'price']);

        return [
            'focus' => 'conversion_and_upsell',
            'top_catalog' => $topProducts->map(fn ($p) => [
                'name' => $p->name,
                'stock' => $p->stock,
                'price' => (float) $p->price,
            ])->all(),
            'recommendation' => 'Promote in-stock items with healthy margin; bundle frequently co-purchased pairs.',
        ];
    }

    /**
     * @param  array<string, mixed>  $perception
     */
    private function rulePerspective(array $perception): string
    {
        $topic = $perception['topic'] ?? 'general';
        $risk = $perception['risk'] ?? 'low';

        return match (true) {
            $risk === 'price negotiation' => 'Sales: Compare value, suggest bundle or accessory upsell before discounting.',
            $topic === 'product inquiry' => 'Sales: Qualify needs, recommend 1–2 best-fit products, check stock with tools.',
            $topic === 'pricing' => 'Sales: Explain value; offer financing or bundle if margin allows.',
            default => 'Sales: Guide toward purchase with helpful recommendations aligned to business goals.',
        };
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
                    ['role' => 'system', 'content' => 'You are the Sales Director AI. One sentence internal advice only. Never address the customer.'],
                    ['role' => 'user', 'content' => "Message: {$message}\nPerception: ".json_encode($perception)."\nRule hint: {$ruleHint}"],
                ],
                useCase: 'agent_specialist_sales',
                company: $company,
                chatId: (int) $chat->id,
                maxTokens: 120,
                temperature: 0.3,
                timeoutSeconds: 15,
            );
            if ($result->success && $result->content) {
                return ['perspective' => 'Sales: '.trim($result->content), 'confidence' => 0.82, 'source' => 'llm'];
            }
        } catch (\Throwable $e) {
            Log::debug('Sales specialist LLM skipped', ['error' => $e->getMessage()]);
        }

        return null;
    }
}
