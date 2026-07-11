<?php

namespace App\Services\Agent\Voice;

use App\Models\Company;
use App\Models\WhatsAppAccount;
use App\Services\AI\AiGateway;
use App\Services\WhatsAppMessageSenderService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * TTS outbound — synthesize agent reply audio and send via WhatsApp.
 */
final class VoiceOutboundService
{
    public function __construct(
        protected AiGateway $gateway,
        protected WhatsAppMessageSenderService $waSender,
    ) {}

    public function shouldReplyWithVoice(Company $company, bool $inboundWasAudio): bool
    {
        if (! $inboundWasAudio || ! config('agent.voice.enabled', true)) {
            return false;
        }

        $company->loadMissing('settings');

        return (bool) ($company->settings?->agent_voice_reply_enabled ?? false);
    }

    /**
     * @return array{success: bool, message_id?: string, error?: string}
     */
    public function sendVoiceReply(WhatsAppAccount $account, Company $company, string $to, string $text): array
    {
        $plain = $this->plainTextForSpeech($text);
        if ($plain === '') {
            return ['success' => false, 'error' => 'Empty speech text'];
        }

        $result = $this->gateway->synthesizeSpeech($plain, $company);
        if (! $result->success || ! $result->audioPath || ! is_readable($result->audioPath)) {
            return ['success' => false, 'error' => $result->error ?? 'TTS synthesis failed'];
        }

        try {
            $send = $this->waSender->sendAudioFile(
                $account,
                $to,
                $result->audioPath,
                $result->mimeType ?? 'audio/mpeg',
            );

            return $send;
        } finally {
            if (isset($result->audioPath) && str_starts_with($result->audioPath, sys_get_temp_dir())) {
                @unlink($result->audioPath);
            }
        }
    }

    private function plainTextForSpeech(string $text): string
    {
        $text = preg_replace('/\*\*(.+?)\*\*/s', '$1', $text) ?? $text;
        $text = preg_replace('/\*(.+?)\*/s', '$1', $text) ?? $text;
        $text = preg_replace('/https?:\/\S+/i', '', $text) ?? $text;
        $text = preg_replace('/\n{2,}/', '. ', $text) ?? $text;
        $text = str_replace("\n", ' ', $text);

        return trim(mb_substr($text, 0, (int) config('agent.voice.tts_max_chars', 800)));
    }
}
