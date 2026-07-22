<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Services\AI\AiLearningConfig;
use App\Services\MailService;
use App\Services\Platform\AuditService;
use App\Services\WhatsApp\WhatsAppBillingModel;
use App\Services\WhatsApp\WhatsAppPlatformConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PlatformSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        $settings = PlatformSetting::first();
        // Use raw attributes so corrupt JSON columns cannot 500 the whole settings page.
        $data = $settings?->getAttributes() ?? [];

        $smtpPasswordMasked = null;
        if ($settings && $settings->getRawOriginal('smtp_password') !== null && $settings->getRawOriginal('smtp_password') !== '') {
            $smtpPasswordMasked = '********';
        }

        $appLogoUrl = null;
        $logoPath = $data['app_logo'] ?? null;
        if (is_string($logoPath) && $logoPath !== '') {
            try {
                $appLogoUrl = Storage::disk('public')->exists($logoPath)
                    ? asset('storage/'.$logoPath)
                    : null;
            } catch (\Throwable) {
                $appLogoUrl = null;
            }
        }

        $landingTrusted = $this->decodeJsonColumn($data['landing_trusted_companies'] ?? null);
        $aiLearning = $this->decodeJsonColumn($data['ai_learning_config'] ?? null);

        $out = [
            'platformName' => $data['platform_name'] ?? null,
            'primaryColor' => $data['primary_color'] ?? null,
            'secondaryColor' => $data['secondary_color'] ?? null,
            'appLogo' => $appLogoUrl,
            'supportEmail' => $data['support_email'] ?? null,
            'maintenanceMode' => (bool) ($data['maintenance_mode'] ?? false),
            'defaultTimezone' => $data['default_timezone'] ?? 'UTC',
            'maintenanceMessage' => $data['maintenance_message'] ?? null,
            'allowNewRegistrations' => (bool) ($data['allow_new_registrations'] ?? true),
            'requireEmailVerification' => (bool) ($data['require_email_verification'] ?? false),
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
            'whatsappWebhookVerifyToken' => $this->maskSecret($settings, 'whatsapp_webhook_verify_token'),
            'metaAppSecret' => $this->maskSecret($settings, 'meta_app_secret'),
            'whatsappEmbeddedAppId' => $data['whatsapp_embedded_app_id'] ?? null,
            'whatsappEmbeddedConfigId' => $data['whatsapp_embedded_config_id'] ?? null,
            'whatsappEmbeddedAppSecret' => $this->maskSecret($settings, 'whatsapp_embedded_app_secret'),
            'whatsappEmbeddedRedirectUri' => $data['whatsapp_embedded_redirect_uri'] ?? null,
            'whatsappEnableCoexist' => (bool) ($data['whatsapp_enable_coexist'] ?? false),
            'whatsappEmbeddedSignupEnabled' => (bool) ($data['whatsapp_embedded_signup_enabled'] ?? true),
            'whatsappManualConnectEnabled' => (bool) ($data['whatsapp_manual_connect_enabled'] ?? true),
            'whatsappBillingModel' => WhatsAppBillingModel::normalize($data['whatsapp_billing_model'] ?? null),
            'whatsappBillingModelLabel' => WhatsAppBillingModel::label($data['whatsapp_billing_model'] ?? WhatsAppBillingModel::TECH_PROVIDER),
            'whatsappExtendedCreditLineId' => $data['whatsapp_extended_credit_line_id'] ?? null,
            'whatsappCreditSharingSystemToken' => $this->maskSecret($settings, 'whatsapp_credit_sharing_system_token'),
            'whatsappWabaCurrency' => WhatsAppPlatformConfig::wabaCurrency(),
            'whatsappSolutionPartnerReady' => WhatsAppPlatformConfig::isSolutionPartnerBillingReady(),
            'whatsappBillingRequiresMetaPayment' => ! WhatsAppPlatformConfig::isSolutionPartnerBilling(),
            'whatsappWebhookUrl' => WhatsAppPlatformConfig::webhookCallbackUrl(),
            'whatsappEmbeddedSignupReady' => WhatsAppPlatformConfig::hasEmbeddedSignupCredentials(),
            'whatsappEmbeddedSignupActive' => WhatsAppPlatformConfig::isEmbeddedSignupEnabled(),
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
            'landingTrustedCompanies' => is_array($landingTrusted) ? $landingTrusted : [],
            'aiLearningConfig' => array_merge(
                AiLearningConfig::defaults(),
                is_array($aiLearning) ? $aiLearning : []
            ),
        ];

        return response()->json($out);
    }

    private function decodeJsonColumn(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value)) {
            return null;
        }

        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
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

    public function update(Request $request, AuditService $audit): JsonResponse
    {
        $validated = $request->validate([
            'platformName' => 'nullable|string|max:255',
            'primaryColor' => 'nullable|string|max:50',
            'secondaryColor' => 'nullable|string|max:50',
            'logo' => 'nullable|image|max:2048',
            'supportEmail' => 'nullable|email',
            'maintenanceMode' => 'sometimes|boolean',
            'defaultTimezone' => 'nullable|string|max:50',
            'maintenanceMessage' => 'nullable|string|max:2000',
            'allowNewRegistrations' => 'sometimes|boolean',
            'requireEmailVerification' => 'sometimes|boolean',
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
            'whatsappEmbeddedAppId' => 'nullable|string|max:100',
            'whatsappEmbeddedConfigId' => 'nullable|string|max:100',
            'whatsappEmbeddedAppSecret' => 'nullable|string|max:500',
            'whatsappEmbeddedRedirectUri' => 'nullable|string|max:500',
            'whatsappEnableCoexist' => 'sometimes|boolean',
            'whatsappEmbeddedSignupEnabled' => 'sometimes|boolean',
            'whatsappManualConnectEnabled' => 'sometimes|boolean',
            'whatsappBillingModel' => 'sometimes|string|in:' . implode(',', WhatsAppBillingModel::all()),
            'whatsappExtendedCreditLineId' => 'nullable|string|max:100',
            'whatsappCreditSharingSystemToken' => 'nullable|string|max:2000',
            'whatsappWabaCurrency' => 'nullable|string|size:3|in:' . implode(',', WhatsAppBillingModel::SUPPORTED_WABA_CURRENCIES),
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
            'landingTrustedCompanies' => 'nullable|array',
            'landingTrustedCompanies.*' => 'string|max:255',
            'aiLearningConfig' => 'sometimes|array',
            'aiLearningConfig.learningEnabled' => 'sometimes|boolean',
            'aiLearningConfig.defaultLearnFromChats' => 'sometimes|boolean',
            'aiLearningConfig.allowCompanyOverride' => 'sometimes|boolean',
            'aiLearningConfig.maxSamplesPerCompany' => 'sometimes|integer|min:10|max:2000',
            'aiLearningConfig.promptSampleLimit' => 'sometimes|integer|min:1|max:25',
            'aiLearningConfig.retentionDays' => 'sometimes|integer|min:7|max:1825',
            'aiLearningConfig.piiRedactionEnabled' => 'sometimes|boolean',
            'aiLearningConfig.storeFaqExchanges' => 'sometimes|boolean',
            'aiLearningConfig.storeAgentReplies' => 'sometimes|boolean',
            'aiLearningConfig.faqEmbeddingsEnabled' => 'sometimes|boolean',
            'aiLearningConfig.learningEmbeddingsEnabled' => 'sometimes|boolean',
            'aiLearningConfig.faqSemanticMinScore' => 'sometimes|numeric|min:0.5|max:0.99',
            'aiLearningConfig.learningSemanticMinScore' => 'sometimes|numeric|min:0.5|max:0.99',
            'aiLearningConfig.faqDirectMinScore' => 'sometimes|numeric|min:50|max:100',
            'aiLearningConfig.minReplyLength' => 'sometimes|integer|min:5|max:500',
            'aiLearningConfig.maxPromptTokens' => 'sometimes|integer|min:2000|max:128000',
            'aiLearningConfig.embeddingModelKey' => 'sometimes|string|max:120',
            'aiLearningConfig.requireLearningReview' => 'sometimes|boolean',
            'aiLearningConfig.autoDetectLanguage' => 'sometimes|boolean',
            'aiLearningConfig.fallbackLanguage' => 'sometimes|string|max:10',
            'aiLearningConfig.aiCostMarkupPercent' => 'sometimes|numeric|min:0|max:100',
        ]);

        $settings = PlatformSetting::firstOrNew([]);
        $map = [
            'platformName' => 'platform_name',
            'primaryColor' => 'primary_color',
            'secondaryColor' => 'secondary_color',
            'supportEmail' => 'support_email',
            'maintenanceMode' => 'maintenance_mode',
            'defaultTimezone' => 'default_timezone',
            'maintenanceMessage' => 'maintenance_message',
            'allowNewRegistrations' => 'allow_new_registrations',
            'requireEmailVerification' => 'require_email_verification',
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
            'whatsappEmbeddedAppId' => 'whatsapp_embedded_app_id',
            'whatsappEmbeddedConfigId' => 'whatsapp_embedded_config_id',
            'whatsappEmbeddedAppSecret' => 'whatsapp_embedded_app_secret',
            'whatsappEmbeddedRedirectUri' => 'whatsapp_embedded_redirect_uri',
            'whatsappEnableCoexist' => 'whatsapp_enable_coexist',
            'whatsappEmbeddedSignupEnabled' => 'whatsapp_embedded_signup_enabled',
            'whatsappManualConnectEnabled' => 'whatsapp_manual_connect_enabled',
            'whatsappBillingModel' => 'whatsapp_billing_model',
            'whatsappExtendedCreditLineId' => 'whatsapp_extended_credit_line_id',
            'whatsappCreditSharingSystemToken' => 'whatsapp_credit_sharing_system_token',
            'whatsappWabaCurrency' => 'whatsapp_waba_currency',
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
            'landingTrustedCompanies' => 'landing_trusted_companies',
        ];
        $before = $settings->exists ? $settings->only(array_values($map)) : [];
        // Never persist UI mask placeholders — those fields are returned as ******** on GET.
        $skipIfMasked = [
            'smtp_password',
            'meta_app_secret',
            'whatsapp_webhook_verify_token',
            'whatsapp_embedded_app_secret',
            'whatsapp_credit_sharing_system_token',
            'openai_api_key',
        ];
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

        if (array_key_exists('aiLearningConfig', $validated)) {
            $settings->ai_learning_config = array_merge(
                AiLearningConfig::defaults(),
                is_array($settings->ai_learning_config) ? $settings->ai_learning_config : [],
                $validated['aiLearningConfig'],
            );
        }

        $settings->save();
        WhatsAppPlatformConfig::clearCache();
        AiLearningConfig::clearCache();

        $audit->log(
            'platform.settings.updated',
            PlatformSetting::class,
            $settings->id,
            $before,
            $settings->only(array_values($map)),
            null,
            $request->user(),
            ['changed_keys' => array_keys($validated)],
        );

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
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send test email. Check SMTP settings and try again.',
            ], 422);
        }
    }
}
