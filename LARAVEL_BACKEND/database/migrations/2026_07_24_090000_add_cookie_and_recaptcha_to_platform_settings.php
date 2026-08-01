<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->boolean('cookie_banner_enabled')->default(true)->after('landing_trusted_companies');
            $table->text('cookie_banner_text')->nullable()->after('cookie_banner_enabled');
            $table->string('cookie_policy_url')->nullable()->after('cookie_banner_text');
            $table->boolean('recaptcha_enabled')->default(false)->after('cookie_policy_url');
            $table->string('recaptcha_site_key')->nullable()->after('recaptcha_enabled');
            $table->text('recaptcha_secret_key')->nullable()->after('recaptcha_site_key');
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn([
                'cookie_banner_enabled',
                'cookie_banner_text',
                'cookie_policy_url',
                'recaptcha_enabled',
                'recaptcha_site_key',
                'recaptcha_secret_key',
            ]);
        });
    }
};
