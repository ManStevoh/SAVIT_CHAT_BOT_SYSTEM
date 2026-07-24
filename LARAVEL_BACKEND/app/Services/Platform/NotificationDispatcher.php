<?php

namespace App\Services\Platform;

use App\Models\Company;
use App\Models\CompanyNotification;
use App\Models\NotificationDelivery;
use App\Models\PlatformSetting;
use App\Services\MailService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Unified notification dispatcher — in-app + optional email when platform toggles allow.
 */
final class NotificationDispatcher
{
    public function __construct(
        protected MailService $mail,
        protected NotificationTemplateService $templates,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function dispatch(Company $company, string $templateKey, array $data = []): CompanyNotification
    {
        [$title, $body, $type] = $this->templates->resolve($templateKey, $data);

        $notification = CompanyNotification::create([
            'company_id' => $company->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
        ]);

        NotificationDelivery::create([
            'company_id' => $company->id,
            'template_key' => $templateKey,
            'channel' => 'in_app',
            'status' => 'sent',
            'payload' => $data !== [] ? $data : null,
        ]);

        $this->maybeSendEmail($company, $templateKey, $title, $body, $data);

        return $notification;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function maybeSendEmail(Company $company, string $templateKey, string $title, ?string $body, array $data): void
    {
        $settings = Cache::remember('platform_notification_toggles', 120, fn () => PlatformSetting::first());
        if (! $settings) {
            return;
        }

        $shouldEmail = match ($templateKey) {
            'approval.pending', 'approval.executed' => (bool) $settings->notify_security_alerts,
            'intelligence.case_opened' => (bool) $settings->notify_daily_summary,
            'payment.received', 'subscription.confirmed' => (bool) $settings->notify_failed_payments,
            'subscription.expiring', 'subscription.expired' => false,
            'usage.limit_warning' => (bool) $settings->notify_usage_alerts,
            default => false,
        };

        if (! $shouldEmail) {
            return;
        }

        $email = $data['owner_email'] ?? $company->email;
        if (! is_string($email) || $email === '') {
            return;
        }

        try {
            $this->mail->send($email, $title, '<p>'.e($body ?? $title).'</p>');
            NotificationDelivery::create([
                'company_id' => $company->id,
                'template_key' => $templateKey,
                'channel' => 'email',
                'status' => 'sent',
                'recipient' => $email,
                'payload' => $data !== [] ? $data : null,
            ]);
        } catch (\Throwable $e) {
            NotificationDelivery::create([
                'company_id' => $company->id,
                'template_key' => $templateKey,
                'channel' => 'email',
                'status' => 'failed',
                'recipient' => $email,
                'error' => mb_substr($e->getMessage(), 0, 500),
            ]);
            Log::warning('Notification email failed', ['template' => $templateKey, 'error' => $e->getMessage()]);
        }
    }
}
