<?php

namespace App\Support;

use App\Models\PlatformSetting;

/**
 * Builds a Laravel 12 Symfony SMTP mailer array from Admin → Email settings.
 * Laravel 12 uses `scheme` (smtp / smtps), not the old `encryption` key.
 */
class PlatformSmtpConfig
{
    /**
     * @return array<string, mixed>
     */
    public static function mailerArray(PlatformSetting $settings): array
    {
        $resolved = self::resolve($settings);

        return [
            'transport' => 'smtp',
            'scheme' => $resolved['scheme'],
            'host' => $resolved['host'],
            'port' => $resolved['port'],
            'username' => $resolved['username'],
            'password' => $resolved['password'],
            'timeout' => 25,
            'local_domain' => parse_url((string) config('app.url', 'https://relayiq.app'), PHP_URL_HOST) ?: 'relayiq.app',
        ];
    }

    /**
     * @return array{host: string, port: int, scheme: string, username: string, password: string, fromAddress: string, fromName: string}
     */
    public static function resolve(PlatformSetting $settings): array
    {
        $encryption = strtolower(trim((string) ($settings->smtp_encryption ?: '')));
        $port = (int) ($settings->smtp_port ?: 0);

        // Production has repeatedly stored 456 (typo). That port is closed; 465 is implicit SSL.
        if ($port === 456) {
            $port = 465;
        }

        if ($port <= 0) {
            $port = $encryption === 'ssl' ? 465 : 587;
        }

        $scheme = match (true) {
            $encryption === 'ssl', $port === 465 => 'smtps',
            default => 'smtp',
        };

        return [
            'host' => trim((string) $settings->smtp_host),
            'port' => $port,
            'scheme' => $scheme,
            'username' => trim((string) $settings->smtp_username),
            'password' => (string) ($settings->getAttributes()['smtp_password'] ?? ''),
            'fromAddress' => trim((string) ($settings->mail_from_address ?: config('mail.from.address'))),
            'fromName' => trim((string) ($settings->mail_from_name ?: config('mail.from.name') ?: 'RelayIQ')),
        ];
    }

    public static function isReady(PlatformSetting $settings): bool
    {
        $resolved = self::resolve($settings);

        return $resolved['host'] !== ''
            && $resolved['username'] !== ''
            && $resolved['password'] !== ''
            && $resolved['fromAddress'] !== '';
    }

    public static function isCertificateError(string $message): bool
    {
        $lower = strtolower($message);

        return str_contains($lower, 'certificate')
            || str_contains($lower, 'ssl operation failed')
            || str_contains($lower, 'unable to connect with starttls')
            || str_contains($lower, 'peer certificate');
    }

    public static function publicError(string $message): string
    {
        $safe = preg_replace('/:[^:\s\/]+@/', ':***@', $message) ?? $message;
        $lower = strtolower($safe);

        if (str_contains($lower, 'authenticate') || str_contains($lower, '535') || str_contains($lower, '534') || str_contains($lower, 'invalid login')) {
            return 'SMTP rejected the username or password. Use the mailbox password for that address, click Save Email Settings, then test again.';
        }
        if (str_contains($lower, 'connection refused') || str_contains($lower, 'timed out') || str_contains($lower, 'timed-out') || str_contains($lower, 'network is unreachable')) {
            return 'Could not reach the mail server. Use host mail.relayiq.app, port 465 with SSL (or 587 with TLS), and confirm the server can open that outbound port.';
        }
        if (self::isCertificateError($safe)) {
            return 'The mail server certificate could not be verified. Keep host as mail.relayiq.app and SSL on port 465. RelayIQ will retry once without peer verification.';
        }

        $trimmed = trim(preg_replace('/\s+/', ' ', $safe) ?? $safe);

        return 'Failed to send test email: '.mb_substr($trimmed, 0, 280);
    }
}
