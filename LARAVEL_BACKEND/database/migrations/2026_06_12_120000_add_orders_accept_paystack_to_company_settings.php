<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('company_settings', 'orders_accept_paystack')) {
            Schema::table('company_settings', function (Blueprint $table) {
                $table->boolean('orders_accept_paystack')->default(false)->after('orders_accept_stripe');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('company_settings', 'orders_accept_paystack')) {
            Schema::table('company_settings', function (Blueprint $table) {
                $table->dropColumn('orders_accept_paystack');
            });
        }
    }
};
