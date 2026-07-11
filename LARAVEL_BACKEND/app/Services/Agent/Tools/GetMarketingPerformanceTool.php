<?php

namespace App\Services\Agent\Tools;

use App\Services\Agent\AgentToolContext;
use App\Services\Agent\Brain\UnifiedCompanyBrainService;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Growth\GrowthAnalyticsService;

final class GetMarketingPerformanceTool implements AgentTool
{
    public function __construct(
        protected GrowthAnalyticsService $growthAnalytics,
        protected UnifiedCompanyBrainService $brain,
    ) {}

    public function name(): string
    {
        return 'get_marketing_performance';
    }

    public function description(): string
    {
        return 'Get marketing and growth performance: leads, ad spend, ROI, platform breakdown. Bridges Growth Engine data into commerce conversations.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'period' => [
                    'type' => 'string',
                    'enum' => ['7d', '30d', '90d'],
                    'description' => 'Reporting period',
                ],
            ],
        ];
    }

    public function execute(AgentToolContext $context, array $arguments): array
    {
        if (! config('agent.brain.enabled', true)) {
            return ['enabled' => false, 'message' => 'Growth bridge is disabled.'];
        }

        $period = (string) ($arguments['period'] ?? '30d');
        if (! in_array($period, ['7d', '30d', '90d'], true)) {
            $period = '30d';
        }

        $companyId = (int) $context->company->id;

        return [
            'period' => $period,
            'executive_summary' => $this->growthAnalytics->executiveSummary($companyId, $period),
            'platform_breakdown' => $this->growthAnalytics->platformBreakdown($companyId, $period),
            'top_posts' => $this->growthAnalytics->topPerformingPosts($companyId, $period, 5),
            'brain_summary' => $this->brain->getForPrompt($context->company),
        ];
    }
}
