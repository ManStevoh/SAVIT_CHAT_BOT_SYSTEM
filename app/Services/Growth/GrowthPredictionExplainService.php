<?php

namespace App\Services\Growth;

use App\Models\AttributionEvent;
use App\Models\SocialPost;

class GrowthPredictionExplainService
{
    public function hasMinimumData(int $companyId): bool
    {
        $minPosts = (int) config('growth.prediction.min_published_posts', 3);
        $minClicks = (int) config('growth.prediction.min_clicks', 10);

        $published = SocialPost::where('company_id', $companyId)->where('status', 'published')->count();
        $clicks = AttributionEvent::where('company_id', $companyId)->where('event_type', 'click')->count();

        return $published >= $minPosts && $clicks >= $minClicks;
    }

    /**
     * @param  array<string, mixed>  $factors
     * @return array<int, string>
     */
    public function explainFactors(array $factors, string $platform = ''): array
    {
        $lines = [];

        $tagMult = (float) ($factors['tagMultiplier'] ?? 1);
        if ($tagMult > 1.1) {
            $pct = (int) round(($tagMult - 1) * 100);
            $tags = implode(', ', $factors['topTags'] ?? []) ?: 'your winning content tags';
            $lines[] = "+{$pct}% because posts tagged {$tags} convert better for you.";
        } elseif ($tagMult < 0.95) {
            $lines[] = 'Content tags are untested — scores stay conservative until more data.';
        }

        $platMult = (float) ($factors['platformMultiplier'] ?? 1);
        $best = $factors['bestPlatform'] ?? null;
        if ($best && $platform === $best && $platMult > 1) {
            $pct = (int) round(($platMult - 1) * 100);
            $lines[] = "+{$pct}% because {$best} is your best-performing platform.";
        } elseif ($best && $platform && $platform !== $best) {
            $lines[] = "Lower on {$platform} — {$best} drives more attributed revenue for you.";
        }

        $typeMult = (float) ($factors['contentTypeMultiplier'] ?? 1);
        if ($typeMult > 1.05) {
            $lines[] = '+15% because this content type matches your top converters.';
        }

        $ctaBonus = (float) ($factors['ctaBonus'] ?? 1);
        if ($ctaBonus > 1) {
            $lines[] = '+15% for a clear WhatsApp call-to-action.';
        } elseif ($ctaBonus < 1) {
            $lines[] = 'Add a WhatsApp CTA to improve predicted conversion.';
        }

        $avg = (float) ($factors['avgHistoricalRevenue'] ?? 0);
        if ($avg > 0) {
            $lines[] = 'Based on '.number_format($avg, 0).' average revenue per attributed post.';
        }

        return $lines ?: ['Not enough history yet — publish and share posts to unlock personalized predictions.'];
    }

    /**
     * Compare predicted vs actual revenue for published posts.
     *
     * @return array{hasEnoughData: bool, items: array<int, array<string, mixed>>, accuracyPercent: ?float}
     */
    public function accuracyReport(int $companyId): array
    {
        if (! $this->hasMinimumData($companyId)) {
            return [
                'hasEnoughData' => false,
                'items' => [],
                'accuracyPercent' => null,
                'message' => 'Publish at least '.config('growth.prediction.min_published_posts', 3)
                    .' posts and get '.config('growth.prediction.min_clicks', 10).' clicks to see prediction accuracy.',
            ];
        }

        $posts = SocialPost::where('company_id', $companyId)
            ->where('status', 'published')
            ->whereNotNull('predicted_revenue_score')
            ->orderByDesc('published_at')
            ->limit(20)
            ->get();

        $items = [];
        $errors = [];

        foreach ($posts as $post) {
            $actual = (float) AttributionEvent::where('social_post_id', $post->id)
                ->where('event_type', 'revenue')
                ->sum('revenue');

            $factors = $post->prediction_factors ?? [];
            $predicted = (float) ($factors['estimatedRevenue'] ?? 0);
            if ($predicted <= 0 && $post->predicted_revenue_score) {
                $avg = (float) ($factors['avgHistoricalRevenue'] ?? 500);
                $predicted = $avg * ((float) $post->predicted_revenue_score / 50);
            }

            if ($predicted > 0 && $actual > 0) {
                $errors[] = abs($predicted - $actual) / max($actual, 1);
            }

            $items[] = [
                'postId' => (string) $post->id,
                'title' => $post->title ?? \Illuminate\Support\Str::limit($post->content, 40),
                'predictedRevenue' => round($predicted, 2),
                'actualRevenue' => round($actual, 2),
                'predictedScore' => (float) $post->predicted_revenue_score,
                'publishedAt' => $post->published_at?->toIso8601String(),
            ];
        }

        $accuracyPercent = ! empty($errors)
            ? round((1 - (array_sum($errors) / count($errors))) * 100, 1)
            : null;

        return [
            'hasEnoughData' => true,
            'items' => $items,
            'accuracyPercent' => $accuracyPercent,
            'message' => $accuracyPercent !== null
                ? "Predictions are within {$accuracyPercent}% of actual on average."
                : 'Keep publishing — we need more attributed sales to score accuracy.',
        ];
    }
}
