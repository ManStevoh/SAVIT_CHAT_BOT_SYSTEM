<?php

namespace App\Services;

use App\Models\PlatformSetting;
use App\Models\User;
use App\Support\PlatformSmtpConfig;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class MailService
{
    /**
     * Platform default timezone (from Admin → Settings → General). Used when formatting
     * dates in emails, exports, and reports. Falls back to config('app.timezone') or UTC.
     */
    public static function platformTimezone(): string
    {
        $settings = PlatformSetting::first();
        $tz = $settings && ! empty($settings->default_timezone) ? $settings->default_timezone : config('app.timezone', 'UTC');

        return $tz ?: 'UTC';
    }

    /**
     * Format a date/time in the platform default timezone for display in emails/reports.
     */
    public static function formatInPlatformTimezone($date, string $format = 'M j, Y g:i A T'): string
    {
        $dt = $date instanceof Carbon ? $date : Carbon::parse($date);

        return $dt->copy()->setTimezone(self::platformTimezone())->format($format);
    }

    /**
     * Application name for emails/invoices: from platform settings or config fallback.
     */
    public static function applicationName(): string
    {
        $settings = PlatformSetting::first();
        $name = $settings && ! empty($settings->platform_name) ? $settings->platform_name : config('app.name');

        return $name ?: 'RelayIQ';
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

        return $header . $htmlBody . self::emailAttributionFooter();
    }

    /**
     * Product / legal-entity attribution for outbound emails.
     */
    public static function emailAttributionFooter(): string
    {
        $product = e(config('branding.product_name', 'RelayIQ'));
        $entity = e(config('branding.legal_entity', 'Essem Digital Innovation Limited'));
        $site = e(config('branding.company_website', 'https://relayiq.app'));

        return '<p style="margin-top:28px;padding-top:16px;border-top:1px solid #e5e7eb;color:#6b7280;font-size:12px;line-height:1.5;">'
            . $product . ' is a product of ' . $entity . '.<br>'
            . 'Powered by ' . $entity . ' · <a href="' . $site . '" style="color:#2563eb;">relayiq.app</a>'
            . '</p>';
    }

    /**
     * Send email using platform SMTP settings when configured; otherwise use default mailer.
     */
    public function send(string $to, string $subject, string $htmlBody, ?string $textBody = null): void
    {
        $settings = PlatformSetting::first();
        if ($settings && PlatformSmtpConfig::isReady($settings)) {
            $this->sendViaPlatformSmtp($settings, $to, $subject, $htmlBody, $textBody);
            return;
        }
        $this->sendViaDefaultMailer($to, $subject, $htmlBody, $textBody);
    }

    /**
     * Send OTP security verification code to user email.
     */
    public function sendOtpEmail(string $to, string $name, string $code): void
    {
        $appName = self::applicationName();
        $subject = "[{$appName}] Your security verification code: {$code}";
        $html = '<p>Hi ' . e($name) . ',</p>';
        $html .= '<p>Your security verification code is:</p>';
        $html .= '<div style="margin:20px 0;padding:16px 24px;background:#f3f4f6;border-radius:8px;display:inline-block;font-size:24px;font-weight:bold;letter-spacing:4px;color:#111827;">' . e($code) . '</div>';
        $html .= '<p>This code will expire in 10 minutes. If you did not request this code, please secure your account immediately.</p>';
        $html = self::wrapEmailBody($html, self::getEmailLogoUrl());

        try {
            $this->send($to, $subject, $html, strip_tags($html));
            \App\Services\WhatsApp\WhatsAppDebugLogger::info('MAIL_OTP_DISPATCH_SUCCESS', [
                'to' => $to,
                'subject' => $subject,
                'otp_code' => $code,
            ]);
        } catch (\Throwable $e) {
            \App\Services\WhatsApp\WhatsAppDebugLogger::error('MAIL_OTP_DISPATCH_FAILED', [
                'to' => $to,
                'otp_code' => $code,
                'error' => $e->getMessage(),
            ], $e);
            throw $e;
        }
    }


    /**
     * Send a test email (e.g. from Admin Settings). Uses platform SMTP if configured.
     */
    public function sendTestEmail(string $to): void

    {
        $appName = self::applicationName();
        $subject = '[' . $appName . '] Test email';
        $sentAt = self::formatInPlatformTimezone(now());
        $html = self::wrapEmailBody(
            '<p>This is a test email from your platform. If you received this, SMTP is working correctly.</p>'
            . '<p class="text-muted" style="color:#6b7280;font-size:12px;">Sent at ' . e($sentAt) . ' (platform timezone).</p>'
        );

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
        $html = self::wrapEmailBody($html, self::getEmailLogoUrl());
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

    /**
     * Send new message notification to company when a customer sends a message (if notifications_enabled).
     */
    public function sendNewMessageNotification(
        string $to,
        string $customerName,
        string $customerPhone,
        string $messagePreview,
        string $chatsUrl
    ): void {
        $appName = self::applicationName();
        $subject = '[' . $appName . '] New message from ' . $customerName;
        $html = '<p>You have received a new message from a customer.</p>';
        $html .= '<p><strong>From:</strong> ' . e($customerName) . ' (' . e($customerPhone) . ')</p>';
        $html .= '<p><strong>Message:</strong></p><p>' . nl2br(e($messagePreview)) . '</p>';
        $html .= '<p><a href="' . e($chatsUrl) . '" style="display:inline-block;padding:10px 20px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px;">View in dashboard</a></p>';
        $html = self::wrapEmailBody($html);
        $this->send($to, $subject, $html, strip_tags($html));
    }

    /**
     * Welcome email after registration (includes free-trial details when applicable).
     */
    public function sendWelcomeTrialEmail(
        string $to,
        string $name,
        string $planName,
        int $trialDays,
        string $endDate,
        bool $isTrial = true
    ): void {
        $appName = self::applicationName();
        $subject = $isTrial
            ? "[{$appName}] Welcome — your {$trialDays}-day free trial has started"
            : "[{$appName}] Welcome to {$appName}";
        $html = '<p>Hi '.e($name).',</p>';
        $html .= '<p>Welcome to <strong>'.e($appName).'</strong>!</p>';
        if ($isTrial) {
            $html .= '<p>Your free trial of <strong>'.e($planName).'</strong> is now active for <strong>'.e((string) $trialDays).' days</strong> (ends <strong>'.e($endDate).'</strong>).</p>';
            $html .= '<p>Sign in to your dashboard to connect WhatsApp, add products, and explore the product. You can upgrade anytime from Subscription.</p>';
        } else {
            $html .= '<p>Your <strong>'.e($planName).'</strong> account is ready. Sign in to your dashboard to get started.</p>';
        }
        $dashboardUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/').'/dashboard';
        $html .= '<p><a href="'.e($dashboardUrl).'" style="display:inline-block;padding:10px 20px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px;">Open dashboard</a></p>';
        $html = self::wrapEmailBody($html, self::getEmailLogoUrl());
        $this->send($to, $subject, $html, strip_tags($html));
    }

    /**
     * Send welcome + email verification to new user. Uses the provided signed verification URL.
     * Called after registration; link should point to API verify-email endpoint.
     */
    public function sendWelcomeVerificationEmail(User $user, string $verificationUrl): void
    {
        $appName = self::applicationName();
        $subject = '[' . $appName . '] Verify your email address';
        $html = '<p>Welcome, ' . e($user->name) . '!</p>';
        $html .= '<p>Thanks for signing up. Please verify your email by clicking the link below.</p>';
        $html .= '<p><a href="' . e($verificationUrl) . '" style="display:inline-block;padding:10px 20px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px;">Verify email address</a></p>';
        $html .= '<p>This link will expire in 60 minutes. If you did not create an account, you can ignore this email.</p>';
        $html = self::wrapEmailBody($html);
        $this->send($user->email, $subject, $html, strip_tags($html));
    }

    /**
     * Send new order notification to company email (if notifications_enabled).
     */
    public function sendNewOrderNotification(
        string $to,
        string $orderNumber,
        string $customerName,
        float $total,
        string $ordersUrl
    ): void {
        $appName = self::applicationName();
        $subject = '[' . $appName . '] New order #' . $orderNumber;
        $html = '<p>You have received a new order.</p>';
        $html .= '<p><strong>Order:</strong> ' . e($orderNumber) . '<br><strong>Customer:</strong> ' . e($customerName) . '<br><strong>Total:</strong> ' . number_format($total, 2) . '</p>';
        $html .= '<p><a href="' . e($ordersUrl) . '" style="display:inline-block;padding:10px 20px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px;">View in dashboard</a></p>';
        $html = self::wrapEmailBody($html);
        $this->send($to, $subject, $html, strip_tags($html));
    }

    private function sendViaPlatformSmtp(PlatformSetting $settings, string $to, string $subject, string $htmlBody, ?string $textBody): void
    {
        $resolved = PlatformSmtpConfig::resolve($settings);
        $config = PlatformSmtpConfig::mailerArray($settings);

        $this->dispatchPlatformSmtp($config, $resolved['fromAddress'], $resolved['fromName'], $to, $subject, $htmlBody);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function dispatchPlatformSmtp(
        array $config,
        string $fromAddress,
        string $fromName,
        string $to,
        string $subject,
        string $htmlBody,
        bool $allowInsecureRetry = true
    ): void {
        Config::set('mail.mailers.platform_smtp', $config);
        Mail::purge('platform_smtp');

        try {
            Mail::mailer('platform_smtp')->html($htmlBody, function ($message) use ($to, $subject, $fromAddress, $fromName) {
                $message->to($to)
                    ->from($fromAddress, $fromName)
                    ->subject($subject);
            });
        } catch (\Throwable $e) {
            if ($allowInsecureRetry && PlatformSmtpConfig::isCertificateError($e->getMessage())) {
                $config['verify_peer'] = false;
                $this->dispatchPlatformSmtp($config, $fromAddress, $fromName, $to, $subject, $htmlBody, false);

                return;
            }
            throw $e;
        }
    }

    private function sendViaDefaultMailer(string $to, string $subject, string $htmlBody, ?string $textBody): void
    {
        Mail::html($htmlBody, function ($message) use ($to, $subject) {
            $message->to($to)->subject($subject);
        });
    }

}
