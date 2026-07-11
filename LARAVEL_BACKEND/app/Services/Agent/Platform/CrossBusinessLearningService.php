<?php

namespace App\Services\Agent\Platform;

use App\Models\AttributionEvent;
use App\Models\Chat;
use App\Models\Company;
use App\Models\Message;
use App\Services\Agent\Cognitive\MetaLearningService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cross-business learning (#35) — anonymized platform patterns from aggregate outcomes.
 */
final class CrossBusinessLearningService
{
    public function __construct(
        protected MetaLearningService $metaLearning,
    ) {}

    public function analyzeAndRecord(): int
    {
        $recorded = 0;

        try {
            if ($this->recordFastReplyPattern()) {
                $recorded++;
            }
            if ($this->recordConversionFromCampaignPattern()) {
                $recorded++;
            }
        } catch (\Throwable $e) {
            Log::warning('Cross-business learning failed', ['error' => $e->getMessage()]);
        }

        return $recorded;
    }

    private function recordFastReplyPattern(): bool
    {
        $rows = DB::table('messages as m')
            ->join('chats as c', 'c.id', '=', 'm.chat_id')
            ->join('companies as co', 'co.id', '=', 'c.company_id')
            ->where('m.sender', 'customer')
            ->where('m.created_at', '>=', now()->subDays(30))
            ->select('c.company_id', 'c.id as chat_id', 'm.created_at as customer_at')
            ->limit(500)
            ->get();

        $fastBuckets = ['under_5' => 0, 'over_15' => 0];
        $fastConversions = 0;
        $slowConversions = 0;

        foreach ($rows as $row) {
            $customerAt = Carbon::parse($row->customer_at);
            $botReply = Message::where('chat_id', $row->chat_id)
                ->where('sender', 'bot')
                ->where('created_at', '>', $customerAt)
                ->orderBy('created_at')
                ->first();

            if (! $botReply) {
                continue;
            }

            $minutes = $customerAt->diffInMinutes($botReply->created_at);
            $converted = AttributionEvent::where('company_id', $row->company_id)
                ->where('event_type', 'order')
                ->where('created_at', '>=', $customerAt)
                ->where('created_at', '<=', $customerAt->copy()->addDays(7))
                ->exists();

            if ($minutes <= 5) {
                $fastBuckets['under_5']++;
                if ($converted) {
                    $fastConversions++;
                }
            } elseif ($minutes > 15) {
                $fastBuckets['over_15']++;
                if ($converted) {
                    $slowConversions++;
                }
            }
        }

        if ($fastBuckets['under_5'] < 10) {
            return false;
        }

        $fastRate = $fastBuckets['under_5'] > 0
            ? round(($fastConversions / $fastBuckets['under_5']) * 100, 1) : 0;
        $slowRate = $fastBuckets['over_15'] > 0
            ? round(($slowConversions / $fastBuckets['over_15']) * 100, 1) : 0;

        if ($fastRate <= $slowRate) {
            return false;
        }

        $this->metaLearning->recordPattern(
            'fast_reply_conversion',
            'engagement',
            "Businesses replying within 5 minutes show higher order conversion ({$fastRate}% vs {$slowRate}% for 15+ min delays).",
            ['all'],
            ['fast_conversion_pct' => $fastRate, 'slow_conversion_pct' => $slowRate, 'sample_chats' => $fastBuckets['under_5']],
        );

        return true;
    }

    private function recordConversionFromCampaignPattern(): bool
    {
        $withCampaign = (int) AttributionEvent::where('event_type', 'order')
            ->where('created_at', '>=', now()->subDays(60))
            ->whereNotNull('platform')
            ->count();

        if ($withCampaign < 5) {
            return false;
        }

        $this->metaLearning->recordPattern(
            'attributed_campaign_orders',
            'revenue',
            'Orders with marketing attribution tend to correlate with active campaign periods — track platform on attribution events.',
            ['all'],
            ['attributed_orders_60d' => $withCampaign],
        );

        return true;
    }
}
