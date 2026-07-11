<?php

namespace App\Services\Agent\Voice;

use App\Models\Company;
use App\Models\Message;
use App\Services\AI\AiGateway;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Transcribe WhatsApp voice notes via the AI orchestration layer (Whisper / STT slot).
 */
final class VoiceTranscriptionService
{
    public function __construct(
        protected AiGateway $gateway,
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

        try {
            $result = $this->gateway->transcribeAudio($path, basename($path), $company);
            if (! $result->success) {
                Log::info('Voice transcription failed', ['error' => $result->error]);

                return null;
            }

            $text = trim((string) $result->text);

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
