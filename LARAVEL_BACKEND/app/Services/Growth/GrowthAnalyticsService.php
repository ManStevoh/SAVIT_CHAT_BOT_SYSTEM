<?php

namespace App\Services\Growth;

use App\Models\AttributionEvent;
use App\Models\GrowthAdSpendEntry;
use App\Models\SocialPost;
use App\Models\SocialPostMetric;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GrowthAnalyticsService
{
    public function executiveSummary(int $companyId, string $period = '30d'): array
    {
        $days = match ($period) {
            '7d' => 7,
            '90d' => 90,
            default => 30,
        };
        $since = now()->subDays($days);

        $leads = AttributionEvent::where('company_id', $companyId)
            ->where('event_type', 'lead')
            ->where('created_at', '>=', $since)
            ->count();

        $whatsappStarts = AttributionEvent::where('company_id', $companyId)
            ->where('event_type', 'whatsapp_start')
            ->where('created_at', '>=', $since)
            ->count();

        $clicks = AttributionEvent::where('company_id', $companyId)
            ->where('event_type', 'click')
            ->where('created_at', '>=', $since)
            ->count();

        $orders = AttributionEvent::where('company_id', $companyId)
            ->where('event_type', 'order')
            ->where('created_at', '>=', $since)
            ->count();

        $revenue = (float) AttributionEvent::where('company_id', $companyId)
            ->where('event_type', 'revenue')
            ->where('created_at', '>=', $since)
            ->sum('revenue');

        $conversionRate = $clicks > 0 ? round(($whatsappStarts / $clicks) * 100, 2) : 0;
        $leadToOrderRate = $leads > 0 ? round(($orders / $leads) * 100, 2) : 0;

        $adSpend = (float) GrowthAdSpendEntry::where('company_id', $companyId)
            ->where('spent_at', '>=', $since->toDateString())
            ->sum('amount');

        $costPerLead = $leads > 0 && $adSpend > 0 ? round($adSpend / $leads, 2) : null;
        $customerAcquisitionCost = $orders > 0 && $adSpend > 0 ? round($adSpend / $orders, 2) : null;
        $roi = $adSpend > 0 ? round((($revenue - $adSpend) / $adSpend) * 100, 2) : null;

        return [
            'period' => $period,
            'leads' => $leads,
            'whatsappStarts' => $whatsappStarts,
            'clicks' => $clicks,
            'orders' => $orders,
            'revenue' => $revenue,
            'adSpend' => $adSpend,
            'conversionRate' => $conversionRate,
            'leadToOrderRate' => $leadToOrderRate,
            'costPerLead' => $costPerLead,
            'customerAcquisitionCost' => $customerAcquisitionCost,
            'roi' => $roi,
        ];
    }

    public function platformBreakdown(int $companyId, string $period = '30d'): array
    {
        $days = match ($period) {
            '7d' => 7,
            '90d' => 90,
            default => 30,
        };
        $since = now()->subDays($days);

        return AttributionEvent::where('company_id', $companyId)
            ->where('event_type', 'revenue')
            ->where('created_at', '>=', $since)
            ->whereNotNull('platform')
            ->select('platform', DB::raw('COUNT(*) as orders'), DB::raw('SUM(revenue) as revenue'))
            ->groupBy('platform')
            ->get()
            ->map(fn ($row) => [
                'platform' => $row->platform,
                'orders' => (int) $row->orders,
                'revenue' => (float) $row->revenue,
                'leads' => AttributionEvent::where('company_id', $companyId)
                    ->where('platform', $row->platform)
                    ->where('event_type', 'lead')
                    ->where('created_at', '>=', $since)
                    ->count(),
            ])
            ->values()
            ->all();
    }

    public function topPerformingPosts(int $companyId, string $period = '30d', int $limit = 10): array
    {
        $days = match ($period) {
            '7d' => 7,
            '90d' => 90,
            default => 30,
        };
        $since = now()->subDays($days);

        $posts = SocialPost::where('company_id', $companyId)
            ->where('created_at', '>=', $since)
            ->with('latestMetrics')
            ->get();

        return $posts->map(function (SocialPost $post) use ($companyId, $since) {
            $metrics = $post->latestMetrics;
            $clicks = AttributionEvent::where('social_post_id', $post->id)
                ->where('event_type', 'click')
                ->where('created_at', '>=', $since)
                ->count();
            $leads = AttributionEvent::where('social_post_id', $post->id)
                ->where('event_type', 'lead')
                ->where('created_at', '>=', $since)
                ->count();
            $orders = AttributionEvent::where('social_post_id', $post->id)
                ->where('event_type', 'order')
                ->where('created_at', '>=', $since)
                ->count();
            $revenue = (float) AttributionEvent::where('social_post_id', $post->id)
                ->where('event_type', 'revenue')
                ->where('created_at', '>=', $since)
                ->sum('revenue');

            return [
                'id' => (string) $post->id,
                'title' => $post->title ?? Str::limit($post->content, 50),
                'platform' => $post->platform,
                'reach' => $metrics?->reach ?? 0,
                'clicks' => max($clicks, $metrics?->clicks ?? 0),
                'leads' => $leads,
                'orders' => $orders,
                'revenue' => $revenue,
                'engagementRate' => (float) ($metrics?->engagement_rate ?? 0),
                'performanceScore' => $post->performance_score !== null ? (float) $post->performance_score : null,
                'contentTags' => $post->content_tags ?? [],
            ];
        })
            ->sortByDesc('revenue')
            ->take($limit)
            ->values()
            ->all();
    }

    public function contentIntelligence(int $companyId): array
    {
        $posts = SocialPost::where('company_id', $companyId)
            ->where('status', 'published')
            ->with('latestMetrics')
            ->get();

        $byPlatform = [];
        $byContentType = [];
        $byHour = array_fill(0, 24, ['posts' => 0, 'revenue' => 0]);

        foreach ($posts as $post) {
            $revenue = (float) AttributionEvent::where('social_post_id', $post->id)
                ->where('event_type', 'revenue')
                ->sum('revenue');

            $platform = $post->platform;
            $byPlatform[$platform] = ($byPlatform[$platform] ?? 0) + $revenue;

            $type = $post->content_type;
            $byContentType[$type] = ($byContentType[$type] ?? 0) + $revenue;

            if ($post->published_at) {
                $hour = (int) $post->published_at->format('G');
                $byHour[$hour]['posts']++;
                $byHour[$hour]['revenue'] += $revenue;
            }
        }

        $bestPlatform = collect($byPlatform)->sortDesc()->keys()->first();
        $bestContentType = collect($byContentType)->sortDesc()->keys()->first();
        $bestHour = collect($byHour)->sortByDesc('revenue')->keys()->first();

        return [
            'bestPlatform' => $bestPlatform,
            'bestContentType' => $bestContentType,
            'bestPostingHour' => $bestHour !== null ? (int) $bestHour : null,
            'platformRevenue' => collect($byPlatform)->map(fn ($v, $k) => ['platform' => $k, 'revenue' => $v])->values()->all(),
            'contentTypeRevenue' => collect($byContentType)->map(fn ($v, $k) => ['contentType' => $k, 'revenue' => $v])->values()->all(),
        ];
    }

    public function funnelMetrics(int $companyId, string $period = '30d'): array
    {
        $days = match ($period) {
            '7d' => 7,
            '90d' => 90,
            default => 30,
        };
        $since = now()->subDays($days);

        $reach = (int) SocialPostMetric::whereHas('socialPost', fn ($q) => $q->where('company_id', $companyId))
            ->where('recorded_at', '>=', $since)
            ->sum('reach');

        $clicks = AttributionEvent::where('company_id', $companyId)->where('event_type', 'click')->where('created_at', '>=', $since)->count();
        $whatsapp = AttributionEvent::where('company_id', $companyId)->where('event_type', 'whatsapp_start')->where('created_at', '>=', $since)->count();
        $leads = AttributionEvent::where('company_id', $companyId)->where('event_type', 'lead')->where('created_at', '>=', $since)->count();
        $orders = AttributionEvent::where('company_id', $companyId)->where('event_type', 'order')->where('created_at', '>=', $since)->count();
        $revenue = (float) AttributionEvent::where('company_id', $companyId)->where('event_type', 'revenue')->where('created_at', '>=', $since)->sum('revenue');

        return [
            ['stage' => 'reach', 'value' => $reach],
            ['stage' => 'clicks', 'value' => $clicks],
            ['stage' => 'whatsapp', 'value' => $whatsapp],
            ['stage' => 'leads', 'value' => $leads],
            ['stage' => 'orders', 'value' => $orders],
            ['stage' => 'revenue', 'value' => $revenue],
        ];
    }
}
