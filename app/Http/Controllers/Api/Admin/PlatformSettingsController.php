<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Services\MailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PlatformSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        $settings = PlatformSetting::first();
        $data = $settings ? $settings->toArray() : [];
        // SMTP password is hidden on model; expose only "set" indicator for frontend
        $smtpPasswordMasked = null;
        if ($settings && $settings->getRawOriginal('smtp_password') !== null && $settings->getRawOriginal('smtp_password') !== '') {
            $smtpPasswordMasked = '********';
        }
        $appLogoUrl = null;
        if ($settings && ! empty($settings->app_logo)) {
            $appLogoUrl = Storage::disk('public')->exists($settings->app_logo)
                ? asset('storage/' . $settings->app_logo)
                : null;
        }
        // Map snake_case to camelCase for frontend
        $out = [
            'platformName' => $data['platform_name'] ?? null,
            'primaryColor' => $data['primary_color'] ?? null,
            'secondaryColor' => $data['secondary_color'] ?? null,
            'appLogo' => $appLogoUrl,
            'supportEmail' => $data['support_email'] ?? null,
            'maintenanceMode' => (bool) ($data['maintenance_mode'] ?? false),
            'aiModel' => $data['ai_model'] ?? null,
            'maxTokensPerRequest' => isset($data['max_tokens_per_request']) ? (int) $data['max_tokens_per_request'] : null,
            'rateLimitPerMinute' => isset($data['rate_limit_per_minute']) ? (int) $data['rate_limit_per_minute'] : null,
            'smtpHost' => $data['smtp_host'] ?? null,
            'smtpPort' => $data['smtp_port'] ?? null,
            'smtpEncryption' => $data['smtp_encryption'] ?? null,
            'smtpUser' => $data['smtp_username'] ?? null,
            'smtpPassword' => $smtpPasswordMasked,
            'fromEmail' => $data['mail_from_address'] ?? null,
            'fromName' => $data['mail_from_name'] ?? null,
            'whatsappWebhookVerifyToken' => $data['whatsapp_webhook_verify_token'] ?? null,
            'metaAppSecret' => $this->maskSecret($settings, 'meta_app_secret'),
            'openaiApiKey' => $this->maskSecret($settings, 'openai_api_key'),
            'openaiModel' => $data['openai_model'] ?? null,
            'openaiMaxTokens' => isset($data['openai_max_tokens']) ? (int) $data['openai_max_tokens'] : null,
            'sessionTimeoutMinutes' => isset($data['session_timeout_minutes']) ? (int) $data['session_timeout_minutes'] : null,
            'maxLoginAttempts' => isset($data['max_login_attempts']) ? (int) $data['max_login_attempts'] : null,
            'passwordMinLength' => isset($data['password_min_length']) ? (int) $data['password_min_length'] : null,
            'require2fa' => (bool) ($data['require_2fa'] ?? false),
            'ipAllowlistEnabled' => (bool) ($data['ip_allowlist_enabled'] ?? false),
            'auditLoggingEnabled' => (bool) ($data['audit_logging_enabled'] ?? true),
            'notifyNewRegistrations' => (bool) ($data['notify_new_registrations'] ?? true),
            'notifyFailedPayments' => (bool) ($data['notify_failed_payments'] ?? true),
            'notifySecurityAlerts' => (bool) ($data['notify_security_alerts'] ?? true),
            'notifySystemErrors' => (bool) ($data['notify_system_errors'] ?? true),
            'notifyUsageAlerts' => (bool) ($data['notify_usage_alerts'] ?? true),
            'notifyDailySummary' => (bool) ($data['notify_daily_summary'] ?? true),
        ];
        return response()->json($out);
    }

    private function maskSecret(?PlatformSetting $settings, string $attribute): ?string
    {
        if (! $settings) {
            return null;
        }
        $raw = $settings->getRawOriginal($attribute);
        if ($raw === null || $raw === '') {
            return null;
        }
        return '********';
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platformName' => 'nullable|string|max:255',
            'primaryColor' => 'nullable|string|max:50',
            'secondaryColor' => 'nullable|string|max:50',
            'logo' => 'nullable|image|max:2048',
            'supportEmail' => 'nullable|email',
            'maintenanceMode' => 'sometimes|boolean',
            'aiModel' => 'nullable|string|max:255',
            'maxTokensPerRequest' => 'nullable|integer|min:1',
            'rateLimitPerMinute' => 'nullable|integer|min:1',
            'smtpHost' => 'nullable|string|max:255',
            'smtpPort' => 'nullable|integer|min:1|max:65535',
            'smtpEncryption' => 'nullable|string|in:none,ssl,tls',
            'smtpUser' => 'nullable|string|max:255',
            'smtpPassword' => 'nullable|string|max:500',
            'fromEmail' => 'nullable|email|max:255',
            'fromName' => 'nullable|string|max:255',
            'whatsappWebhookVerifyToken' => 'nullable|string|max:255',
            'metaAppSecret' => 'nullable|string|max:500',
            'openaiApiKey' => 'nullable|string|max:500',
            'openaiModel' => 'nullable|string|max:100',
            'openaiMaxTokens' => 'nullable|integer|min:1|max:4096',
            'sessionTimeoutMinutes' => 'nullable|integer|min:1|max:1440',
            'maxLoginAttempts' => 'nullable|integer|min:1|max:20',
            'passwordMinLength' => 'nullable|integer|min:6|max:128',
            'require2fa' => 'sometimes|boolean',
            'ipAllowlistEnabled' => 'sometimes|boolean',
            'auditLoggingEnabled' => 'sometimes|boolean',
            'notifyNewRegistrations' => 'sometimes|boolean',
            'notifyFailedPayments' => 'sometimes|boolean',
            'notifySecurityAlerts' => 'sometimes|boolean',
            'notifySystemErrors' => 'sometimes|boolean',
            'notifyUsageAlerts' => 'sometimes|boolean',
            'notifyDailySummary' => 'sometimes|boolean',
        ]);

        $settings = PlatformSetting::firstOrNew([]);
        $map = [
            'platformName' => 'platform_name',
            'primaryColor' => 'primary_color',
            'secondaryColor' => 'secondary_color',
            'supportEmail' => 'support_email',
            'maintenanceMode' => 'maintenance_mode',
            'aiModel' => 'ai_model',
            'maxTokensPerRequest' => 'max_tokens_per_request',
            'rateLimitPerMinute' => 'rate_limit_per_minute',
            'smtpHost' => 'smtp_host',
            'smtpPort' => 'smtp_port',
            'smtpEncryption' => 'smtp_encryption',
            'smtpUser' => 'smtp_username',
            'smtpPassword' => 'smtp_password',
            'fromEmail' => 'mail_from_address',
            'fromName' => 'mail_from_name',
            'whatsappWebhookVerifyToken' => 'whatsapp_webhook_verify_token',
            'metaAppSecret' => 'meta_app_secret',
            'openaiApiKey' => 'openai_api_key',
            'openaiModel' => 'openai_model',
            'openaiMaxTokens' => 'openai_max_tokens',
            'sessionTimeoutMinutes' => 'session_timeout_minutes',
            'maxLoginAttempts' => 'max_login_attempts',
            'passwordMinLength' => 'password_min_length',
            'require2fa' => 'require_2fa',
            'ipAllowlistEnabled' => 'ip_allowlist_enabled',
            'auditLoggingEnabled' => 'audit_logging_enabled',
            'notifyNewRegistrations' => 'notify_new_registrations',
            'notifyFailedPayments' => 'notify_failed_payments',
            'notifySecurityAlerts' => 'notify_security_alerts',
            'notifySystemErrors' => 'notify_system_errors',
            'notifyUsageAlerts' => 'notify_usage_alerts',
            'notifyDailySummary' => 'notify_daily_summary',
        ];
        $skipIfMasked = ['smtp_password', 'meta_app_secret', 'openai_api_key'];
        foreach ($validated as $key => $value) {
            if ($key === 'logo') {
                continue;
            }
            $col = $map[$key] ?? null;
            if ($col === null) {
                continue;
            }
            if (in_array($col, $skipIfMasked, true) && ($value === '' || $value === '********')) {
                continue;
            }
            $settings->setAttribute($col, $value);
        }

        if ($request->hasFile('logo')) {
            if ($settings->app_logo) {
                Storage::disk('public')->delete($settings->app_logo);
            }
            $path = $request->file('logo')->store('app_logos', 'public');
            $settings->app_logo = $path;
        }

        $settings->save();

        return response()->json([
            'success' => true,
            'message' => 'Platform settings updated successfully',
        ]);
    }

    public function testEmail(Request $request, MailService $mailService): JsonResponse
    {
        $validated = $request->validate([
            'to' => 'required|email',
        ]);

        try {
            $mailService->sendTestEmail($validated['to']);
            return response()->json([
                'success' => true,
                'message' => 'Test email sent successfully. Check the inbox for ' . $validated['to'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test email: ' . $e->getMessage(),
            ], 422);
        }
    }
}
