<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->string('whatsapp_embedded_app_id')->nullable()->after('meta_app_secret');
            $table->string('whatsapp_embedded_config_id')->nullable()->after('whatsapp_embedded_app_id');
            $table->text('whatsapp_embedded_app_secret')->nullable()->after('whatsapp_embedded_config_id');
            $table->string('whatsapp_embedded_redirect_uri')->nullable()->after('whatsapp_embedded_app_secret');
            $table->boolean('whatsapp_enable_coexist')->default(false)->after('whatsapp_embedded_redirect_uri');
        });

        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->string('onboarding_status')->default('pending')->after('status');
            $table->text('onboarding_error')->nullable()->after('onboarding_status');
            $table->timestamp('webhook_subscribed_at')->nullable()->after('onboarding_error');
            $table->timestamp('phone_registered_at')->nullable()->after('webhook_subscribed_at');
            $table->string('display_name_status')->nullable()->after('phone_registered_at');
            $table->string('quality_rating')->nullable()->after('display_name_status');
            $table->text('registration_pin')->nullable()->after('quality_rating');
            $table->timestamp('connected_at')->nullable()->after('registration_pin');
            $table->timestamp('disconnected_at')->nullable()->after('connected_at');
        });

        Schema::create('whatsapp_message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('meta_template_id')->nullable();
            $table->string('name');
            $table->string('language', 10)->default('en');
            $table->string('category')->default('utility');
            $table->string('status')->default('pending');
            $table->json('components')->nullable();
            $table->text('body_preview')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'name', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_message_templates');

        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'onboarding_status',
                'onboarding_error',
                'webhook_subscribed_at',
                'phone_registered_at',
                'display_name_status',
                'quality_rating',
                'registration_pin',
                'connected_at',
                'disconnected_at',
            ]);
        });

        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_embedded_app_id',
                'whatsapp_embedded_config_id',
                'whatsapp_embedded_app_secret',
                'whatsapp_embedded_redirect_uri',
                'whatsapp_enable_coexist',
            ]);
        });
    }
};
