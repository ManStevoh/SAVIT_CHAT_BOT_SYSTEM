<?php

namespace App\Services\Agent\Timeline;

use App\Models\BusinessTimelineEvent;
use App\Models\CommerceAgentEvent;
use App\Models\CommerceBrief;
use App\Models\Company;
use App\Models\Order;
use App\Models\OwnerAnalyticsInvestigation;
use App\Models\BusinessOpportunity;
use Illuminate\Support\Carbon;

/**
 * Unified business timeline — chronological narrative of company life.
 */
final class BusinessTimelineService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(
        Company $company,
        string $eventType,
        string $title,
        ?string $summary = null,
        array $payload = [],
        ?string $sourceType = null,
        ?int $sourceId = null,
        int $importance = 50,
        ?Carbon $occurredAt = null,
        string $category = 'general',
    ): BusinessTimelineEvent {
        $occurredAt ??= now();

        if ($sourceType !== null && $sourceId !== null) {
            return BusinessTimelineEvent::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'event_type' => $eventType,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                ],
                [
                    'category' => $category,
                    'title' => mb_substr($title, 0, 255),
                    'summary' => $summary ? mb_substr($summary, 0, 2000) : null,
                    'payload' => $payload,
                    'importance' => min(100, max(1, $importance)),
                    'occurred_at' => $occurredAt,
                ],
            );
        }

        return BusinessTimelineEvent::create([
            'company_id' => $company->id,
            'event_type' => $eventType,
            'category' => $category,
            'title' => mb_substr($title, 0, 255),
            'summary' => $summary ? mb_substr($summary, 0, 2000) : null,
            'payload' => $payload,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'importance' => min(100, max(1, $importance)),
            'occurred_at' => $occurredAt,
        ]);
    }

    /**
     * Backfill timeline from existing nervous-system signals.
     */
    public function syncFromCompany(Company $company, int $limit = 100): int
    {
        $count = 0;

        Order::query()
            ->where('company_id', $company->id)
            ->where('payment_status', 'paid')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'order_number', 'total', 'created_at'])
            ->each(function (Order $order) use ($company, &$count) {
                $this->record(
                    $company,
                    'order_paid',
                    "Order {$order->order_number} paid",
                    'Revenue: '.$order->total,
                    ['order_number' => $order->order_number, 'total' => (float) $order->total],
                    'order',
                    (int) $order->id,
                    40,
                    $order->created_at,
                    'commerce',
                );
                $count++;
            });

        CommerceAgentEvent::query()
            ->where('company_id', $company->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->each(function (CommerceAgentEvent $event) use ($company, &$count) {
                $this->record(
                    $company,
                    $event->event_type,
                    ucfirst(str_replace('_', ' ', $event->event_type)),
                    (string) ($event->payload['summary'] ?? $event->event_key),
                    $event->payload ?? [],
                    'commerce_agent_event',
                    (int) $event->id,
                    in_array($event->event_type, ['sales_drop', 'low_stock'], true) ? 85 : 60,
                    $event->created_at,
                    'signal',
                );
                $count++;
            });

        OwnerAnalyticsInvestigation::query()
            ->where('company_id', $company->id)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->each(function (OwnerAnalyticsInvestigation $inv) use ($company, &$count) {
                $this->record(
                    $company,
                    'investigation',
                    'Owner asked: '.mb_substr($inv->question, 0, 80),
                    is_array($inv->findings) ? implode(' ', array_slice($inv->findings, 0, 2)) : null,
                    ['question' => $inv->question, 'confidence' => $inv->confidence],
                    'owner_analytics_investigation',
                    (int) $inv->id,
                    70,
                    $inv->created_at,
                    'consciousness',
                );
                $count++;
            });

        CommerceBrief::query()
            ->where('company_id', $company->id)
            ->orderByDesc('brief_date')
            ->limit(14)
            ->get()
            ->each(function (CommerceBrief $brief) use ($company, &$count) {
                $this->record(
                    $company,
                    'morning_brief',
                    'Daily commerce brief',
                    mb_substr((string) $brief->summary, 0, 200),
                    ['brief_date' => $brief->brief_date?->toDateString()],
                    'commerce_brief',
                    (int) $brief->id,
                    55,
                    $brief->brief_date ?? $brief->created_at,
                    'consciousness',
                );
                $count++;
            });

        BusinessOpportunity::query()
            ->where('company_id', $company->id)
            ->where('status', 'open')
            ->orderByDesc('detected_at')
            ->limit(20)
            ->get()
            ->each(function (BusinessOpportunity $opp) use ($company, &$count) {
                $this->record(
                    $company,
                    'opportunity',
                    $opp->title ?? 'Business opportunity',
                    $opp->description,
                    ['type' => $opp->opportunity_type, 'impact' => $opp->estimated_impact],
                    'business_opportunity',
                    (int) $opp->id,
                    75,
                    $opp->detected_at ?? $opp->created_at,
                    'growth',
                );
                $count++;
            });

        return $count;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function timeline(Company $company, int $limit = 50, ?string $category = null): array
    {
        $query = BusinessTimelineEvent::query()
            ->where('company_id', $company->id)
            ->orderByDesc('occurred_at')
            ->limit($limit);

        if ($category !== null) {
            $query->where('category', $category);
        }

        return $query->get()->map(fn (BusinessTimelineEvent $e) => [
            'id' => $e->id,
            'eventType' => $e->event_type,
            'category' => $e->category,
            'title' => $e->title,
            'summary' => $e->summary,
            'importance' => $e->importance,
            'occurredAt' => $e->occurred_at?->toIso8601String(),
            'payload' => $e->payload,
        ])->all();
    }
}
