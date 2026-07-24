<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PlatformSetting extends Model
{
    protected $fillable = [
        'platform_name',
        'primary_color',
        'secondary_color',
        'app_logo',
        'support_email',
        'maintenance_mode',
        'default_timezone',
        'maintenance_message',
        'allow_new_registrations',
        'require_email_verification',
        'ai_model',
        'max_tokens_per_request',
        'rate_limit_per_minute',
        'smtp_host',
        'smtp_port',
        'smtp_encryption',
        'smtp_username',
        'smtp_password',
        'mail_from_address',
        'mail_from_name',
        'whatsapp_webhook_verify_token',
        'meta_app_secret',
        'whatsapp_embedded_app_id',
        'whatsapp_embedded_config_id',
        'whatsapp_embedded_app_secret',
        'whatsapp_embedded_redirect_uri',
        'whatsapp_enable_coexist',
        'whatsapp_embedded_signup_enabled',
        'whatsapp_manual_connect_enabled',
        'whatsapp_billing_model',
        'whatsapp_extended_credit_line_id',
        'whatsapp_credit_sharing_system_token',
        'whatsapp_waba_currency',
        'openai_api_key',
        'openai_model',
        'openai_max_tokens',
        'ai_learning_config',
        'session_timeout_minutes',
        'max_login_attempts',
        'password_min_length',
        'require_2fa',
        'ip_allowlist_enabled',
        'audit_logging_enabled',
        'notify_new_registrations',
        'notify_failed_payments',
        'notify_security_alerts',
        'notify_system_errors',
        'notify_usage_alerts',
        'notify_daily_summary',
        'landing_trusted_companies',
        'cookie_banner_enabled',
        'cookie_banner_text',
        'cookie_policy_url',
        'recaptcha_enabled',
        'recaptcha_site_key',
        'recaptcha_secret_key',
    ];

    protected $casts = [
        'maintenance_mode' => 'boolean',
        'allow_new_registrations' => 'boolean',
        'require_email_verification' => 'boolean',
        'whatsapp_enable_coexist' => 'boolean',
        'whatsapp_embedded_signup_enabled' => 'boolean',
        'whatsapp_manual_connect_enabled' => 'boolean',
        'smtp_port' => 'integer',
        'require_2fa' => 'boolean',
        'ip_allowlist_enabled' => 'boolean',
        'audit_logging_enabled' => 'boolean',
        'notify_new_registrations' => 'boolean',
        'notify_failed_payments' => 'boolean',
        'notify_security_alerts' => 'boolean',
        'notify_system_errors' => 'boolean',
        'notify_usage_alerts' => 'boolean',
        'notify_daily_summary' => 'boolean',
        'landing_trusted_companies' => 'array',
        'ai_learning_config' => 'array',
        'cookie_banner_enabled' => 'boolean',
        'recaptcha_enabled' => 'boolean',
    ];

    /** Hide secrets when serializing (e.g. for API GET); controller returns masked values. */
    protected $hidden = [
        'smtp_password',
        'meta_app_secret',
        'whatsapp_embedded_app_secret',
        'whatsapp_credit_sharing_system_token',
        'openai_api_key',
        'recaptcha_secret_key',
    ];

    /** Whether SMTP is configured enough to send mail. */
    public function hasSmtpConfigured(): bool
    {
        return ! empty($this->smtp_host) && ! empty($this->mail_from_address);
    }

    /** Whether new registrations must verify email before login. Default: off. */
    public static function requiresEmailVerification(): bool
    {
        $settings = static::first();

        return (bool) ($settings?->require_email_verification ?? false);
    }

    /** Whether new company registrations are allowed. Default: on. */
    public static function allowsNewRegistrations(): bool
    {
        $settings = static::first();

        return (bool) ($settings?->allow_new_registrations ?? true);
    }

    /** Max failed login attempts before lockout (per email + IP). Default: 5. */
    public static function maxLoginAttempts(): int
    {
        $settings = static::first();
        $value = (int) ($settings?->max_login_attempts ?? 5);

        return max(3, min($value, 20));
    }

    /** Password validation rule using platform minimum length. */
    public static function passwordRule(): PasswordRule
    {
        $settings = static::first();
        $min = (int) ($settings?->password_min_length ?? 8);

        return PasswordRule::min(max(8, min($min, 128)));
    }
}
