<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('company_settings', 'channel_ingest_secret')) {
                $table->string('channel_ingest_secret', 64)->nullable()->after('web_widget_token');
            }
        });

        if (Schema::hasTable('company_settings') && Schema::hasColumn('company_settings', 'channel_ingest_secret')) {
            \App\Models\CompanySetting::query()
                ->whereNull('channel_ingest_secret')
                ->each(function (\App\Models\CompanySetting $setting) {
                    $setting->update(['channel_ingest_secret' => Str::random(32)]);
                });
        }
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            if (Schema::hasColumn('company_settings', 'channel_ingest_secret')) {
                $table->dropColumn('channel_ingest_secret');
            }
        });
    }
};
