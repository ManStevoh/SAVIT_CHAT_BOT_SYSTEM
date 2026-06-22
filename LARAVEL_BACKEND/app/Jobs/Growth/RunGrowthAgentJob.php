<?php

namespace App\Jobs\Growth;

use App\Models\Company;
use App\Models\GrowthAgentRun;
use App\Services\Growth\CrmFollowUpService;
use App\Services\Growth\GrowthAnalyticsService;
use App\Services\Growth\GrowthContentService;
use App\Services\Growth\GrowthInsightService;
use App\Services\Growth\GrowthPatternService;
use App\Services\Growth\GrowthStrategyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunGrowthAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $runId) {}

    public function handle(
        GrowthContentService $contentService,
        GrowthAnalyticsService $analyticsService,
        GrowthInsightService $insightService
    ): void {
        $run = GrowthAgentRun::find($this->runId);
        if (! $run) {
            return;
        }

        $company = Company::find($run->company_id);
        if (! $company) {
            $run->update(['status' => 'failed', 'completed_at' => now()]);

            return;
        }

        $run->update(['status' => 'running', 'started_at' => now()]);

        try {
            $output = match ($run->agent_type) {
                'research' => [
                    'competitors' => $company->competitorProfiles()->where('is_active', true)->count(),
                    'platforms' => $company->socialAccounts()->where('status', 'connected')->pluck('platform'),
                ],
                'content' => $this->runContentAgent($contentService, $company, $run),
                'analytics' => [
                    'summary' => $analyticsService->executiveSummary($company->id),
                    'funnel' => $analyticsService->funnelMetrics($company->id),
                ],
                'strategy' => $this->runStrategyAgent($company, $insightService),
                'posting' => ['message' => 'Posting agent runs via PublishScheduledPostsJob'],
                'crm' => app(CrmFollowUpService::class)->processCompany($company->id),
                default => ['message' => 'Unknown agent type'],
            };

            $run->update([
                'status' => 'completed',
                'output' => $output,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'output' => ['error' => $e->getMessage()],
                'completed_at' => now(),
            ]);
        }
    }

    /**
     * @return array<int, mixed>|array{message: string, draftCount: int}
     */
    protected function runContentAgent(
        GrowthContentService $contentService,
        Company $company,
        GrowthAgentRun $run
    ): array {
        $count = (int) ($run->input['count'] ?? 0);
        if ($count <= 0) {
            return [
                'message' => 'Pipeline content step: review drafts or generate posts from the Content tab.',
                'draftCount' => $company->socialPosts()->where('status', 'draft')->count(),
            ];
        }

        $input = array_merge($run->input ?? [], ['count' => $count]);
        if ($run->input['fromWinners'] ?? false) {
            return $contentService->generateFromWinners($company, $input);
        }

        return $contentService->generatePosts($company, $input);
    }

    protected function runStrategyAgent(Company $company, GrowthInsightService $insightService): array
    {
        $strategy = app(GrowthStrategyService::class);
        $patterns = app(GrowthPatternService::class);
        $mix = $strategy->buildContentMixPlan($company);
        $brief = $strategy->generateWeeklyBrief($company);

        return [
            'insights' => $insightService->generateInsights($company),
            'contentMix' => $mix,
            'patterns' => $patterns->patternsForCompany($company->id),
            'weeklyBrief' => [
                'title' => $brief->title,
                'body' => $brief->body,
                'data' => $brief->data,
            ],
        ];
    }
}
