<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('company_settings', 'orders_accept_paypal')) {
                $table->boolean('orders_accept_paypal')->default(false)->after('orders_accept_flutterwave');
            }
            if (! Schema::hasColumn('company_settings', 'order_payment_paypal_config')) {
                $table->json('order_payment_paypal_config')->nullable()->after('order_payment_flutterwave_config');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            if (Schema::hasColumn('company_settings', 'orders_accept_paypal')) {
                $table->dropColumn('orders_accept_paypal');
            }
            if (Schema::hasColumn('company_settings', 'order_payment_paypal_config')) {
                $table->dropColumn('order_payment_paypal_config');
            }
        });
    }
};
