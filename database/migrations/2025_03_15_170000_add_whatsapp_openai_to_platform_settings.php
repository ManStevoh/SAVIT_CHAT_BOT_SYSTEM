<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->string('whatsapp_webhook_verify_token')->nullable()->after('mail_from_name');
            $table->string('meta_app_secret')->nullable()->after('whatsapp_webhook_verify_token');
            $table->string('openai_api_key')->nullable()->after('meta_app_secret');
            $table->string('openai_model')->nullable()->after('openai_api_key');
            $table->unsignedInteger('openai_max_tokens')->nullable()->after('openai_model');
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_webhook_verify_token',
                'meta_app_secret',
                'openai_api_key',
                'openai_model',
                'openai_max_tokens',
            ]);
        });
    }
};
