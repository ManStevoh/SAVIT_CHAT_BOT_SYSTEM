<?php

namespace App\Services\Logs;

class LogDataScrubber
{
    /**
     * Regex patterns for sensitive values to redact.
     *
     * @var array<string, string>
     */
    private static array $patterns = [
        // OpenAI / DeepSeek / API keys
        '/(sk-[a-zA-Z0-9_\-]{20,})/i' => '[REDACTED_API_KEY]',
        // Bearer tokens
        '/(Bearer\s+)[a-zA-Z0-9_\-\.]{20,}/i' => '$1[REDACTED_BEARER_TOKEN]',
        // Passwords in JSON or key-value format
        '/("(?:password|secret|app_secret|client_secret|access_token|private_key)"\s*:\s*")[^"]+(")/i' => '$1[REDACTED]$2',
        '/((?:password|secret|app_secret|client_secret|access_token|private_key)\s*=\s*)[^\s&]+/i' => '$1[REDACTED]',
        // Stripe keys
        '/(sk_live_[a-zA-Z0-9]{24,})/i' => '[REDACTED_STRIPE_SECRET]',
        '/(rk_live_[a-zA-Z0-9]{24,})/i' => '[REDACTED_STRIPE_KEY]',
        // WhatsApp Meta App Secret / Access Token
        '/(EAAG[a-zA-Z0-9]{50,})/i' => '[REDACTED_META_TOKEN]',
        // Credit card numbers (standard 13-19 digits)
        '/\b(?:\d[ -]*?){13,19}\b/' => '[REDACTED_CARD_NUMBER]',
    ];

    /**
     * Scrub sensitive information from a raw log string or array.
     */
    public static function scrub(string $content): string
    {
        if ($content === '') {
            return '';
        }

        foreach (self::$patterns as $pattern => $replacement) {
            $content = (string) preg_replace($pattern, $replacement, $content);
        }

        return $content;
    }

    /**
     * Recursively scrub sensitive information from structured array data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function scrubArray(array $data): array
    {
        $sensitiveKeys = [
            'password', 'password_confirmation', 'secret', 'app_secret', 'client_secret',
            'token', 'access_token', 'private_key', 'authorization', 'deploy_secret',
            'deploy_agent_key', 'meta_app_secret', 'api_key', 'stripe_secret',
        ];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::scrubArray($value);
            } elseif (is_string($value)) {
                if (in_array(strtolower((string) $key), $sensitiveKeys, true)) {
                    $data[$key] = '[REDACTED]';
                } else {
                    $data[$key] = self::scrub($value);
                }
            }
        }

        return $data;
    }
}
