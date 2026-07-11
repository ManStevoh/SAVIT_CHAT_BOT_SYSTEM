<?php

namespace App\Services\Agent\Voice;

use App\Models\AiModel;
use App\Models\Company;
use App\Models\Message;
use App\Services\AI\AiDriverFactory;
use App\Services\AI\AiModelResolver;
use App\Services\AI\Drivers\OpenAiDriver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Transcribe WhatsApp voice notes via OpenAI-compatible Whisper API.
 */
final class VoiceTranscriptionService
{
    public function __construct(
        protected AiModelResolver $resolver,
        protected AiDriverFactory $driverFactory,
    ) {}

    public function transcribeMessage(Message $message, Company $company): ?string
    {
        if (! config('agent.voice.enabled', true)) {
            return null;
        }

        $url = $message->attachment_url;
        if (empty($url)) {
            return null;
        }

        $mime = (string) ($message->attachment_mime ?? '');
        $isAudio = str_starts_with($mime, 'audio/')
            || str_contains($mime, 'ogg')
            || str_contains($mime, 'mpeg')
            || $message->message_type === 'audio';

        if (! $isAudio && ! $this->looksLikeAudioPlaceholder((string) $message->content)) {
            return null;
        }

        $path = $this->localPathFromUrl((string) $url);
        if ($path === null || ! is_readable($path)) {
            return null;
        }

        $resolved = $this->resolver->resolve($company, AiModel::CAPABILITY_CHAT);
        if ($resolved === null) {
            return null;
        }

        $base = rtrim($resolved->apiBaseUrl ?: $resolved->provider->api_base_url ?: 'https://api.openai.com/v1', '/');

        try {
            $response = Http::withToken($resolved->apiKey)
                ->timeout(60)
                ->attach('file', file_get_contents($path), basename($path))
                ->post("{$base}/audio/transcriptions", [
                    'model' => config('agent.voice.whisper_model', 'whisper-1'),
                    'response_format' => 'text',
                ]);

            if (! $response->successful()) {
                Log::info('Voice transcription failed', ['status' => $response->status()]);

                return null;
            }

            $text = trim($response->body());

            return $text !== '' ? $text : null;
        } catch (\Throwable $e) {
            Log::warning('Voice transcription error', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function looksLikeAudioPlaceholder(string $content): bool
    {
        return str_contains($content, '[audio received]');
    }

    private function localPathFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (str_contains($path, '/storage/')) {
            $relative = ltrim(substr($path, strpos($path, '/storage/') + strlen('/storage/')), '/');

            return Storage::disk('public')->path($relative);
        }

        return null;
    }
}
