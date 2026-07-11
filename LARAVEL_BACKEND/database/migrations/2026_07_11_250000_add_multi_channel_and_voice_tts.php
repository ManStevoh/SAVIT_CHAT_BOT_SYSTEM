<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            if (! Schema::hasColumn('chats', 'channel')) {
                $table->string('channel', 32)->default('whatsapp')->after('company_id');
                $table->index(['company_id', 'channel']);
            }
            if (! Schema::hasColumn('chats', 'channel_user_id')) {
                $table->string('channel_user_id', 120)->nullable()->after('channel');
            }
        });

        Schema::table('company_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('company_settings', 'web_widget_token')) {
                $table->string('web_widget_token', 64)->nullable()->after('agent_proactive_enabled');
            }
            if (! Schema::hasColumn('company_settings', 'agent_voice_reply_enabled')) {
                $table->boolean('agent_voice_reply_enabled')->default(false)->after('web_widget_token');
            }
        });

        if (Schema::hasTable('company_settings') && Schema::hasColumn('company_settings', 'web_widget_token')) {
            \App\Models\CompanySetting::query()
                ->whereNull('web_widget_token')
                ->each(function (\App\Models\CompanySetting $setting) {
                    $setting->update(['web_widget_token' => Str::random(32)]);
                });
        }
    }

    public function down(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            if (Schema::hasColumn('chats', 'channel_user_id')) {
                $table->dropColumn('channel_user_id');
            }
            if (Schema::hasColumn('chats', 'channel')) {
                $table->dropColumn('channel');
            }
        });

        Schema::table('company_settings', function (Blueprint $table) {
            if (Schema::hasColumn('company_settings', 'agent_voice_reply_enabled')) {
                $table->dropColumn('agent_voice_reply_enabled');
            }
            if (Schema::hasColumn('company_settings', 'web_widget_token')) {
                $table->dropColumn('web_widget_token');
            }
        });
    }
};
