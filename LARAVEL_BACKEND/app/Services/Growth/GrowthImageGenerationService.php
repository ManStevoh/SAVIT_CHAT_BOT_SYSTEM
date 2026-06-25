<?php

namespace App\Services\Growth;

use App\Models\Company;
use App\Models\SocialPost;
use App\Services\AI\AiGateway;
use App\Services\AI\GeminiImageResult;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class GrowthImageGenerationService
{
    public function __construct(
        private AiGateway $aiGateway,
    ) {}

    /**
     * @return array{success: bool, url?: string, error?: string, result: GeminiImageResult}
     */
    public function generateForPost(SocialPost $post, ?string $customPrompt = null): array
    {
        $company = $post->company;
        if (! $company) {
            return $this->fail('Company not found.', $this->emptyResult());
        }

        if (! GrowthLimitService::isGrowthEnabled($company)) {
            return $this->fail('Growth Engine is not enabled for your plan.', $this->emptyResult());
        }

        if (! GrowthLimitService::canGenerateAiImage($company)) {
            return $this->fail('AI image generation limit reached for this billing period.', $this->emptyResult());
        }

        $prompt = $customPrompt ?: $this->buildPosterPrompt($post, $company);
        $result = $this->aiGateway->generateImage($prompt, $company);

        if (! $result->success || $result->imageBytes === null) {
            return [
                'success' => false,
                'error' => $result->error ?? 'Image generation failed',
                'result' => $result,
            ];
        }

        $extension = $this->extensionForMime($result->mimeType ?? 'image/png');
        $filename = Str::uuid().'.'.$extension;
        $path = 'growth-posts/'.$post->company_id.'/'.$filename;

        Storage::disk('public')->put($path, $result->imageBytes);
        $url = Storage::disk('public')->url($path);

        $media = $post->media_urls ?? [];
        $media[] = $url;

        $post->update([
            'media_urls' => $media,
            'content_type' => $post->content_type === 'text' ? 'image' : $post->content_type,
        ]);

        GrowthLimitService::notifyIfLimitReached($company, 'ai_images');

        return [
            'success' => true,
            'url' => $url,
            'result' => $result,
        ];
    }

    public function buildPosterPrompt(SocialPost $post, Company $company): string
    {
        $platform = $post->platform;
        $aspect = match ($platform) {
            'instagram' => '1:1 square',
            'facebook', 'linkedin' => '4:5 portrait or 1:1 square',
            'whatsapp' => '16:9 landscape friendly for mobile chat',
            'tiktok' => '9:16 vertical',
            default => '1:1 square',
        };

        $hashtags = collect($post->hashtags ?? [])
            ->map(fn ($h) => str_starts_with($h, '#') ? $h : "#{$h}")
            ->take(5)
            ->implode(' ');

        $headline = $post->title ?: Str::limit($post->content, 80);
        $body = Str::limit($post->content, 280);

        return <<<PROMPT
Create a professional social media poster image for "{$company->name}".

Platform: {$platform}
Aspect ratio: {$aspect}

Headline text on poster (large, readable): {$headline}
Supporting text (smaller): {$body}
Optional hashtags (small): {$hashtags}

Style: modern, clean, high-contrast typography, brand-friendly colors, no watermarks except subtle SynthID.
Do not include fake logos of other brands. Make text legible on mobile screens.
PROMPT;
    }

    /**
     * @return array{success: bool, error: string, result: GeminiImageResult}
     */
    private function fail(string $message, GeminiImageResult $result): array
    {
        return [
            'success' => false,
            'error' => $message,
            'result' => $result,
        ];
    }

    private function emptyResult(): GeminiImageResult
    {
        return new GeminiImageResult(
            imageBytes: null,
            mimeType: null,
            success: false,
            model: config('gemini.image_model', 'gemini-2.5-flash-image'),
        );
    }

    private function extensionForMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        };
    }
}
