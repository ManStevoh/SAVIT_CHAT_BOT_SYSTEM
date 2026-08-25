<?php

use App\Models\PaymentGateway;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('company_settings', 'orders_accept_pesapal')) {
                $table->boolean('orders_accept_pesapal')->default(false)->after('orders_accept_paystack');
            }
            if (! Schema::hasColumn('company_settings', 'order_payment_pesapal_config')) {
                $table->json('order_payment_pesapal_config')->nullable()->after('order_payment_paystack_config');
            }
        });

        if (Schema::hasTable('payment_gateways')) {
            PaymentGateway::firstOrCreate(
                ['slug' => 'pesapal'],
                [
                    'name' => 'Pesapal (Cards, Mobile Money & Bank)',
                    'is_enabled' => false,
                    'config' => [
                        'consumer_key' => '',
                        'consumer_secret' => '',
                        'env' => 'sandbox',
                        'currency' => 'kes',
                        'ipn_id' => '',
                        'callback_url' => '',
                    ],
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            if (Schema::hasColumn('company_settings', 'orders_accept_pesapal')) {
                $table->dropColumn('orders_accept_pesapal');
            }
            if (Schema::hasColumn('company_settings', 'order_payment_pesapal_config')) {
                $table->dropColumn('order_payment_pesapal_config');
            }
        });
    }
};
