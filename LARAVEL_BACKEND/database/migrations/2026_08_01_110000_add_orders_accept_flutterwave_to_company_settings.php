<?php

use App\Models\PaymentGateway;
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
            if (! Schema::hasColumn('company_settings', 'orders_accept_flutterwave')) {
                $table->boolean('orders_accept_flutterwave')->default(false)->after('orders_accept_pesapal');
            }
            if (! Schema::hasColumn('company_settings', 'order_payment_flutterwave_config')) {
                $table->json('order_payment_flutterwave_config')->nullable()->after('order_payment_pesapal_config');
            }
        });

        PaymentGateway::firstOrCreate(
            ['slug' => 'flutterwave'],
            [
                'name' => 'Flutterwave (Cards, Mobile Money, Bank Transfer & USSD)',
                'is_enabled' => false,
                'config' => [
                    'public_key' => '',
                    'secret_key' => '',
                    'secret_hash' => '',
                    'currency' => 'kes',
                    'env' => 'sandbox',
                ],
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            if (Schema::hasColumn('company_settings', 'orders_accept_flutterwave')) {
                $table->dropColumn('orders_accept_flutterwave');
            }
            if (Schema::hasColumn('company_settings', 'order_payment_flutterwave_config')) {
                $table->dropColumn('order_payment_flutterwave_config');
            }
        });
    }
};
