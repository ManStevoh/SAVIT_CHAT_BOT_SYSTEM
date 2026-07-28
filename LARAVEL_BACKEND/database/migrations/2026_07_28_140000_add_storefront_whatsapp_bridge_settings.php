<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('company_settings', 'storefront_whatsapp_order_notify')) {
                $table->boolean('storefront_whatsapp_order_notify')->default(true)->after('abandoned_cart_recovery_enabled');
            }
            if (! Schema::hasColumn('company_settings', 'abandoned_cart_template_name')) {
                $table->string('abandoned_cart_template_name', 128)->nullable()->after('storefront_whatsapp_order_notify');
            }
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            foreach (['storefront_whatsapp_order_notify', 'abandoned_cart_template_name'] as $col) {
                if (Schema::hasColumn('company_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
