<?php

namespace App\Services\Agent\Company;

use App\Models\CommerceBrief;
use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Services\Agent\Platform\ExecutiveBriefService;
use App\Services\AI\AiGateway;
use Illuminate\Support\Facades\Log;

/**
 * Daily commerce morning brief for business owners (AI Company dashboard).
 */
final class CommerceMorningBriefService
{
    public function __construct(
        protected AiGateway $aiGateway,
        protected ExecutiveBriefService $executive,
    ) {}

    public function generateForCompany(Company $company): ?CommerceBrief
    {
        $company->loadMissing('settings');
        $date = now()->toDateString();

        if (CommerceBrief::where('company_id', $company->id)->whereDate('brief_date', $date)->exists()) {
            return CommerceBrief::where('company_id', $company->id)->whereDate('brief_date', $date)->first();
        }

        $yesterday = now()->subDay();
        $salesYesterday = (float) Order::query()
            ->where('company_id', $company->id)
            ->where('payment_status', 'paid')
            ->whereDate('updated_at', $yesterday)
            ->sum('total');

        $pendingOrders = Order::query()
            ->where('company_id', $company->id)
            ->where('payment_status', 'pending')
            ->count();

        $lowStock = Product::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->where('stock', '<=', (int) config('agent.company.low_stock_threshold', 5))
            ->orderBy('stock')
            ->limit(10)
            ->pluck('name', 'stock')
            ->all();

        $metrics = [
            'sales_yesterday' => $salesYesterday,
            'pending_orders' => $pendingOrders,
            'low_stock_count' => count($lowStock),
            'low_stock_items' => $lowStock,
        ];

        $summary = $this->generateSummary($company, $metrics);
        if ($summary === null) {
            $summary = $this->fallbackSummary($company->name, $metrics);
        }

        $brief = CommerceBrief::create([
            'company_id' => $company->id,
            'brief_date' => $date,
            'summary' => $summary,
            'metrics' => $metrics,
            'recommendations' => $this->buildRecommendations($metrics),
        ]);

        return $this->executive->attachToBrief($brief, $company);
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    private function generateSummary(Company $company, array $metrics): ?string
    {
        try {
            $result = $this->aiGateway->chatCompletion(
                [
                    ['role' => 'system', 'content' => 'Write a concise morning brief for a business owner (3-5 sentences). Warm, actionable, cite numbers. No markdown.'],
                    ['role' => 'user', 'content' => json_encode([
                        'business' => $company->name,
                        'metrics' => $metrics,
                    ], JSON_UNESCAPED_UNICODE)],
                ],
                useCase: 'commerce_morning_brief',
                company: $company,
                maxTokens: 350,
                temperature: 0.5,
                jsonMode: false,
                timeoutSeconds: 25,
            );

            if ($result->success && trim((string) $result->content) !== '') {
                return trim((string) $result->content);
            }
        } catch (\Throwable $e) {
            Log::warning('Commerce morning brief LLM failed', ['company_id' => $company->id, 'error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return list<string>
     */
    private function buildRecommendations(array $metrics): array
    {
        $recs = [];
        if (($metrics['pending_orders'] ?? 0) > 0) {
            $recs[] = 'Follow up on '.$metrics['pending_orders'].' pending payment(s).';
        }
        if (($metrics['low_stock_count'] ?? 0) > 0) {
            $recs[] = 'Restock or promote alternatives for low-inventory items.';
        }
        if (($metrics['sales_yesterday'] ?? 0) <= 0) {
            $recs[] = 'Consider a WhatsApp campaign to re-engage recent customers.';
        }

        return $recs;
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    private function fallbackSummary(string $businessName, array $metrics): string
    {
        $sales = number_format((float) ($metrics['sales_yesterday'] ?? 0), 0);
        $pending = (int) ($metrics['pending_orders'] ?? 0);
        $low = (int) ($metrics['low_stock_count'] ?? 0);

        return "Good morning. {$businessName} recorded {$sales} in paid sales yesterday. "
            ."There are {$pending} orders awaiting payment"
            .($low > 0 ? " and {$low} products are low on stock." : '.')
            .' Review your dashboard for details.';
    }
}
