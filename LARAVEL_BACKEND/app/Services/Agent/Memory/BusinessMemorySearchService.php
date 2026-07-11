<?php

namespace App\Services\Agent\Memory;

use App\Models\BusinessTimelineEvent;
use App\Models\CommerceBrief;
use App\Models\Company;
use App\Models\Message;
use App\Models\OwnerAnalyticsInvestigation;
use App\Services\AI\KnowledgeChunkService;

/**
 * Unified memory search — semantic chunks + keyword hits across nervous-system records.
 */
final class BusinessMemorySearchService
{
    public function __construct(protected KnowledgeChunkService $knowledgeChunks) {}

    /**
     * @return array{query: string, results: list<array<string, mixed>>, counts: array<string, int>}
     */
    public function search(Company $company, string $query, int $limit = 15): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['query' => '', 'results' => [], 'counts' => ['total' => 0]];
        }

        $results = [];
        $perSource = max(3, (int) ceil($limit / 4));

        foreach ($this->knowledgeChunks->search((int) $company->id, $query, null, $perSource) as $hit) {
            $results[] = [
                'source' => 'knowledge_chunk',
                'sourceType' => $hit['source_type'],
                'sourceId' => $hit['source_id'],
                'title' => ucfirst(str_replace('_', ' ', (string) $hit['source_type'])),
                'snippet' => mb_substr((string) $hit['content'], 0, 280),
                'score' => round((float) $hit['score'], 3),
                'occurredAt' => null,
            ];
        }

        OwnerAnalyticsInvestigation::query()
            ->where('company_id', $company->id)
            ->where(function ($q) use ($query) {
                $q->where('question', 'like', '%'.$query.'%');
            })
            ->orderByDesc('created_at')
            ->limit($perSource)
            ->get(['id', 'question', 'findings', 'confidence', 'created_at'])
            ->each(function (OwnerAnalyticsInvestigation $inv) use (&$results) {
                $snippet = is_array($inv->findings) && isset($inv->findings[0]['claim'])
                    ? (string) $inv->findings[0]['claim']
                    : null;
                $results[] = [
                    'source' => 'investigation',
                    'sourceType' => 'owner_analytics_investigation',
                    'sourceId' => $inv->id,
                    'title' => 'Investigation: '.mb_substr($inv->question, 0, 80),
                    'snippet' => $snippet,
                    'score' => (float) ($inv->confidence ?? 0.6),
                    'occurredAt' => $inv->created_at?->toIso8601String(),
                ];
            });

        BusinessTimelineEvent::query()
            ->where('company_id', $company->id)
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', '%'.$query.'%')
                    ->orWhere('summary', 'like', '%'.$query.'%');
            })
            ->orderByDesc('occurred_at')
            ->limit($perSource)
            ->get(['id', 'title', 'summary', 'event_type', 'occurred_at'])
            ->each(function (BusinessTimelineEvent $event) use (&$results) {
                $results[] = [
                    'source' => 'timeline',
                    'sourceType' => $event->event_type,
                    'sourceId' => $event->id,
                    'title' => $event->title,
                    'snippet' => $event->summary,
                    'score' => 0.5,
                    'occurredAt' => $event->occurred_at?->toIso8601String(),
                ];
            });

        CommerceBrief::query()
            ->where('company_id', $company->id)
            ->where('summary', 'like', '%'.$query.'%')
            ->orderByDesc('brief_date')
            ->limit($perSource)
            ->get(['id', 'summary', 'brief_date'])
            ->each(function (CommerceBrief $brief) use (&$results) {
                $results[] = [
                    'source' => 'brief',
                    'sourceType' => 'commerce_brief',
                    'sourceId' => $brief->id,
                    'title' => 'Commerce brief · '.$brief->brief_date?->toDateString(),
                    'snippet' => mb_substr((string) $brief->summary, 0, 280),
                    'score' => 0.45,
                    'occurredAt' => $brief->brief_date?->toIso8601String(),
                ];
            });

        Message::query()
            ->whereHas('chat', fn ($q) => $q->where('company_id', $company->id))
            ->where('content', 'like', '%'.$query.'%')
            ->orderByDesc('created_at')
            ->limit($perSource)
            ->get(['id', 'chat_id', 'content', 'sender', 'created_at'])
            ->each(function (Message $msg) use (&$results) {
                $results[] = [
                    'source' => 'chat',
                    'sourceType' => 'message',
                    'sourceId' => (int) $msg->id,
                    'title' => ucfirst($msg->sender ?? 'message').' message',
                    'snippet' => mb_substr((string) $msg->content, 0, 280),
                    'score' => 0.42,
                    'occurredAt' => $msg->created_at?->toIso8601String(),
                ];
            });

        usort($results, fn ($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

        $results = array_slice($results, 0, $limit);

        $counts = [
            'total' => count($results),
            'knowledge' => count(array_filter($results, fn ($r) => $r['source'] === 'knowledge_chunk')),
            'investigations' => count(array_filter($results, fn ($r) => $r['source'] === 'investigation')),
            'timeline' => count(array_filter($results, fn ($r) => $r['source'] === 'timeline')),
            'briefs' => count(array_filter($results, fn ($r) => $r['source'] === 'brief')),
            'chats' => count(array_filter($results, fn ($r) => $r['source'] === 'chat')),
        ];

        return [
            'query' => $query,
            'results' => $results,
            'counts' => $counts,
        ];
    }
}
