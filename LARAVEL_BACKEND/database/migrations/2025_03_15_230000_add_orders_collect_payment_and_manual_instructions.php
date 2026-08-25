<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->boolean('orders_collect_payment_enabled')->default(true)->after('orders_accept_stripe');
            $table->text('order_payment_manual_instructions')->nullable()->after('order_payment_stripe_config');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['orders_collect_payment_enabled', 'order_payment_manual_instructions']);
        });
    }
};
