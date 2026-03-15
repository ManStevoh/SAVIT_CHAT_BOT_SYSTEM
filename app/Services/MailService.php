<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class MailService
{
    /**
     * Application name for emails/invoices: from platform settings or config fallback.
     */
    public static function applicationName(): string
    {
        $settings = PlatformSetting::first();
        $name = $settings && ! empty($settings->platform_name) ? $settings->platform_name : config('app.name');

        return $name ?: 'SAVIT';
    }

    /**
     * Absolute URL for app logo (for email headers). Returns null if not set.
     */
    public static function getEmailLogoUrl(): ?string
    {
        $settings = PlatformSetting::first();
        if (! $settings || empty($settings->app_logo)) {
            return null;
        }
        if (! Storage::disk('public')->exists($settings->app_logo)) {
            return null;
        }

        return asset('storage/' . $settings->app_logo);
    }

    /**
     * Wrap HTML body with optional logo header (for emails). Logo URL must be absolute.
     */
    public static function wrapEmailBody(string $htmlBody, ?string $logoUrl = null): string
    {
        $url = $logoUrl ?? self::getEmailLogoUrl();
        $header = '';
        if ($url) {
            $header = '<div style="margin-bottom:20px;"><img src="' . e($url) . '" alt="Logo" style="max-height:48px;max-width:200px;" /></div>';
        }

        return $header . $htmlBody;
    }

    /**
     * Send email using platform SMTP settings when configured; otherwise use default mailer.
     */
    public function send(string $to, string $subject, string $htmlBody, ?string $textBody = null): void
    {
        $settings = PlatformSetting::first();
        if ($settings && $settings->hasSmtpConfigured()) {
            $this->sendViaPlatformSmtp($settings, $to, $subject, $htmlBody, $textBody);
            return;
        }
        $this->sendViaDefaultMailer($to, $subject, $htmlBody, $textBody);
    }

    /**
     * Send a test email (e.g. from Admin Settings). Uses platform SMTP if configured.
     */
    public function sendTestEmail(string $to): void
    {
        $appName = self::applicationName();
        $subject = '[' . $appName . '] Test email';
        $html = self::wrapEmailBody('<p>This is a test email from your platform. If you received this, SMTP is working correctly.</p>');

        $this->send($to, $subject, $html, strip_tags($html));
    }

    /**
     * Send subscription confirmed email after checkout.
     */
    public function sendSubscriptionConfirmed(string $to, string $planName, string $endDate): void
    {
        $appName = self::applicationName();
        $subject = "[{$appName}] Your subscription is active";
        $html = '<p>Your subscription to <strong>' . e($planName) . '</strong> is now active.</p>';
        $html .= '<p>Your current period ends on <strong>' . e($endDate) . '</strong>. You can manage your subscription and view invoices in your dashboard.</p>';
        $html .= '<p>Thank you for your business.</p>';
        $html = self::wrapEmailBody($html, $this->emailLogoUrl());
        $this->send($to, $subject, $html, strip_tags($html));
    }

    /**
     * Send payment received / invoice paid email.
     */
    public function sendInvoicePaid(string $to, string $invoiceId, float $amount, string $date): void
    {
        $appName = self::applicationName();
        $subject = "[{$appName}] Payment received – Invoice {$invoiceId}";
        $html = '<p>We have received your payment.</p>';
        $html .= '<p><strong>Invoice:</strong> ' . e($invoiceId) . '<br><strong>Amount:</strong> $' . number_format($amount, 2) . '<br><strong>Date:</strong> ' . e($date) . '</p>';
        $html .= '<p>You can view and download your invoices in your dashboard under Subscription → Billing History.</p>';
        $html = self::wrapEmailBody($html);
        $this->send($to, $subject, $html, strip_tags($html));
    }

    /**
     * Send subscription expiring soon reminder.
     */
    public function sendSubscriptionExpiringSoon(string $to, string $planName, string $endDate, int $daysLeft): void
    {
        $appName = self::applicationName();
        $subject = "[{$appName}] Your subscription expires in {$daysLeft} " . ($daysLeft === 1 ? 'day' : 'days');
        $html = '<p>This is a reminder that your <strong>' . e($planName) . '</strong> subscription will end on <strong>' . e($endDate) . '</strong>.</p>';
        $html .= '<p>To avoid any interruption, please renew or update your subscription in your dashboard (Subscription → Manage billing).</p>';
        $html = self::wrapEmailBody($html);
        $this->send($to, $subject, $html, strip_tags($html));
    }

    private function sendViaPlatformSmtp(PlatformSetting $settings, string $to, string $subject, string $htmlBody, ?string $textBody): void
    {
        $config = [
            'transport' => 'smtp',
            'host' => $settings->smtp_host,
            'port' => (int) ($settings->smtp_port ?: 587),
            'encryption' => $this->normalizeEncryption($settings->smtp_encryption),
            'username' => $settings->smtp_username,
            'password' => $settings->smtp_password,
            'timeout' => null,
        ];
        $fromAddress = $settings->mail_from_address ?: config('mail.from.address');
        $fromName = $settings->mail_from_name ?: config('mail.from.name');

        Config::set('mail.mailers.platform_smtp', $config);
        Mail::mailer('platform_smtp')->html($htmlBody, function ($message) use ($to, $subject, $fromAddress, $fromName) {
            $message->to($to)
                ->from($fromAddress, $fromName)
                ->subject($subject);
        });
    }

    private function sendViaDefaultMailer(string $to, string $subject, string $htmlBody, ?string $textBody): void
    {
        Mail::html($htmlBody, function ($message) use ($to, $subject) {
            $message->to($to)->subject($subject);
        });
    }

    private function normalizeEncryption(?string $encryption): ?string
    {
        if (empty($encryption) || strtolower($encryption) === 'none') {
            return null;
        }
        return strtolower($encryption) === 'ssl' ? 'ssl' : 'tls';
    }
}
