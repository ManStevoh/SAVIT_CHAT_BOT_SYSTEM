<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->string('whatsapp_billing_model', 32)->default('tech_provider')->after('whatsapp_manual_connect_enabled');
            $table->string('whatsapp_extended_credit_line_id', 100)->nullable()->after('whatsapp_billing_model');
            $table->text('whatsapp_credit_sharing_system_token')->nullable()->after('whatsapp_extended_credit_line_id');
            $table->string('whatsapp_waba_currency', 3)->default('USD')->after('whatsapp_credit_sharing_system_token');
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_billing_model',
                'whatsapp_extended_credit_line_id',
                'whatsapp_credit_sharing_system_token',
                'whatsapp_waba_currency',
            ]);
        });
    }
};
