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
        $company->loadMissing('settings');
        $voiceEnabled = (bool) ($company->settings?->agent_voice_reply_enabled ?? config('agent.voice.enabled', true));
        if (! $voiceEnabled) {
            \App\Services\WhatsApp\WhatsAppDebugLogger::info('VOICE_TRANSCRIPTION_DISABLED_FOR_COMPANY', ['company_id' => $company->id]);

            return null;
        }

        $url = $message->attachment_url;
        if (empty($url)) {
            \App\Services\WhatsApp\WhatsAppDebugLogger::info('VOICE_TRANSCRIPTION_MISSING_URL', ['message_id' => $message->id]);

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
            \App\Services\WhatsApp\WhatsAppDebugLogger::warning('VOICE_TRANSCRIPTION_PATH_UNREADABLE', [
                'message_id' => $message->id,
                'attachment_url' => $url,
                'resolved_path' => $path,
            ]);

            return null;
        }

        \App\Services\WhatsApp\WhatsAppDebugLogger::info('VOICE_TRANSCRIPTION_START', [
            'company_id' => $company->id,
            'message_id' => $message->id,
            'audio_path' => $path,
        ]);

        $promptHint = $this->buildPromptHint($company);

        try {
            $result = $this->gateway->transcribeAudio($path, basename($path), $company, $promptHint);
            if (! $result->success) {
                \App\Services\WhatsApp\WhatsAppDebugLogger::error('VOICE_TRANSCRIPTION_FAILED', [
                    'message_id' => $message->id,
                    'error' => $result->error,
                ]);
                Log::info('Voice transcription failed', ['error' => $result->error]);

                return null;
            }

            $text = trim((string) $result->text);
            if ($text !== '') {
                \App\Services\WhatsApp\WhatsAppDebugLogger::info('VOICE_TRANSCRIPTION_SUCCESS', [
                    'message_id' => $message->id,
                    'transcript_preview' => mb_substr($text, 0, 150),
                ]);
                try {
                    $message->update(['voice_transcript' => $text]);
                } catch (\Throwable $e) {
                    // Soft fallback if database migration hasn't been run yet
                }

                return $text;
            }

            return null;
        } catch (\Throwable $e) {
            \App\Services\WhatsApp\WhatsAppDebugLogger::error('VOICE_TRANSCRIPTION_EXCEPTION', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ], $e);
            Log::warning('Voice transcription error', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function buildPromptHint(Company $company): string
    {
        $hints = [$company->name];
        $currency = $company->settings?->displayCurrencyCode() ?? 'KES';
        $hints[] = "Currency: {$currency}, KSh, M-Pesa, PayBill, Till";

        try {
            $productNames = \App\Models\Product::where('company_id', $company->id)
                ->where('is_active', true)
                ->limit(10)
                ->pluck('name')
                ->toArray();
            if (! empty($productNames)) {
                $hints[] = 'Products: '.implode(', ', $productNames);
            }
        } catch (\Throwable $e) {
            // Ignore catalog fetch failure for prompt hint
        }

        return implode('. ', $hints);
    }

    private function looksLikeAudioPlaceholder(string $content): bool
    {
        return str_contains($content, '[audio received]');
    }

    private function localPathFromUrl(string $url): ?string
    {
        if (file_exists($url) && is_readable($url)) {
            return $url;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (str_contains($path, '/storage/')) {
            $relative = ltrim(substr($path, strpos($path, '/storage/') + strlen('/storage/')), '/');
            $full = Storage::disk('public')->path($relative);
            if (file_exists($full) && is_readable($full)) {
                return $full;
            }
        }

        $publicFile = public_path(ltrim($path, '/'));
        if (file_exists($publicFile) && is_readable($publicFile)) {
            return $publicFile;
        }

        $storageFile = storage_path('app/public/'.ltrim($path, '/'));
        if (file_exists($storageFile) && is_readable($storageFile)) {
            return $storageFile;
        }

        return null;
    }
}
