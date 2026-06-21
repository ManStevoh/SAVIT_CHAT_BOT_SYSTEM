<?php

namespace App\Logging;

use Illuminate\Support\Facades\Log;

/**
 * Structured logs for the WhatsApp → queue → AI → send pipeline.
 * Grep logs for the message key {@see self::KEY} or use channel filtering if configured.
 */
class WhatsAppPipelineLog
{
    public const KEY = 'whatsapp_pipeline';

    /**
     * @param  array<string, mixed>  $context
     */
    public static function info(string $stage, array $context = []): void
    {
        Log::info(self::KEY, array_merge(['stage' => $stage], $context));
    }

    public static function phoneLast4(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }
        $digits = preg_replace('/\D/', '', $phone);
        if ($digits === '') {
            return null;
        }

        return strlen($digits) >= 4 ? substr($digits, -4) : $digits;
    }

    public static function messagePreview(?string $text, int $max = 160): ?string
    {
        if ($text === null || $text === '') {
            return null;
        }
        $t = trim($text);
        if (mb_strlen($t) <= $max) {
            return $t;
        }

        return mb_substr($t, 0, $max).'…';
    }
}
