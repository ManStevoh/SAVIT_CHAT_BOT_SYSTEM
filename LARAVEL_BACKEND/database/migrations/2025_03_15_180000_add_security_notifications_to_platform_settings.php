<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            // Security
            $table->unsignedSmallInteger('session_timeout_minutes')->nullable()->after('openai_max_tokens');
            $table->unsignedTinyInteger('max_login_attempts')->nullable()->after('session_timeout_minutes');
            $table->unsignedTinyInteger('password_min_length')->nullable()->after('max_login_attempts');
            $table->boolean('require_2fa')->default(false)->after('password_min_length');
            $table->boolean('ip_allowlist_enabled')->default(false)->after('require_2fa');
            $table->boolean('audit_logging_enabled')->default(true)->after('ip_allowlist_enabled');
            // Notifications
            $table->boolean('notify_new_registrations')->default(true)->after('audit_logging_enabled');
            $table->boolean('notify_failed_payments')->default(true)->after('notify_new_registrations');
            $table->boolean('notify_security_alerts')->default(true)->after('notify_failed_payments');
            $table->boolean('notify_system_errors')->default(true)->after('notify_security_alerts');
            $table->boolean('notify_usage_alerts')->default(true)->after('notify_system_errors');
            $table->boolean('notify_daily_summary')->default(true)->after('notify_usage_alerts');
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn([
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
            ]);
        });
    }
};
