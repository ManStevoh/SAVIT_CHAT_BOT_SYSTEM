<?php

namespace App\Services\Growth;

use App\Models\Company;
use App\Models\PlatformSetting;
use App\Models\SocialPost;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GrowthContentService
{
    public function __construct(
        protected AttributionService $attributionService,
        protected GrowthAnalyticsService $analytics,
        protected ContentPredictionService $prediction
    ) {}

    /**
     * @return array<int, array{title: string, content: string, hashtags: array<int, string>, platform: string}>
     */
    public function generatePosts(Company $company, array $params): array
    {
        if (! GrowthLimitService::isGrowthEnabled($company)) {
            throw new \RuntimeException('Growth Engine is not enabled for your plan. Upgrade to access AI content.');
        }

        if (! GrowthLimitService::canGenerateAiContent($company)) {
            throw new \RuntimeException('AI content generation limit reached for this billing period.');
        }

        $count = min(max((int) ($params['count'] ?? 5), 1), 10);
        $platform = $params['platform'] ?? 'facebook';
        $topic = $params['topic'] ?? 'our products and services';
        $audience = $params['audience'] ?? 'potential customers';
        $tone = $params['tone'] ?? 'professional and friendly';
        $learningContext = $params['learningContext'] ?? $this->buildLearningContext($company);

        $products = $company->products()->limit(10)->get(['name', 'price', 'description']);
        $productList = $products->map(fn ($p) => "- {$p->name}: {$p->price}")->implode("\n");

        $prompt = "Generate {$count} social media posts for {$platform}.\n"
            ."Business: {$company->name}\n"
            ."Topic: {$topic}\n"
            ."Target audience: {$audience}\n"
            ."Tone: {$tone}\n"
            ."Products:\n{$productList}\n"
            ."Performance learnings:\n{$learningContext}\n\n"
            ."Return valid JSON array with objects: title, content, hashtags (array of strings), contentType (text|image|video).\n"
            ."Each post should drive WhatsApp inquiries or orders. Include a clear call-to-action.";

        $raw = $this->callOpenAi($prompt);
        $parsed = $this->parseJsonArray($raw);

        return $this->persistGeneratedPosts($company, $platform, $topic, array_slice($parsed, 0, $count));
    }

    /**
     * Generate posts mimicking top-performing content (smart generate).
     *
     * @return array<int, array<string, mixed>>
     */
    public function generateFromWinners(Company $company, array $params = []): array
    {
        if (! GrowthLimitService::isGrowthEnabled($company)) {
            throw new \RuntimeException('Growth Engine is not enabled for your plan.');
        }
        if (! GrowthLimitService::canGenerateAiContent($company)) {
            throw new \RuntimeException('AI content generation limit reached for this billing period.');
        }

        $count = min(max((int) ($params['count'] ?? 3), 1), 10);
        $platform = $params['platform'] ?? 'facebook';
        $winners = $this->analytics->topPerformingPosts($company->id, '30d', 3);

        if (empty($winners)) {
            return $this->generatePosts($company, array_merge($params, ['count' => $count, 'platform' => $platform]));
        }

        $examples = collect($winners)->map(function ($w, $i) {
            $post = SocialPost::find($w['id']);

            return 'Winner '.($i + 1)." (revenue: {$w['revenue']}): "
                .($post?->content ?? $w['title'])
                .' Tags: '.implode(', ', $post?->content_tags ?? []);
        })->implode("\n");

        $prompt = "Generate {$count} NEW social media posts for {$platform}.\n"
            ."Business: {$company->name}\n"
            ."Mimic the structure, tone, and CTA style of these proven winners but use fresh copy:\n{$examples}\n\n"
            ."Return valid JSON array: title, content, hashtags, contentType.\n"
            ."Each post must include a WhatsApp call-to-action.";

        $parsed = $this->parseJsonArray($this->callOpenAi($prompt));

        return $this->persistGeneratedPosts(
            $company,
            $platform,
            $params['topic'] ?? 'winning patterns',
            array_slice($parsed, 0, $count)
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    protected function persistGeneratedPosts(Company $company, string $platform, string $topic, array $items): array
    {
        $results = [];
        foreach ($items as $item) {
            $content = $item['content'] ?? '';
            $contentType = $item['contentType'] ?? 'text';
            $tags = ContentTagger::inferTags($content, $contentType);

            $post = SocialPost::create([
                'company_id' => $company->id,
                'platform' => $platform,
                'title' => $item['title'] ?? null,
                'content' => $content,
                'content_type' => $contentType,
                'content_tags' => $tags,
                'hashtags' => $item['hashtags'] ?? [],
                'status' => 'draft',
                'utm_source' => $platform,
                'utm_medium' => 'social',
                'utm_campaign' => str($topic)->slug()->toString(),
                'ai_generated' => true,
            ]);

            $prediction = $this->prediction->predictAndStore($post);
            $waPhone = $company->whatsappAccount?->display_phone_number ?? $company->phone;
            $link = $this->attributionService->createLinkForPost($post, $waPhone);

            $results[] = [
                'id' => (string) $post->id,
                'title' => $post->title,
                'content' => $post->content,
                'hashtags' => $post->hashtags ?? [],
                'platform' => $post->platform,
                'status' => $post->status,
                'contentTags' => $tags,
                'predictedRevenueScore' => $prediction['score'],
                'estimatedRevenue' => $prediction['estimatedRevenue'],
                'trackingUrl' => $this->attributionService->trackingUrl($link),
                'aiGenerated' => true,
            ];
        }

        return $results;
    }

    protected function buildLearningContext(Company $company): string
    {
        $intelligence = $this->analytics->contentIntelligence($company->id);
        $lines = [];
        if ($intelligence['bestPlatform']) {
            $lines[] = "- Best platform: {$intelligence['bestPlatform']}";
        }
        if ($intelligence['bestContentType']) {
            $lines[] = "- Best format: {$intelligence['bestContentType']}";
        }
        if ($intelligence['bestPostingHour'] !== null) {
            $lines[] = "- Best hour: {$intelligence['bestPostingHour']}:00";
        }

        $profile = \App\Models\GrowthBrandProfile::where('company_id', $company->id)->first();
        if ($profile?->winning_patterns['topTags'] ?? null) {
            $lines[] = '- Favor tags: '.implode(', ', $profile->winning_patterns['topTags']);
        }

        return $lines ? implode("\n", $lines) : '- No history yet; focus on clear WhatsApp CTAs.';
    }

    /**
     * Generate A/B content variants ranked by predicted score (not persisted until user saves).
     *
     * @return array<int, array<string, mixed>>
     */
    public function generateVariants(Company $company, array $params): array
    {
        if (! GrowthLimitService::canGenerateAiContent($company)) {
            throw new \RuntimeException('AI content generation limit reached for this billing period.');
        }

        $count = min(max((int) ($params['count'] ?? 3), 2), 5);
        $platform = $params['platform'] ?? 'facebook';
        $topic = $params['topic'] ?? 'our products and services';
        $angles = ['testimonial style', 'limited-time offer', 'educational tip', 'behind the scenes', 'customer story'];

        $prompt = "Generate {$count} DISTINCT social media post variants for {$platform}.\n"
            ."Business: {$company->name}\nTopic: {$topic}\n"
            ."Each variant must use a different angle: ".implode(', ', array_slice($angles, 0, $count)).".\n"
            ."Return JSON array: title, content, hashtags, contentType, angle (short label).\n"
            ."Each must include a WhatsApp CTA.";

        $parsed = $this->parseJsonArray($this->callOpenAi($prompt));
        $variants = [];

        foreach (array_slice($parsed, 0, $count) as $i => $item) {
            $content = $item['content'] ?? '';
            $contentType = $item['contentType'] ?? 'text';
            $draft = new SocialPost([
                'company_id' => $company->id,
                'platform' => $platform,
                'content' => $content,
                'content_type' => $contentType,
            ]);
            $prediction = $this->prediction->predictDraft($draft);

            $variants[] = [
                'variantIndex' => $i + 1,
                'angle' => $item['angle'] ?? ('Variant '.($i + 1)),
                'title' => $item['title'] ?? null,
                'content' => $content,
                'hashtags' => $item['hashtags'] ?? [],
                'contentType' => $contentType,
                'platform' => $platform,
                'predictedScore' => $prediction['score'],
                'estimatedRevenue' => $prediction['estimatedRevenue'],
                'hasEnoughData' => $prediction['hasEnoughData'],
                'explanations' => $prediction['explanations'],
                'tags' => $prediction['tags'],
            ];
        }

        usort($variants, fn ($a, $b) => $b['predictedScore'] <=> $a['predictedScore']);

        return array_values($variants);
    }

    /**
     * Persist user-selected variants as draft posts.
     *
     * @param  array<int, array<string, mixed>>  $variants
     * @return array<int, array<string, mixed>>
     */
    public function saveSelectedVariants(Company $company, string $platform, array $variants): array
    {
        return $this->persistGeneratedPosts($company, $platform, 'ab-variant', $variants);
    }

    public function approvePost(SocialPost $post, int $userId): SocialPost
    {
        $post->update([
            'status' => $post->scheduled_at && $post->scheduled_at->isFuture() ? 'scheduled' : 'draft',
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        return $post->fresh();
    }

    public function schedulePost(SocialPost $post, \DateTimeInterface $scheduledAt): SocialPost
    {
        $post->update([
            'scheduled_at' => $scheduledAt,
            'status' => 'scheduled',
        ]);

        return $post->fresh();
    }

    protected function callOpenAi(string $prompt): string
    {
        $settings = Cache::remember('platform_settings_openai', 300, fn () => PlatformSetting::first());
        $apiKey = $settings?->openai_api_key ?? config('services.openai.key');
        $model = $settings?->openai_model ?? 'gpt-4o-mini';

        if (! $apiKey) {
            return $this->fallbackGeneratedJson($prompt);
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(60)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a conversion-focused social media marketing expert. Output only valid JSON.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.7,
                ]);

            if ($response->successful()) {
                return (string) $response->json('choices.0.message.content', '[]');
            }

            Log::warning('GrowthContentService OpenAI failed', ['status' => $response->status()]);
        } catch (\Throwable $e) {
            Log::warning('GrowthContentService OpenAI error', ['error' => $e->getMessage()]);
        }

        return $this->fallbackGeneratedJson($prompt);
    }

    protected function fallbackGeneratedJson(string $prompt): string
    {
        preg_match('/Generate (\d+)/', $prompt, $m);
        $count = (int) ($m[1] ?? 3);

        $items = [];
        for ($i = 1; $i <= $count; $i++) {
            $items[] = [
                'title' => "Post {$i}",
                'content' => "Discover what we offer! Message us on WhatsApp to learn more. (Post {$i})",
                'hashtags' => ['business', 'growth', 'whatsapp'],
            ];
        }

        return json_encode($items);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function parseJsonArray(string $raw): array
    {
        $raw = trim($raw);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $raw, $m)) {
            $raw = trim($m[1]);
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
