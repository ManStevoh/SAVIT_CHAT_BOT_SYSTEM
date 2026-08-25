<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('company_settings') && ! Schema::hasColumn('company_settings', 'dev_mode_enabled')) {
            Schema::table('company_settings', function (Blueprint $table) {
                $table->boolean('dev_mode_enabled')->default(false)->after('learn_from_conversations');
            });
        }

        if (Schema::hasTable('platform_settings') && ! Schema::hasColumn('platform_settings', 'dev_mode_enabled')) {
            Schema::table('platform_settings', function (Blueprint $table) {
                $table->boolean('dev_mode_enabled')->default(false);
            });
        }

        if (Schema::hasTable('ai_request_logs') && ! Schema::hasColumn('ai_request_logs', 'prompt_payload')) {
            Schema::table('ai_request_logs', function (Blueprint $table) {
                $table->longText('prompt_payload')->nullable()->after('error_message');
            });
        }

        if (Schema::hasTable('messages') && ! Schema::hasColumn('messages', 'ai_request_log_id')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->unsignedBigInteger('ai_request_log_id')->nullable()->after('learning_sample_id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('company_settings') && Schema::hasColumn('company_settings', 'dev_mode_enabled')) {
            Schema::table('company_settings', function (Blueprint $table) {
                $table->dropColumn('dev_mode_enabled');
            });
        }

        if (Schema::hasTable('ai_request_logs') && Schema::hasColumn('ai_request_logs', 'prompt_payload')) {
            Schema::table('ai_request_logs', function (Blueprint $table) {
                $table->dropColumn('prompt_payload');
            });
        }

        if (Schema::hasTable('messages') && Schema::hasColumn('messages', 'ai_request_log_id')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->dropColumn('ai_request_log_id');
            });
        }
    }
};
