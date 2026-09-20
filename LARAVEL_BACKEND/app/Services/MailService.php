<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\StorefrontCustomer;
use App\Models\User;
use App\Support\MoneyFormatter;
use App\Support\PlatformSmtpConfig;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
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
    public function send(string $to, string $subject, string $htmlBody, ?string $textBody = null, array $attachments = []): void
    {
        $settings = PlatformSetting::first();
        if ($settings && PlatformSmtpConfig::isReady($settings)) {
            $this->sendViaPlatformSmtp($settings, $to, $subject, $htmlBody, $textBody, $attachments);
            return;
        }
        $this->sendViaDefaultMailer($to, $subject, $htmlBody, $textBody, $attachments);
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
    /**
     * Send new order notification to company owner with actionable confirmation instructions.
     */
    public function sendNewOrderNotification(
        string $to,
        string $orderNumber,
        string $customerName,
        float $total,
        string $ordersUrl,
        ?Order $order = null
    ): void {
        $appName = self::applicationName();
        $storeName = $order?->company?->name ?: $appName;
        $subject = "Action Required: New order #{$orderNumber} from {$customerName} — Confirm to proceed";

        $html = '<div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; line-height: 1.5; color: #1e293b;">';
        $html .= '<h2 style="color:#0f172a; margin-bottom: 8px;">New Order Received!</h2>';
        $html .= '<p style="margin-top:0;">You have received a new purchase for <strong>' . e($storeName) . '</strong>.</p>';

        $html .= '<div style="background-color: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 14px 16px; margin: 18px 0; color: #92400e;">';
        $html .= '<strong style="display:block; font-size: 14px; margin-bottom: 4px;">⚠️ Action Required: Confirm Order to Proceed</strong>';
        $html .= '<span style="font-size: 13px;">Please verify payment and confirm this order in your dashboard so the goods can proceed to the next stage (fulfillment / delivery).</span>';
        $html .= '</div>';

        $html .= '<table style="width:100%; border-collapse: collapse; margin: 16px 0; font-size: 14px;">';
        $html .= '<tr><td style="padding: 6px 0; color: #64748b; width: 140px;">Order Number:</td><td style="padding: 6px 0; font-weight: 600; color: #0f172a;">#' . e($orderNumber) . '</td></tr>';
        $html .= '<tr><td style="padding: 6px 0; color: #64748b;">Customer:</td><td style="padding: 6px 0; font-weight: 600; color: #0f172a;">' . e($customerName) . ($order && $order->customer_phone ? ' (' . e($order->customer_phone) . ')' : '') . '</td></tr>';
        if ($order && $order->customer_email) {
            $html .= '<tr><td style="padding: 6px 0; color: #64748b;">Customer Email:</td><td style="padding: 6px 0; color: #0f172a;">' . e($order->customer_email) . '</td></tr>';
        }
        $formattedTotal = $order && $order->company ? MoneyFormatter::formatCompany($order->company, $total) : number_format($total, 2);
        $html .= '<tr><td style="padding: 6px 0; color: #64748b;">Total Amount:</td><td style="padding: 6px 0; font-weight: 700; color: #0f172a; font-size: 16px;">' . e($formattedTotal) . '</td></tr>';
        if ($order && $order->payment_method) {
            $methodLabel = $order->payment_method === 'manual' ? 'Manual / M-Pesa (Pochi la Biashara)' : ucfirst($order->payment_method);
            $html .= '<tr><td style="padding: 6px 0; color: #64748b;">Payment Method:</td><td style="padding: 6px 0; font-weight: 600; color: #0f172a;">' . e($methodLabel) . '</td></tr>';
        }
        $html .= '</table>';

        if ($order) {
            $html .= $this->orderItemsHtml($order);
        }

        $html .= '<p style="margin-top: 24px;">' . $this->emailButton($ordersUrl, 'Review & Confirm Order in Dashboard') . '</p>';
        $html .= '<p style="font-size: 12px; color: #94a3b8; margin-top: 20px;">Once you confirm the payment in your dashboard, the order status will advance and the customer will receive their confirmation details.</p>';
        $html .= '</div>';

        $html = self::wrapEmailBody($html);
        $this->send($to, $subject, $html, strip_tags($html));
    }

    /**
     * Send notification to owner when customer reports submitting a manual payment (M-Pesa).
     */
    public function sendOwnerManualPaymentSubmittedNotification(
        Order $order,
        string $to,
        ?string $txnCode = null,
        ?string $payingPhone = null
    ): void {
        $appName = self::applicationName();
        $storeName = $order->company?->name ?: $appName;
        $customerName = $order->customer_name ?: 'A customer';
        $orderNumber = (string) $order->order_number;
        $subject = "Payment Submitted: Order #{$orderNumber} by {$customerName} — Verify & Confirm";
        $ordersUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/').'/dashboard/orders';

        $html = '<div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; line-height: 1.5; color: #1e293b;">';
        $html .= '<h2 style="color:#0f172a; margin-bottom: 8px;">Customer Reported Payment!</h2>';
        $html .= '<p style="margin-top:0;"><strong>' . e($customerName) . '</strong> has submitted manual payment for Order <strong>#' . e($orderNumber) . '</strong> on ' . e($storeName) . '.</p>';

        $html .= '<div style="background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 14px 16px; margin: 18px 0; color: #166534;">';
        $html .= '<strong style="display:block; font-size: 14px; margin-bottom: 4px;">✅ Payment Confirmation Required</strong>';
        $html .= '<span style="font-size: 13px;">Please check your M-Pesa messages / bank account for the payment below, then click to confirm the order in your dashboard so goods can proceed to the next stage.</span>';
        $html .= '</div>';

        $html .= '<table style="width:100%; border-collapse: collapse; margin: 16px 0; font-size: 14px;">';
        $html .= '<tr><td style="padding: 6px 0; color: #64748b; width: 150px;">Order Number:</td><td style="padding: 6px 0; font-weight: 600; color: #0f172a;">#' . e($orderNumber) . '</td></tr>';
        $formattedTotal = MoneyFormatter::formatCompany($order->company, (float) $order->total);
        $html .= '<tr><td style="padding: 6px 0; color: #64748b;">Expected Amount:</td><td style="padding: 6px 0; font-weight: 700; color: #0f172a; font-size: 16px;">' . e($formattedTotal) . '</td></tr>';
        if ($txnCode) {
            $html .= '<tr><td style="padding: 6px 0; color: #64748b;">M-Pesa Reference:</td><td style="padding: 6px 0; font-weight: 700; color: #1e40af; font-family: monospace; font-size: 15px;">' . e($txnCode) . '</td></tr>';
        }
        if ($payingPhone) {
            $html .= '<tr><td style="padding: 6px 0; color: #64748b;">Customer Phone:</td><td style="padding: 6px 0; font-weight: 600; color: #0f172a;">' . e($payingPhone) . '</td></tr>';
        }
        $html .= '</table>';

        $html .= $this->orderItemsHtml($order);

        $html .= '<p style="margin-top: 24px;">' . $this->emailButton($ordersUrl, 'Open Orders Dashboard to Confirm') . '</p>';
        $html .= '</div>';

        $html = self::wrapEmailBody($html);
        $this->send($to, $subject, $html, strip_tags($html));
    }

    public function sendCustomerOrderConfirmationSafely(Order $order, bool $attachDigitalPdfs = false): bool
    {
        try {
            $fresh = $order->fresh(['company.settings', 'orderProducts.product']) ?? $order;
            if ($this->customerEmail($fresh) === null) {
                return false;
            }
            $this->sendCustomerOrderConfirmation($fresh, $attachDigitalPdfs);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Failed to send customer order confirmation email', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Email to notify a shopper when WhatsApp is down: submitted address, then storefront or prior orders.
     */
    public function resolveNotifyEmail(?Company $company, ?string $phone, ?string $submitted = null): ?string
    {
        $submitted = strtolower(trim((string) $submitted));
        if ($submitted !== '' && filter_var($submitted, FILTER_VALIDATE_EMAIL)) {
            return $submitted;
        }

        if (! $company) {
            return null;
        }

        $tail = $this->phoneTail($phone);
        if ($tail === '') {
            return null;
        }

        $customers = StorefrontCustomer::query()
            ->where('company_id', $company->id)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereNotNull('phone')
            ->orderByDesc('id')
            ->limit(200)
            ->get(['email', 'phone']);

        foreach ($customers as $customer) {
            if ($this->phoneTail((string) $customer->phone) !== $tail) {
                continue;
            }
            $email = strtolower(trim((string) $customer->email));
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        $orders = Order::query()
            ->where('company_id', $company->id)
            ->whereNotNull('customer_email')
            ->where('customer_email', '!=', '')
            ->orderByDesc('id')
            ->limit(200)
            ->get(['customer_email', 'customer_phone']);

        foreach ($orders as $prior) {
            if ($this->phoneTail((string) $prior->customer_phone) !== $tail) {
                continue;
            }
            $email = strtolower(trim((string) $prior->customer_email));
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        return null;
    }

    private function phoneTail(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        return $digits === '' ? '' : substr($digits, -9);
    }

    public function sendCustomerPaymentFulfillmentSafely(Order $order): bool
    {
        try {
            $fresh = $order->fresh(['company.settings', 'orderProducts.product']) ?? $order;
            if ($this->customerEmail($fresh) === null) {
                return false;
            }
            $this->sendCustomerPaymentFulfillment($fresh);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Failed to send customer payment / download email', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send automatic storefront customer account creation & password setup/reset email.
     */
    public function sendStorefrontCustomerPasswordSetupEmail(
        \App\Models\StorefrontCustomer $customer,
        \App\Models\Company $company,
        string $setupPasswordUrl,
        bool $isReset = false,
        ?\App\Models\Order $order = null
    ): void {
        $to = strtolower(trim((string) $customer->email));
        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $store = trim((string) ($company->name ?? '')) ?: 'the store';
        $name = trim((string) ($customer->name ?? '')) ?: 'there';

        if ($isReset) {
            $subject = 'Reset your password for '.$store;
            $html = '<p>Hi '.e($name).',</p>';
            $html .= '<p>We received a request to set or reset the password for your customer account at <strong>'.e($store).'</strong>.</p>';
            $html .= '<p>Click the secure button below to set your password:</p>';
            $html .= $this->emailButton($setupPasswordUrl, 'Set / Reset Password');
            $html .= '<p class="text-muted" style="color:#6b7280;font-size:12px;">This link is valid for 7 days. If you did not request this, you can safely ignore this email.</p>';
        } else {
            $orderRef = $order ? ' for your order #'.$order->order_number : '';
            $subject = 'Your account at '.$store.$orderRef;
            $html = '<p>Hi '.e($name).',</p>';
            $html .= '<p>Thank you for your purchase from <strong>'.e($store).'</strong>!</p>';
            $html .= '<p>An account has been automatically created for you using <strong>'.e($to).'</strong> so you can easily track your order, view past receipts, and speed up future checkout.</p>';
            $html .= '<p>To access your account and manage your orders, please set a password:</p>';
            $html .= $this->emailButton($setupPasswordUrl, 'Create Your Password');
            $html .= '<p class="text-muted" style="color:#6b7280;font-size:12px;">This secure link is valid for 7 days. You can also request a new link anytime from the store login screen.</p>';
        }

        $this->send($to, $subject, self::wrapEmailBody($html), strip_tags($html));
    }

    /**
     * Confirmation email to the shopper after any purchase (storefront, WhatsApp, or dashboard).
     */
    public function sendCustomerOrderConfirmation(Order $order, bool $attachDigitalPdfs = false): void
    {
        $to = $this->customerEmail($order);
        if ($to === null) {
            return;
        }

        $order->loadMissing(['company.settings', 'orderProducts.product']);
        $store = trim((string) ($order->company?->name ?? '')) ?: 'the store';
        $name = trim((string) ($order->customer_name ?? '')) ?: 'there';
        $total = MoneyFormatter::formatFromSettings((float) $order->total, $order->company?->settings);
        $paid = strtolower((string) $order->payment_status) === 'paid';
        $digitalPdfs = ($attachDigitalPdfs || $paid) ? $this->digitalPdfAttachments($order) : [];
        $subject = 'Your order '.$order->order_number.' from '.$store;
        $html = $this->customerOrderConfirmationHtml($order, $store, $name, $total, $digitalPdfs !== []);
        $this->send($to, $subject, self::wrapEmailBody($html), strip_tags($html), $digitalPdfs);
    }

    /**
     * Payment-received email. Includes signed download / license details for digital items.
     */
    public function sendCustomerPaymentFulfillment(Order $order): void
    {
        $to = $this->customerEmail($order);
        if ($to === null) {
            return;
        }

        $order->loadMissing(['company.settings', 'orderProducts.product']);
        $store = trim((string) ($order->company?->name ?? '')) ?: 'the store';
        $name = trim((string) ($order->customer_name ?? '')) ?: 'there';
        $total = MoneyFormatter::formatFromSettings((float) $order->total, $order->company?->settings);
        $subject = 'Payment received — order '.$order->order_number.' from '.$store;
        $html = $this->customerPaymentFulfillmentHtml($order, $store, $name, $total);
        $this->send(
            $to,
            $subject,
            self::wrapEmailBody($html),
            strip_tags($html),
            $this->digitalPdfAttachments($order)
        );
    }

    private function customerEmail(Order $order): ?string
    {
        $to = strtolower(trim((string) ($order->customer_email ?? '')));
        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $to;
    }

    private function customerOrderConfirmationHtml(Order $order, string $store, string $name, string $total, bool $digitalPdfAttached = false): string
    {
        $html = '<p>Hi '.e($name).',</p>';
        $html .= '<p>Thank you for your order from <strong>'.e($store).'</strong>.</p>';
        $html .= '<p><strong>Order:</strong> '.e((string) $order->order_number).'<br><strong>Total:</strong> '.e($total).'</p>';
        $html .= $this->orderItemsHtml($order);

        $paid = strtolower((string) $order->payment_status) === 'paid';
        if (! $paid) {
            $html .= '<p>Complete payment here:</p>';
            $html .= $this->emailButton($order->publicPayUrl(), 'Pay now');
        }

        $html .= '<p><a href="'.e($order->publicInvoiceUrl()).'">View invoice</a>';
        if ($order->company?->store_slug) {
            $track = rtrim((string) config('app.url'), '/').'/s/'.$order->company->store_slug.'/track';
            $html .= ' · <a href="'.e($track).'">Track order</a>';
        }
        $html .= '</p>';

        if ($digitalPdfAttached) {
            $html .= '<p>Your PDF is attached to this email — save it for offline download.</p>';
        } elseif ($this->orderHasDigitalItems($order) && ! $paid) {
            $html .= '<p>Digital files or license keys will be emailed to this address after payment is confirmed.</p>';
        }

        $html .= '<p>Reply to the store if you need help with this order.</p>';

        return $html;
    }

    private function customerPaymentFulfillmentHtml(Order $order, string $store, string $name, string $total): string
    {
        $html = '<p>Hi '.e($name).',</p>';
        $html .= '<p>Payment received for your order from <strong>'.e($store).'</strong>.</p>';
        $html .= '<p><strong>Order:</strong> '.e((string) $order->order_number).'<br><strong>Total:</strong> '.e($total).'</p>';
        $html .= $this->orderItemsHtml($order);
        $html .= $this->digitalAccessHtml($order);
        if ($this->digitalPdfAttachments($order) !== []) {
            $html .= '<p>The PDF is also attached to this email so you can download it immediately.</p>';
        }
        $html .= '<p>'.$this->emailButton($order->publicReceiptUrl(), 'View receipt').'</p>';
        $html .= '<p>Keep this email for your records.</p>';

        return $html;
    }

    private function orderItemsHtml(Order $order): string
    {
        if ($order->orderProducts->isEmpty()) {
            return '';
        }

        $rows = '';
        foreach ($order->orderProducts as $line) {
            $rows .= '<li>'.e((string) $line->quantity).' × '.e((string) $line->name).'</li>';
        }

        return '<p><strong>Items</strong></p><ul>'.$rows.'</ul>';
    }


    private function digitalAccessHtml(Order $order): string
    {
        $items = $order->receiptFulfillmentItems();
        if ($items === []) {
            return '';
        }

        $html = '<p><strong>Your digital downloads</strong></p>';
        foreach ($items as $item) {
            $html .= '<p>'.e((string) ($item['name'] ?? 'Digital item')).'</p>';
            if (! empty($item['instructions'])) {
                $html .= '<p>'.nl2br(e((string) $item['instructions'])).'</p>';
            }
            if (! empty($item['fileUrl'])) {
                $label = ! empty($item['fileName']) ? 'Download '.((string) $item['fileName']) : 'Download file';
                $html .= $this->emailButton((string) $item['fileUrl'], $label);
            }
            if (! empty($item['accessUrl'])) {
                $html .= $this->emailButton((string) $item['accessUrl'], 'Open access link');
            }
            if (! empty($item['bookingUrl'])) {
                $html .= $this->emailButton((string) $item['bookingUrl'], 'Book now');
            }
            $keys = is_array($item['licenseKeys'] ?? null) ? $item['licenseKeys'] : [];
            if ($keys !== []) {
                $html .= '<p>License key(s): <strong>'.e(implode(', ', array_map('strval', $keys))).'</strong></p>';
            }
        }

        $portal = app(DigitalAccessService::class)->signedAccessPortalUrl($order);
        $html .= '<p>You can also open your access portal anytime:</p>';
        $html .= $this->emailButton($portal, 'Open access portal');

        return $html;
    }

    private function emailButton(string $url, string $label): string
    {
        return '<p><a href="'.e($url).'" style="display:inline-block;padding:10px 20px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px;">'.e($label).'</a></p>';
    }

    /**
     * @return list<array{path: string, name: string, mime: string}>
     */
    public function digitalPdfAttachmentsFor(Order $order): array
    {
        return $this->digitalPdfAttachments($order);
    }

    public function orderHasDigitalItems(Order $order): bool
    {
        $order->loadMissing('orderProducts.product');
        $access = app(DigitalAccessService::class);
        foreach ($order->orderProducts as $line) {
            $data = is_array($line->fulfillment_data) ? $line->fulfillment_data : [];
            if ($access->isDigitalFulfillment($data, $line->product)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{path: string, name: string, mime: string}>
     */
    private function digitalPdfAttachments(Order $order): array
    {
        $order->loadMissing('orderProducts.product');
        $out = [];
        foreach ($order->orderProducts as $line) {
            $data = is_array($line->fulfillment_data) ? $line->fulfillment_data : [];
            $path = (string) ($data['digitalFilePath'] ?? $line->product?->digital_file_path ?? '');
            $name = (string) ($data['digitalFileName'] ?? $line->product?->digital_file_name ?? '');
            $mime = strtolower((string) ($data['digitalFileMime'] ?? $line->product?->digital_file_mime ?? ''));
            if ($path === '') {
                continue;
            }
            $isPdf = str_contains($mime, 'pdf')
                || str_ends_with(strtolower($name), '.pdf')
                || str_ends_with(strtolower($path), '.pdf');
            if (! $isPdf) {
                continue;
            }
            $absolute = $this->resolveStoredFilePath($path);
            if ($absolute === null) {
                continue;
            }
            $size = @filesize($absolute) ?: 0;
            if ($size < 1 || $size > 12 * 1024 * 1024) {
                continue;
            }
            $filename = basename($name !== '' ? $name : $path) ?: 'download.pdf';
            if (! str_ends_with(strtolower($filename), '.pdf')) {
                $filename .= '.pdf';
            }
            $out[] = [
                'path' => $absolute,
                'name' => $filename,
                'mime' => $mime !== '' ? $mime : 'application/pdf',
            ];
        }

        return $out;
    }

    private function resolveStoredFilePath(string $path): ?string
    {
        if (is_file($path) && is_readable($path)) {
            return $path;
        }
        foreach (['local', 'public'] as $disk) {
            try {
                if (Storage::disk($disk)->exists($path)) {
                    return Storage::disk($disk)->path($path);
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }

    /**
     * @param  list<array{path: string, name: string, mime: string}>  $attachments
     */
    private function attachFiles(mixed $message, array $attachments): void
    {
        foreach ($attachments as $file) {
            $path = (string) ($file['path'] ?? '');
            if ($path === '' || ! is_readable($path)) {
                continue;
            }
            $message->attach($path, [
                'as' => $file['name'] ?? basename($path),
                'mime' => $file['mime'] ?? 'application/pdf',
            ]);
        }
    }

    /**
     * @param  list<array{path: string, name: string, mime: string}>  $attachments
     */
    private function sendViaPlatformSmtp(
        PlatformSetting $settings,
        string $to,
        string $subject,
        string $htmlBody,
        ?string $textBody,
        array $attachments = []
    ): void {
        $resolved = PlatformSmtpConfig::resolve($settings);
        $config = PlatformSmtpConfig::mailerArray($settings);

        $this->dispatchPlatformSmtp($config, $resolved['fromAddress'], $resolved['fromName'], $to, $subject, $htmlBody, true, $attachments);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  list<array{path: string, name: string, mime: string}>  $attachments
     */
    private function dispatchPlatformSmtp(
        array $config,
        string $fromAddress,
        string $fromName,
        string $to,
        string $subject,
        string $htmlBody,
        bool $allowInsecureRetry = true,
        array $attachments = []
    ): void {
        Config::set('mail.mailers.platform_smtp', $config);
        Mail::purge('platform_smtp');

        try {
            Mail::mailer('platform_smtp')->html($htmlBody, function ($message) use ($to, $subject, $fromAddress, $fromName, $attachments) {
                $message->to($to)
                    ->from($fromAddress, $fromName)
                    ->subject($subject);
                $this->attachFiles($message, $attachments);
            });
        } catch (\Throwable $e) {
            if ($allowInsecureRetry && PlatformSmtpConfig::isCertificateError($e->getMessage())) {
                $config['verify_peer'] = false;
                $this->dispatchPlatformSmtp($config, $fromAddress, $fromName, $to, $subject, $htmlBody, false, $attachments);

                return;
            }
            throw $e;
        }
    }

    /**
     * @param  list<array{path: string, name: string, mime: string}>  $attachments
     */
    private function sendViaDefaultMailer(string $to, string $subject, string $htmlBody, ?string $textBody, array $attachments = []): void
    {
        Mail::html($htmlBody, function ($message) use ($to, $subject, $attachments) {
            $message->to($to)->subject($subject);
            $this->attachFiles($message, $attachments);
        });
    }

}
