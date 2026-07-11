<?php

namespace App\Services\Agent\MissionControl;

use App\Models\AgentActionRequest;
use App\Models\AgentTrustLog;
use App\Models\BusinessHealthScore;
use App\Models\BusinessOpportunity;
use App\Models\BusinessGraphEdge;
use App\Models\BusinessGraphNode;
use App\Models\CommerceAgentEvent;
use App\Models\Company;
use App\Services\Agent\Brain\UnifiedCompanyBrainService;
use App\Services\Agent\Graph\BusinessGraphV2Service;
use App\Services\Agent\Platform\ExecutiveBriefService;
use App\Services\Agent\Timeline\BusinessTimelineService;

/**
 * Mission Control — single attention queue for what the owner must see.
 */
final class MissionControlService
{
    public function __construct(
        protected UnifiedCompanyBrainService $brain,
        protected BusinessTimelineService $timeline,
        protected BusinessGraphV2Service $graph,
        protected ExecutiveBriefService $executive,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Company $company): array
    {
        $company->loadMissing('settings');
        $snapshot = $this->brain->refreshIfStale($company, 30);

        $health = BusinessHealthScore::where('company_id', $company->id)
            ->orderByDesc('score_date')
            ->first();

        $openEvents = CommerceAgentEvent::where('company_id', $company->id)
            ->whereIn('status', ['open', 'alerted'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $opportunities = BusinessOpportunity::where('company_id', $company->id)
            ->where('status', 'open')
            ->orderByDesc('detected_at')
            ->limit(10)
            ->get();

        $approvals = AgentActionRequest::where('company_id', $company->id)
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $attention = $this->buildAttentionQueue($openEvents, $opportunities, $approvals, $health);

        $graphStats = [
            'nodes' => BusinessGraphNode::where('company_id', $company->id)->count(),
            'edges' => BusinessGraphEdge::where('company_id', $company->id)->count(),
        ];

        return [
            'generatedAt' => now()->toIso8601String(),
            'brainSummary' => $snapshot?->summary_text,
            'brainDigest' => $snapshot?->digest,
            'healthScore' => $health ? [
                'overall' => $health->overall_score,
                'summary' => $health->summary,
                'date' => $health->score_date->toDateString(),
            ] : null,
            'topDecisions' => $this->executive->topDecisionsForCompany($company),
            'attentionQueue' => $attention,
            'counts' => [
                'openEvents' => CommerceAgentEvent::where('company_id', $company->id)->where('status', 'open')->count(),
                'pendingApprovals' => $approvals->count(),
                'openOpportunities' => BusinessOpportunity::where('company_id', $company->id)->where('status', 'open')->count(),
            ],
            'recentTimeline' => $this->timeline->timeline($company, 15),
            'graphStats' => $graphStats,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CommerceAgentEvent>  $events
     * @param  \Illuminate\Support\Collection<int, BusinessOpportunity>  $opportunities
     * @param  \Illuminate\Support\Collection<int, AgentActionRequest>  $approvals
     * @return list<array<string, mixed>>
     */
    private function buildAttentionQueue($events, $opportunities, $approvals, ?BusinessHealthScore $health): array
    {
        $items = [];

        foreach ($approvals as $req) {
            $items[] = [
                'priority' => 95,
                'type' => 'approval',
                'title' => 'Approval required: '.$req->action_type,
                'summary' => $req->reasoning,
                'id' => $req->id,
                'href' => '/dashboard/executive',
            ];
        }

        foreach ($events as $event) {
            $priority = in_array($event->event_type, ['sales_drop', 'low_stock'], true) ? 90 : 70;
            $items[] = [
                'priority' => $priority,
                'type' => 'commerce_event',
                'title' => ucfirst(str_replace('_', ' ', $event->event_type)),
                'summary' => (string) ($event->payload['summary'] ?? $event->event_key),
                'id' => $event->id,
                'href' => '/dashboard/agent-ops',
            ];
        }

        foreach ($opportunities as $opp) {
            $items[] = [
                'priority' => 75,
                'type' => 'opportunity',
                'title' => $opp->title ?? 'Opportunity',
                'summary' => $opp->description,
                'id' => $opp->id,
                'href' => '/dashboard/executive',
            ];
        }

        if ($health && $health->overall_score < 50) {
            $items[] = [
                'priority' => 80,
                'type' => 'health',
                'title' => 'Business health needs attention',
                'summary' => $health->summary,
                'id' => null,
                'href' => '/dashboard/mission-control',
            ];
        }

        usort($items, fn ($a, $b) => $b['priority'] <=> $a['priority']);

        return array_slice($items, 0, 20);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function explainability(int $companyId, int $trustLogId): ?array
    {
        $log = AgentTrustLog::where('company_id', $companyId)->where('id', $trustLogId)->first();
        if (! $log) {
            return null;
        }

        return [
            'id' => $log->id,
            'action' => $log->action_type,
            'reasoningSummary' => $log->reasoning_summary,
            'confidence' => $log->confidence,
            'toolsUsed' => $log->tools_used,
            'dataConsulted' => $log->data_consulted,
            'explainability' => $log->explainability,
            'createdAt' => $log->created_at?->toIso8601String(),
        ];
    }
}
