<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        'openai_api_key',
        'openai_model',
        'openai_max_tokens',
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
    ];

    protected $casts = [
        'maintenance_mode' => 'boolean',
        'allow_new_registrations' => 'boolean',
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
    ];

    /** Hide secrets when serializing (e.g. for API GET); controller returns masked values. */
    protected $hidden = [
        'smtp_password',
        'meta_app_secret',
        'openai_api_key',
    ];

    /** Whether SMTP is configured enough to send mail. */
    public function hasSmtpConfigured(): bool
    {
        return ! empty($this->smtp_host) && ! empty($this->mail_from_address);
    }
}
