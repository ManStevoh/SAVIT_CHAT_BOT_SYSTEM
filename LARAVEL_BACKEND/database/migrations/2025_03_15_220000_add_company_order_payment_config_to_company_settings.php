<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->json('order_payment_mpesa_config')->nullable()->after('orders_accept_stripe');
            $table->json('order_payment_stripe_config')->nullable()->after('order_payment_mpesa_config');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['order_payment_mpesa_config', 'order_payment_stripe_config']);
        });
    }
};
