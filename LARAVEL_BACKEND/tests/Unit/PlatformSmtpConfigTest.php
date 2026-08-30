<?php

namespace Tests\Unit;

use App\Models\PlatformSetting;
use App\Support\PlatformSmtpConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlatformSmtpConfigTest extends TestCase
{
    public static function transportProvider(): array
    {
        return [
            'cpanel implicit ssl' => [465, 'ssl', 'smtps', 465],
            'submission starttls' => [587, 'tls', 'smtp', 587],
            'ssl without port' => [null, 'ssl', 'smtps', 465],
            'tls without port' => [null, 'tls', 'smtp', 587],
            'port 465 wins over tls label' => [465, 'tls', 'smtps', 465],
            'typo 456 becomes 465' => [456, 'ssl', 'smtps', 465],
        ];
    }

    #[DataProvider('transportProvider')]
    public function test_it_maps_admin_fields_to_laravel_12_scheme(?int $port, string $encryption, string $scheme, int $expectedPort): void
    {
        $settings = new PlatformSetting([
            'smtp_host' => 'mail.relayiq.app',
            'smtp_port' => $port,
            'smtp_encryption' => $encryption,
            'smtp_username' => 'info@relayiq.app',
            'mail_from_address' => 'info@relayiq.app',
            'mail_from_name' => 'RelayIQ',
        ]);
        $settings->smtp_password = 'secret-mailbox-password';

        $resolved = PlatformSmtpConfig::resolve($settings);
        $mailer = PlatformSmtpConfig::mailerArray($settings);

        $this->assertSame('mail.relayiq.app', $resolved['host']);
        $this->assertSame($expectedPort, $resolved['port']);
        $this->assertSame($scheme, $resolved['scheme']);
        $this->assertSame($scheme, $mailer['scheme']);
        $this->assertSame($expectedPort, $mailer['port']);
        $this->assertArrayNotHasKey('encryption', $mailer);
        $this->assertTrue(PlatformSmtpConfig::isReady($settings));
    }

    public function test_it_is_not_ready_without_password(): void
    {
        $settings = new PlatformSetting([
            'smtp_host' => 'mail.relayiq.app',
            'smtp_username' => 'info@relayiq.app',
            'mail_from_address' => 'info@relayiq.app',
        ]);

        $this->assertFalse(PlatformSmtpConfig::isReady($settings));
    }

    public function test_public_error_explains_auth_failures(): void
    {
        $message = PlatformSmtpConfig::publicError('Expected response code 535 but got code "535", with message "Authentication failed"');
        $this->assertStringContainsString('username or password', $message);
    }
}
