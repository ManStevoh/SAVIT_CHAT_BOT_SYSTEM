<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Jobs\Growth\ScorePostPerformanceJob;
use App\Jobs\Growth\SyncSocialPostMetricsJob;
use App\Models\SocialPost;
use App\Services\Growth\AttributionService;
use App\Services\Growth\ContentPredictionService;
use App\Services\Growth\ContentTagger;
use App\Services\Growth\GrowthContentService;
use App\Services\Growth\GrowthImageGenerationService;
use App\Services\Growth\GrowthLimitService;
use App\Services\Growth\MetaSocialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GrowthPostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $query = SocialPost::where('company_id', $companyId)->with(['latestMetrics', 'attributionLink']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('platform')) {
            $query->where('platform', $request->platform);
        }

        $posts = $query->orderByDesc('created_at')->get()->map(fn (SocialPost $p) => $this->formatPost($p));

        return response()->json($posts->values()->all());
    }

    public function store(Request $request, GrowthContentService $content): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        $validated = $request->validate([
            'platform' => 'required|string|in:facebook,instagram,linkedin,tiktok,twitter,whatsapp',
            'title' => 'nullable|string|max:255',
            'content' => 'required|string',
            'contentType' => 'nullable|string|in:text,image,video,carousel',
            'hashtags' => 'nullable|array',
            'mediaUrls' => 'nullable|array',
            'mediaUrls.*' => 'url|max:500',
            'scheduledAt' => 'nullable|date',
        ]);

        $content = $validated['content'];
        $contentType = $validated['contentType'] ?? 'text';

        $post = SocialPost::create([
            'company_id' => $company->id,
            'platform' => $validated['platform'],
            'title' => $validated['title'] ?? null,
            'content' => $content,
            'content_type' => $contentType,
            'content_tags' => ContentTagger::inferTags($content, $contentType),
            'hashtags' => $validated['hashtags'] ?? [],
            'media_urls' => $validated['mediaUrls'] ?? null,
            'status' => isset($validated['scheduledAt']) ? 'scheduled' : 'draft',
            'scheduled_at' => $validated['scheduledAt'] ?? null,
            'utm_source' => $validated['platform'],
            'utm_medium' => 'social',
        ]);

        app(AttributionService::class)->createLinkForPost(
            $post,
            $company->whatsappAccount?->display_phone_number ?? $company->phone
        );

        app(ContentPredictionService::class)->predictAndStore($post->fresh());

        return response()->json(['success' => true, 'post' => $this->formatPost($post->fresh(['attributionLink', 'latestMetrics']))]);
    }

    public function generate(Request $request, GrowthContentService $content): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        $validated = $request->validate([
            'count' => 'nullable|integer|min:1|max:10',
            'platform' => 'nullable|string|in:facebook,instagram,linkedin,tiktok,twitter',
            'topic' => 'nullable|string|max:500',
            'audience' => 'nullable|string|max:500',
            'tone' => 'nullable|string|max:255',
        ]);

        try {
            $result = $content->generatePosts($company, $validated);

            return response()->json([
                'success' => true,
                'posts' => $result->posts,
                'aiGenerated' => $result->aiGenerated,
                'aiError' => $result->aiError,
            ]);
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'limit reached')) {
                GrowthLimitService::notifyIfLimitReached($company, 'ai_posts');
            }

            return response()->json(['success' => false, 'message' => $e->getMessage(), 'code' => 'limit_reached'], 422);
        }
    }

    public function approve(Request $request, SocialPost $post, GrowthContentService $content): JsonResponse
    {
        $this->authorizePost($request, $post);
        $updated = $content->approvePost($post, (int) $request->user()->id);

        return response()->json(['success' => true, 'post' => $this->formatPost($updated)]);
    }

    public function schedule(Request $request, SocialPost $post, GrowthContentService $content): JsonResponse
    {
        $this->authorizePost($request, $post);
        $validated = $request->validate(['scheduledAt' => 'required|date|after:now']);
        $updated = $content->schedulePost($post, new \DateTimeImmutable($validated['scheduledAt']));

        return response()->json(['success' => true, 'post' => $this->formatPost($updated)]);
    }

    public function publish(Request $request, SocialPost $post, MetaSocialService $meta): JsonResponse
    {
        $this->authorizePost($request, $post);

        if ($post->platform === 'instagram') {
            $media = $post->media_urls ?? [];
            if (! is_array($media) || empty($media[0])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Instagram posts require at least one image URL in media_urls.',
                ], 422);
            }
        }

        if (! $post->approved_at) {
            return response()->json([
                'success' => false,
                'message' => 'Approve the post before publishing.',
            ], 422);
        }

        $updated = $meta->publishPost($post);
        if ($updated->status === 'failed') {
            return response()->json([
                'success' => false,
                'message' => $updated->publish_error ?? 'Publish failed',
                'metaError' => $updated->publish_error,
                'post' => $this->formatPost($updated->fresh(['latestMetrics', 'attributionLink'])),
            ], 422);
        }

        SyncSocialPostMetricsJob::dispatch($updated->id);
        ScorePostPerformanceJob::dispatch($updated->id);

        return response()->json(['success' => true, 'post' => $this->formatPost($updated->fresh(['latestMetrics', 'attributionLink']))]);
    }

    public function uploadImage(Request $request, SocialPost $post): JsonResponse
    {
        $this->authorizePost($request, $post);

        $validated = $request->validate([
            'image' => 'required|image|max:5120',
        ]);

        $path = $validated['image']->store('growth-posts/'.$post->company_id, 'public');
        $url = Storage::disk('public')->url($path);
        $media = $post->media_urls ?? [];
        $media[] = $url;
        $post->update([
            'media_urls' => $media,
            'content_type' => $post->content_type === 'text' ? 'image' : $post->content_type,
        ]);

        return response()->json([
            'success' => true,
            'url' => $url,
            'post' => $this->formatPost($post->fresh(['attributionLink', 'latestMetrics'])),
        ]);
    }

    public function generateImage(Request $request, SocialPost $post, GrowthImageGenerationService $images): JsonResponse
    {
        $this->authorizePost($request, $post);

        if ($post->status === 'published') {
            return response()->json(['success' => false, 'message' => 'Cannot generate images for published posts.'], 422);
        }

        $validated = $request->validate([
            'prompt' => 'nullable|string|max:2000',
        ]);

        $outcome = $images->generateForPost($post, $validated['prompt'] ?? null);

        if (! $outcome['success']) {
            return response()->json([
                'success' => false,
                'message' => $outcome['error'] ?? 'Image generation failed',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'url' => $outcome['url'],
            'post' => $this->formatPost($post->fresh(['attributionLink', 'latestMetrics'])),
        ]);
    }

    public function sharePackage(Request $request, SocialPost $post, AttributionService $attribution): JsonResponse
    {
        $this->authorizePost($request, $post);
        $link = $post->attributionLink;
        if (! $link) {
            $link = $attribution->createLinkForPost(
                $post,
                $request->user()->company?->whatsappAccount?->display_phone_number
            );
        }

        $trackingUrl = $attribution->trackingUrl($link);
        $hashtags = collect($post->hashtags ?? [])->map(fn ($h) => str_starts_with($h, '#') ? $h : "#{$h}")->implode(' ');
        $caption = trim($post->content."\n\n".$hashtags."\n\n🔗 ".$trackingUrl);

        $platformCaptions = [
            'instagram' => $caption,
            'facebook' => $caption,
            'default' => $caption,
        ];

        return response()->json([
            'trackingUrl' => $trackingUrl,
            'whatsappPrefill' => $link->whatsapp_prefill,
            'caption' => $platformCaptions[$post->platform] ?? $platformCaptions['default'],
            'clipboardPackage' => $caption,
        ]);
    }

    public function destroy(Request $request, SocialPost $post): JsonResponse
    {
        $this->authorizePost($request, $post);
        if ($post->status === 'published') {
            return response()->json(['success' => false, 'message' => 'Cannot delete published posts.'], 422);
        }
        $post->delete();

        return response()->json(['success' => true]);
    }

    private function authorizePost(Request $request, SocialPost $post): void
    {
        if ((int) $request->user()->company_id !== (int) $post->company_id) {
            abort(403);
        }
    }

    private function formatPost(SocialPost $post): array
    {
        $link = $post->relationLoaded('attributionLink') ? $post->attributionLink : $post->attributionLink()->first();
        $metrics = $post->relationLoaded('latestMetrics') ? $post->latestMetrics : $post->latestMetrics()->first();

        return [
            'id' => (string) $post->id,
            'platform' => $post->platform,
            'title' => $post->title,
            'content' => $post->content,
            'contentType' => $post->content_type,
            'hashtags' => $post->hashtags ?? [],
            'status' => $post->status,
            'scheduledAt' => $post->scheduled_at?->toIso8601String(),
            'publishedAt' => $post->published_at?->toIso8601String(),
            'aiGenerated' => $post->ai_generated,
            'approvedAt' => $post->approved_at?->toIso8601String(),
            'performanceScore' => $post->performance_score !== null ? (float) $post->performance_score : null,
            'predictedRevenueScore' => $post->predicted_revenue_score !== null ? (float) $post->predicted_revenue_score : null,
            'contentTags' => $post->content_tags ?? [],
            'predictionFactors' => $post->prediction_factors,
            'mediaUrls' => $post->media_urls ?? [],
            'publishError' => $post->publish_error,
            'trackingUrl' => $link ? app(AttributionService::class)->trackingUrl($link) : null,
            'metrics' => $metrics ? [
                'reach' => $metrics->reach,
                'clicks' => $metrics->clicks,
                'likes' => $metrics->likes,
                'comments' => $metrics->comments,
                'shares' => $metrics->shares,
                'engagementRate' => (float) $metrics->engagement_rate,
            ] : null,
            'createdAt' => $post->created_at?->toIso8601String(),
        ];
    }
}
